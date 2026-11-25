<?php

namespace App\Services\Portfolio;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PortfolioQueries
{
    // Entrega trabajos públicos o del propietario indicado y permite acotar una selección reciente.
    public function works(?int $owner = null, ?int $id = null, ?int $limit = null): array
    {
        return $this->projects()
            ->when($owner === null, fn($q) => $this->published($q))
            ->when($owner !== null, fn($q) => $q->where('p.user_id', $owner))
            ->when($id !== null, fn($q) => $q->where('w.id', $id))
            ->when($limit !== null, fn($q) => $q->limit($limit))
            ->leftJoin('services as service', 'service.id', '=', 'w.service_id')
            ->leftJoin('service_categories as category', 'category.id', '=', 'service.service_category_id')
            ->select('w.*', 'p.brand_name', 'category.name as catalog_category')->orderByDesc('w.id')->get()->map(fn($w) => [
                // La ficha refleja el catálogo actual y conserva el rótulo de trabajos sin servicio asociado.
                ...((array)$w), 'category' => $w->catalog_category ?? $w->category, 'images' => DB::table('portfolio_project_images')->where('portfolio_project_id', $w->id)->orderBy('sort_order')->get(),
            ])->all();
    }

    // Aplica la misma visibilidad a la portada y la galería; su propietario también puede abrirlas en privado.
    public function canReadImage(string $path, ?int $owner): bool
    {
        return $this->projects()
            ->where(fn($q) => $q->where('w.cover_path', $path)
                ->orWhereExists(fn($sub) => $sub->selectRaw('1')->from('portfolio_project_images as i')
                    ->whereColumn('i.portfolio_project_id', 'w.id')->where('i.image_path', $path)))
            ->where(fn($q) => $q->where(fn($public) => $this->published($public))
                ->when($owner !== null, fn($scope) => $scope->orWhere('p.user_id', $owner)))
            ->exists();
    }

    // El perfil interno de un administrador puede publicar trabajos de synaux desde Gestión.
    private function published(Builder $query): Builder
    {
        return $query->where('w.is_public', 1)
            ->where(fn($q) => $q->where('p.active', 1)->orWhere('account.is_admin', 1));
    }

    private function projects(): Builder
    {
        return DB::table('portfolio_projects as w')
            ->join('professional_profiles as p', 'p.id', '=', 'w.professional_profile_id')
            ->join('users as account', 'account.id', '=', 'p.user_id');
    }
}
