<?php

namespace App\Services\Solicitudes;

use Illuminate\Support\Facades\DB;

class ConsultasSolicitudes
{
    // Filtra solicitudes por propiedad, publicación o permisos internos antes de preparar sus datos.
    public function requests(?int $owner, bool $management = false, bool $public = false, ?array $only = null): array
    {
        return DB::table('project_requests as r')->leftJoin('services as s', 's.id', '=', 'r.service_id')
            ->leftJoin('estimates as e', fn($join) => $join->on('e.id', '=', 'r.estimate_id')
                ->on('e.user_id', '=', 'r.user_id')->on('e.service_id', '=', 'r.service_id'))
            ->join('users as contact_owner', 'contact_owner.id', '=', 'r.user_id')
            ->when($only !== null, fn($q) => $q->whereIn('r.id', $only))
            ->when($public, fn($q) => $q->where('r.visibility', 'public')->where('r.status', 'open'))
            ->when(!$public && !$management, fn($q) => $q->where('r.user_id', $owner))
            // Los borradores pertenecen al cliente hasta que se envía el pedido.
            ->when($management, fn($q) => $q->where(fn($drafts) => $drafts->where('r.status', '!=', 'draft')->orWhere('r.user_id', $owner)))
            ->orderByDesc('r.created_at')->select('r.*', 's.name as service_name', 's.slug as service_slug', 'contact_owner.name as contact_name', 'contact_owner.email as contact_email',
                'e.price_min as estimate_min', 'e.price_max as estimate_max', 'e.currency_snapshot as estimate_currency', 'e.tariff_version_id',
                'e.base_min_snapshot', 'e.base_max_snapshot', 'e.items_snapshot as estimate_items')->get()->map(function ($r) use ($public) {
                $base = ['id' => $r->id, 'title' => $r->title, 'description' => $r->description,
                    'service_id' => $r->service_id, 'service_name' => $r->service_name, 'service_slug' => $r->service_slug,
                    'visibility' => $r->visibility, 'status' => $r->status, 'category' => $r->category,
                    'skills' => json_decode($r->skills ?? '[]', true), 'budget_reference' => $r->budget_reference,
                    'desired_deadline' => $r->desired_deadline, 'published_at' => $r->published_at, 'created_at' => $r->created_at,
                    'proposal_count' => DB::table('proposals')->where('project_request_id', $r->id)->count()];

                return $public ? $base : [...$base, 'user_id' => $r->user_id, 'estimate_id' => $r->estimate_id,
                    'pricing_reference' => $r->estimate_min === null ? null : [
                        'min' => $r->estimate_min, 'max' => $r->estimate_max ?? $r->estimate_min,
                        'currency' => $r->estimate_currency,
                    ],
                    'tariff_version_id' => $r->tariff_version_id,
                    'pricing_breakdown' => $r->base_min_snapshot === null ? null : [
                        'base_min' => $r->base_min_snapshot, 'base_max' => $r->base_max_snapshot,
                        'items' => json_decode($r->estimate_items ?? '[]', true),
                    ],
                    // Las solicitudes antiguas sin instantánea usan el mismo contacto que el documento formal.
                    'contact' => json_decode($r->contact_snapshot ?? 'null', true) ?? ['name' => $r->contact_name, 'email' => $r->contact_email], 'additional_services' => json_decode($r->requested_services ?? '[]', true),
                    'reviewed_at' => $r->reviewed_at, 'answers' => json_decode($r->answers_snapshot ?? '[]', true)];
            })->all();
    }
}
