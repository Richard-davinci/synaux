<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Las operaciones formales se ejecutan en transacciones y siempre verifican el dueño de la solicitud.
class SynauxBudgets
{
    // Valida y guarda una versión formal sin sobrescribir ninguna versión ya enviada.
    public function save(Request $request): int
    {
        $data = $request->validate(['budget_id' => 'nullable|integer', 'request_id' => 'required|integer|exists:project_requests,id',
            'title' => 'required|string|max:255', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])],
            'tax_rate' => 'required|numeric|min:0|max:100', 'valid_until' => 'required|date|after_or_equal:today',
            'estimated_delivery_days' => 'required|integer|min:1|max:3650', 'terms' => 'required|string|max:10000',
            'items' => 'required|array|min:1|max:100', 'items.*.description' => 'required|string|max:2000',
            'items.*.quantity' => 'required|numeric|min:0.001|max:10000', 'items.*.unit_price' => 'required|numeric|min:0|max:100000000',
            'send' => 'required|boolean']);

        return DB::transaction(function () use ($request, $data) {
            $record = DB::table('project_requests')->where('id', $data['request_id'])->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'open', 422, 'La solicitud debe estar enviada y abierta.');
            $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->first();
            $proposal = $profile ? DB::table('proposals')->where('project_request_id', $record->id)->where('professional_profile_id', $profile->id)->where('status', 'accepted')->first() : null;
            abort_unless($request->user()->is_admin || $proposal !== null, 403);
            abort_if($record->user_id === $request->user()->id, 422, 'No podés presupuestarte a vos mismo.');
            if (! $profile) {
                // El gestor de Synaux puede completar su perfil de emisión desde el área profesional.
                throw ValidationException::withMessages(['request_id' => 'Completá primero tu perfil profesional para identificar al emisor.']);
            }
            if (! empty($data['budget_id'])) {
                $budget = DB::table('budgets')->where('id', $data['budget_id'])->lockForUpdate()->firstOrFail();
                $origin = $budget->proposal_id ? DB::table('proposals')->where('id', $budget->proposal_id)->first() : null;
                abort_unless(($budget->professional_profile_id ?? $origin?->professional_profile_id) === $profile->id && ($budget->project_request_id ?? $origin?->project_request_id) === $record->id, 403);
                $budgetId = $budget->id;
            } else {
                $budgetId = DB::table('budgets')->insertGetId(['proposal_id' => $proposal?->id,
                    'project_request_id' => $proposal ? null : $record->id, 'professional_profile_id' => $proposal ? null : $profile->id,
                    'created_at' => now(), 'updated_at' => now()]);
            }
            abort_if(DB::table('budget_versions')->where('budget_id', $budgetId)->where('status', 'accepted')->exists(), 422, 'Este presupuesto ya fue aceptado.');
            $draft = DB::table('budget_versions')->where('budget_id', $budgetId)->where('status', 'draft')->first();
            // El documento conserva las partes de esta versión aunque después cambien los perfiles.
            $owner = DB::table('users')->where('id', $record->user_id)->firstOrFail();
            $client = json_decode($record->contact_snapshot ?? 'null', true) ?? ['name' => $owner->name, 'email' => $owner->email];
            $issuer = ['name' => $profile->brand_name, 'contact' => $request->user()->name, 'email' => $request->user()->email];
            $attributes = ['title' => $data['title'], 'currency_code' => $data['currency_code'], 'tax_rate' => $data['tax_rate'],
                'valid_until' => $data['valid_until'], 'terms' => $data['terms'], 'estimated_delivery_days' => $data['estimated_delivery_days'],
                'client_snapshot' => json_encode($client), 'issuer_snapshot' => json_encode($issuer),
                'status' => $data['send'] ? 'sent' : 'draft', 'sent_at' => $data['send'] ? now() : null, 'updated_at' => now()];
            if ($draft) {
                $versionId = $draft->id;
                DB::table('budget_versions')->where('id', $versionId)->update($attributes);
                DB::table('budget_items')->where('budget_version_id', $versionId)->delete();
            } else {
                // Una versión enviada jamás se sobrescribe: cada cambio genera un número nuevo.
                $versionId = DB::table('budget_versions')->insertGetId([...$attributes, 'budget_id' => $budgetId,
                    'version_number' => (DB::table('budget_versions')->where('budget_id', $budgetId)->max('version_number') ?? 0) + 1, 'created_at' => now()]);
            }
            foreach ($data['items'] as $item) {
                DB::table('budget_items')->insert(['budget_version_id' => $versionId, 'description' => $item['description'],
                    'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'unit_label' => 'unidad']);
            }

            return $versionId;
        });
    }

    // Procesa una decisión formal vigente y devuelve el siguiente paso del recorrido.
    public function decide(Request $request): ?int
    {
        $data = $request->validate(['id' => 'required|integer', 'decision' => ['required', Rule::in(['accepted', 'rejected'])], 'confirmed' => 'accepted']);

        return DB::transaction(function () use ($request, $data) {
            $version = DB::table('budget_versions')->where('id', $data['id'])->lockForUpdate()->firstOrFail();
            $budget = DB::table('budgets')->where('id', $version->budget_id)->lockForUpdate()->firstOrFail();
            $origin = $budget->proposal_id ? DB::table('proposals')->where('id', $budget->proposal_id)->first() : null;
            $record = DB::table('project_requests')->where('id', $budget->project_request_id ?? $origin?->project_request_id)->lockForUpdate()->first();
            abort_unless(($record?->user_id ?? $budget->client_user_id) === $request->user()->id, 403);
            abort_if($record && $record->status !== 'open', 422, 'La solicitud ya no está abierta.');
            $latest = DB::table('budget_versions')->where('budget_id', $budget->id)->whereNotNull('sent_at')->max('version_number');
            abort_unless($version->status === 'sent' && $version->version_number === (int) $latest && $version->valid_until >= today()->toDateString(), 422, 'Esta versión no está vigente para tomar una decisión.');
            abort_if(DB::table('budget_versions')->where('budget_id', $budget->id)->where('status', 'accepted')->exists(), 422);
            DB::table('budget_versions')->where('id', $version->id)->update(['status' => $data['decision'], 'updated_at' => now()]);
            if ($data['decision'] !== 'accepted') {
                return null;
            }
            // La unicidad de accepted_budget_version_id y el bloqueo evitan crear proyectos duplicados.
            if ($record) {
                DB::table('project_requests')->where('id', $record->id)->update(['status' => 'closed', 'updated_at' => now()]);
            }

            return DB::table('projects')->insertGetId(['accepted_budget_version_id' => $version->id, 'title' => $version->title,
                'status' => 'pending', 'progress' => 0, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
