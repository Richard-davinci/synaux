<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Prepara datos de lectura sin exponer correos ni campos privados en el muro o el directorio.
class SynauxWorkspace
{
    // Devuelve los perfiles visibles en el directorio público sin incluir correos privados.
    public function professionals(): array
    {
        return DB::table('professional_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.active', 1)->select('p.*', 'u.name')->orderBy('p.id')->get()->map(fn ($p) => $this->profile($p))->all();
    }

    // Selecciona los campos de presentación de un perfil y decodifica sus habilidades.
    public function profile(object $p): array
    {
        return ['id' => $p->id, 'name' => $p->name ?? $p->brand_name, 'brand_name' => $p->brand_name,
            'headline' => $p->headline, 'bio' => $p->bio, 'location' => $p->location,
            'skills' => json_decode($p->skills ?? '[]', true), 'active' => (bool) $p->active,
            'availability_status' => $p->availability_status];
    }

    // Entrega trabajos públicos o los del propietario indicado, con sus imágenes ordenadas.
    public function works(?int $owner = null, ?int $id = null): array
    {
        return DB::table('portfolio_projects as w')->join('professional_profiles as p', 'p.id', '=', 'w.professional_profile_id')
            ->when($owner === null, fn ($q) => $q->where('w.is_public', 1)->where('p.active', 1))
            ->when($owner !== null, fn ($q) => $q->where('p.user_id', $owner))
            ->when($id !== null, fn ($q) => $q->where('w.id', $id))
            ->select('w.*', 'p.brand_name')->orderByDesc('w.id')->get()->map(fn ($w) => [
                ...((array) $w), 'images' => DB::table('portfolio_project_images')->where('portfolio_project_id', $w->id)->orderBy('sort_order')->get(),
            ])->all();
    }

