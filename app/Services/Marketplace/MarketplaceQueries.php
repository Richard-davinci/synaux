<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;

class MarketplaceQueries
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
}
