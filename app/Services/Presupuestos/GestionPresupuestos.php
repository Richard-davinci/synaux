<?php

namespace App\Services\Presupuestos;

use App\Services\Notifications\NotificationCenter;
use App\Models\Organizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Las operaciones formales se ejecutan en transacciones y siempre verifican el dueño de la solicitud.
class GestionPresupuestos
{
    public function __construct(private NotificationCenter $notifications)
    {
    }

    // Valida y guarda una versión formal sin sobrescribir ninguna versión ya enviada.
    public function save(Request $request): int
    {
        // La emisión directa es exclusiva de Gestión y usa un contacto externo guardado en la versión.
        $direct = $request->boolean('direct');
        abort_if($direct && !$request->user()->puedeGestionarSynaux(), 403);
        $data = $request->validate(['direct' => 'sometimes|boolean', 'budget_id' => 'nullable|integer',
            'request_id' => ['nullable', Rule::requiredIf(!$direct), Rule::prohibitedIf($direct), 'integer', 'exists:project_requests,id'],
            'client' => [Rule::excludeIf(!$direct), Rule::requiredIf($direct), 'array:name,email,company,phone,address'],
            'client.name' => 'required_with:client|string|max:255', 'client.email' => 'nullable|email|max:254',
            'client.company' => 'nullable|string|max:255', 'client.phone' => 'nullable|string|max:50', 'client.address' => 'nullable|string|max:500',
            'title' => 'required|string|max:255', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])],
            'tax_rate' => 'required|numeric|min:0|max:100', 'valid_until' => 'required|date|after_or_equal:today',
            'estimated_delivery_days' => 'required|integer|min:1|max:3650', 'terms' => 'required|string|max:10000',
            'items' => 'required|array|min:1|max:100', 'items.*.description' => 'required|string|max:2000',
            'items.*.quantity' => 'required|numeric|min:0.001|max:10000', 'items.*.unit_price' => 'required|numeric|min:0|max:100000000',
            'send' => 'required|boolean']);

        return DB::transaction(function () use ($request, $data, $direct) {
            $record = $direct ? null : DB::table('project_requests')->where('id', $data['request_id'])->lockForUpdate()->firstOrFail();
            // Los conflictos de estado vuelven al formulario con un mensaje y conservan sus datos.
            if ($record && $record->status !== 'open') {
                throw ValidationException::withMessages(['request_id' => 'La solicitud ya no está abierta. Revisá su estado antes de preparar el presupuesto.']);
            }
            $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->first();
            // La emisión de la empresa conserva la relación interna de cada gestor que genera presupuestos.
            if (!$profile && $request->user()->puedeGestionarSynaux()) {
                $organizacion = Organizacion::principal();
                if ($organizacion->tieneDatosDeEmision()) {
                    DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->firstOrFail();
                    $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->first();
                    if (!$profile) {
                        $perfilId = DB::table('professional_profiles')->insertGetId([
                            'user_id' => $request->user()->id, 'brand_name' => $organizacion->nombre,
                            'headline' => 'Gestión de proyectos digitales', 'bio' => 'Perfil interno de emisión de presupuestos.',
                            'availability_status' => 'unavailable', 'active' => false,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $profile = DB::table('professional_profiles')->find($perfilId);
                    }
                }
            }
            $proposal = $profile && $record ? DB::table('proposals')->where('project_request_id', $record->id)->where('professional_profile_id', $profile->id)->where('status', 'accepted')->first() : null;
            abort_unless($request->user()->puedeGestionarSynaux() || $proposal !== null, 403);
            if ($record?->user_id === $request->user()->id) {
                throw ValidationException::withMessages(['request_id' => 'No podés preparar un presupuesto para tu propia solicitud.']);
            }
            if (!$profile) {
                $mensaje = $request->user()->puedeGestionarSynaux()
                    ? 'Faltan los datos de emisión de synaux en Administración → Datos de la organización.'
                    : 'Completá tu perfil profesional antes de guardar el presupuesto.';
                throw ValidationException::withMessages(['emisor' => $mensaje]);
            }
            if (!empty($data['budget_id'])) {
                $budget = DB::table('budgets')->where('id', $data['budget_id'])->lockForUpdate()->firstOrFail();
                $origin = $budget->proposal_id ? DB::table('proposals')->where('id', $budget->proposal_id)->first() : null;
                // El editor solo puede continuar la misma relación y el mismo emisor.
                abort_unless(($budget->professional_profile_id ?? $origin?->professional_profile_id) === $profile->id
                    && ($budget->project_request_id ?? $origin?->project_request_id) === $record?->id
                    && (!$direct || ($budget->client_user_id === null && $budget->direct_service_id === null)), 403);
                $budgetId = $budget->id;
            } else {
                $budgetId = DB::table('budgets')->insertGetId(['proposal_id' => $proposal?->id,
                    'project_request_id' => $proposal ? null : $record?->id, 'professional_profile_id' => $proposal ? null : $profile->id,
                    'created_at' => now(), 'updated_at' => now()]);
            }
            if (DB::table('budget_versions')->where('budget_id', $budgetId)->where('status', 'accepted')->exists()) {
                throw ValidationException::withMessages(['budget_id' => 'Este presupuesto ya fue aceptado. Consultá el proyecto para continuar.']);
            }
            $draft = DB::table('budget_versions')->where('budget_id', $budgetId)->where('status', 'draft')->first();
            // El documento conserva las partes de esta versión aunque después cambien los perfiles.
            $client = $data['client'] ?? null;
            if ($record) {
                $owner = DB::table('users')->where('id', $record->user_id)->firstOrFail();
                $client = json_decode($record->contact_snapshot ?? 'null', true) ?? ['name' => $owner->name, 'email' => $owner->email];
            }
            $issuer = $request->user()->issuerDetails($profile->brand_name);
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

            // El cliente recibe el aviso únicamente cuando se envía la versión, nunca por borradores.
            if ($data['send'] && $record) {
                // Emitir en Gestión también incorpora clientes de solicitudes históricas.
                if ($request->user()->puedeGestionarSynaux()) {
                    DB::table('organizacion_clientes')->insertOrIgnore([
                        'organizacion_id' => Organizacion::principal()->id, 'user_id' => $record->user_id,
                    ]);
                }
                $this->notifications->send('presupuesto', $versionId, 'presupuesto-enviado:' . $versionId,
                    'Nuevo presupuesto', $data['title'], [$record->user_id], actor: $request->user()->id);
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
            if ($record && $record->status !== 'open') {
                throw ValidationException::withMessages(['decision' => 'La solicitud ya no está abierta. Actualizá la página para consultar su estado.']);
            }
            $latest = DB::table('budget_versions')->where('budget_id', $budget->id)->whereNotNull('sent_at')->max('version_number');
            if ($version->status !== 'sent' || $version->version_number !== (int)$latest || $version->valid_until < today()->toDateString()) {
                throw ValidationException::withMessages(['decision' => 'Esta versión ya no está vigente para decidir. Actualizá la página y revisá la última versión.']);
            }
            if (DB::table('budget_versions')->where('budget_id', $budget->id)->where('status', 'accepted')->exists()) {
                throw ValidationException::withMessages(['decision' => 'Este presupuesto ya tiene una versión aceptada. Consultá el proyecto.']);
            }
            DB::table('budget_versions')->where('id', $version->id)->update(['status' => $data['decision'], 'updated_at' => now()]);
            if ($data['decision'] !== 'accepted') {
                return null;
            }
            // La unicidad de accepted_budget_version_id y el bloqueo evitan crear proyectos duplicados.
            if ($record) {
                DB::table('project_requests')->where('id', $record->id)->update(['status' => 'closed', 'updated_at' => now()]);
            }

            $projectId = DB::table('projects')->insertGetId(['accepted_budget_version_id' => $version->id, 'title' => $version->title,
                'status' => 'pending', 'progress' => 0, 'created_at' => now(), 'updated_at' => now()]);
            // El proyecto creado avisa a sus partes y a gestión dentro de la misma transacción.
            $center = $this->notifications;
            $center->send('proyecto', $projectId, 'proyecto-creado:' . $projectId, 'Nuevo proyecto', $version->title,
                $center->projectRecipients($projectId), management: true, actor: $request->user()->id);

            return $projectId;
        });
    }
}