    // Filtra solicitudes por propiedad, publicación o permisos internos antes de preparar sus datos.
    public function requests(?int $owner, bool $management = false, bool $public = false, ?array $only = null): array
    {
        return DB::table('project_requests as r')->leftJoin('services as s', 's.id', '=', 'r.service_id')
            ->leftJoin('estimates as e', fn ($join) => $join->on('e.id', '=', 'r.estimate_id')
                ->on('e.user_id', '=', 'r.user_id')->on('e.service_id', '=', 'r.service_id'))
            ->join('users as contact_owner', 'contact_owner.id', '=', 'r.user_id')
            ->when($only !== null, fn ($q) => $q->whereIn('r.id', $only))
            ->when($public, fn ($q) => $q->where('r.visibility', 'public')->where('r.status', 'open'))
            ->when(! $public && ! $management, fn ($q) => $q->where('r.user_id', $owner))
            // Los borradores pertenecen al cliente hasta que se envía el pedido.
            ->when($management, fn ($q) => $q->where(fn ($drafts) => $drafts->where('r.status', '!=', 'draft')->orWhere('r.user_id', $owner)))
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

    // Resuelve los dos orígenes del presupuesto y filtra versiones según cliente, emisor y estado.
    public function quotes(?int $userId, bool $management = false, ?int $id = null, ?int $budgetId = null): array
    {
        // Los dos orígenes del DER (solicitud directa o propuesta elegida) se resuelven antes de autorizar.
        return DB::table('budget_versions as v')->join('budgets as b', 'b.id', '=', 'v.budget_id')
            ->leftJoin('proposals as p', 'p.id', '=', 'b.proposal_id')
            ->leftJoin('project_requests as r', fn ($j) => $j->on('r.id', '=', DB::raw('COALESCE(b.project_request_id, p.project_request_id)')))
            ->leftJoin('professional_profiles as pro', fn ($j) => $j->on('pro.id', '=', DB::raw('COALESCE(b.professional_profile_id, p.professional_profile_id)')))
            ->when(! $management, fn ($q) => $q->where(fn ($w) => $w->where('r.user_id', $userId)->orWhere('pro.user_id', $userId)->orWhere('b.client_user_id', $userId)))
            ->when($id !== null, fn ($q) => $q->where('v.id', $id))
            ->when($budgetId !== null, fn ($q) => $q->where('v.budget_id', $budgetId))
            ->select('v.*', 'r.id as request_id', 'r.title as request_title', 'r.user_id as request_owner', 'pro.user_id as issuer_id', 'b.client_user_id')
            ->orderByDesc('v.id')->get()->filter(fn ($v) => $management || $v->issuer_id === $userId || $v->status !== 'draft')
            ->map(function ($v) {
                $items = DB::table('budget_items')->where('budget_version_id', $v->id)->get();
                $subtotal = $items->sum(fn ($i) => (int) round($i->quantity * $i->unit_price * 100));
                $total = (int) round($subtotal * (1 + $v->tax_rate / 100));
                $latestSent = DB::table('budget_versions')->where('budget_id', $v->budget_id)->whereNotNull('sent_at')->max('version_number');

                // Los documentos emitidos para contactos externos se comparten como PDF.
                $external = $v->request_id === null && $v->client_user_id === null;

                return [...((array) $v), 'external' => $external, 'client' => json_decode($v->client_snapshot ?? 'null', true), 'issuer' => json_decode($v->issuer_snapshot ?? 'null', true), 'items' => $items, 'subtotal' => $subtotal / 100, 'total' => $total / 100,
                    'current' => (int) $latestSent === (int) $v->version_number,
                    'display_status' => $v->status === 'sent' && $v->valid_until < today()->toDateString() ? 'expired' : ($external && $v->status === 'sent' ? 'issued' : $v->status)];
            })->values()->all();
    }

    // Consulta solo propuestas recibidas o enviadas por la cuenta, salvo la lectura administrativa.
    public function proposals(?int $userId, bool $management = false): array
    {
        return DB::table('proposals as p')->join('professional_profiles as pro', 'pro.id', '=', 'p.professional_profile_id')
            ->join('project_requests as r', 'r.id', '=', 'p.project_request_id')
            ->when(! $management, fn ($q) => $q->where(fn ($w) => $w->where('r.user_id', $userId)->orWhere('pro.user_id', $userId)))
            ->select('p.*', 'pro.brand_name as name', 'pro.headline', 'pro.skills', 'pro.user_id as professional_user_id', 'r.title', 'r.user_id as owner_id')
            ->orderByDesc('p.id')->get()->map(fn ($p) => [...((array) $p), 'skills' => json_decode($p->skills ?? '[]', true)])->all();
    }

    // Consulta invitaciones de la cuenta como cliente o destinatario sin exponer otras invitaciones.
    public function invitations(?int $userId, bool $management = false): array
    {
        return DB::table('project_request_invitations as i')->join('professional_profiles as pro', 'pro.id', '=', 'i.professional_profile_id')
            ->join('project_requests as r', 'r.id', '=', 'i.project_request_id')
            ->when(! $management, fn ($q) => $q->where(fn ($w) => $w->where('r.user_id', $userId)->orWhere('pro.user_id', $userId)))
            ->select('i.*', 'pro.brand_name as name', 'pro.user_id as professional_user_id', 'r.title', 'r.user_id as owner_id', 'r.status as request_status')
            ->orderByDesc('i.id')->get()->map(fn ($i) => (array) $i)->all();
    }

    // Devuelve los proyectos asociados a versiones de presupuesto que la cuenta puede consultar.
    public function projects(array $quotes, ?int $id = null): array
    {
        $projects = DB::table('projects')->whereIn('accepted_budget_version_id', array_column($quotes, 'id'))
            ->when($id !== null, fn ($query) => $query->where('id', $id))->orderByDesc('id')->get();
        // El historial se consulta solo para los proyectos autorizados y no expone datos de la cuenta del gestor.
        $updates = DB::table('project_updates')->whereIn('project_id', $projects->pluck('id'))
            ->orderByDesc('id')->get(['id', 'project_id', 'status', 'progress', 'start_date', 'end_date', 'notes', 'next_steps', 'created_at'])->groupBy('project_id');
        return $projects->map(fn ($project) => [
            ...((array) $project), 'updates' => ($updates[$project->id] ?? collect())->map(fn ($update) => (array) $update)->all(),
        ])->all();
    }
}
