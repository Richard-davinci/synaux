<?php

namespace App\Services\Portfolio;

use Illuminate\Support\Facades\DB;

class PortfolioQueries
{
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
}
