<?php

namespace App\Services\Portfolio;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortfolioGallery
{
    // La revisión identifica los registros que el editor abrió, incluidos su orden y archivos.
    public function revision(Collection $images): string
    {
        return hash('sha256', json_encode($images->values()->all(), JSON_THROW_ON_ERROR));
    }

    // Valida que la edición siga vigente y que cada imagen pertenezca al trabajo.
    public function check(int $work, array $items, string $revision, array $replacements): Collection
    {
        $current = $this->images($work)->orderBy('sort_order')->get();
        $ids = array_column($items, 'id');
        if ($revision !== $this->revision($current) || array_diff($ids, $current->pluck('id')->all())
            || array_diff(array_keys($replacements), $ids)) {
            throw ValidationException::withMessages([
                'gallery_items' => 'La galería cambió desde que la abriste. Recargá la página para revisar las imágenes antes de volver a guardar.',
            ]);
        }

        return $current;
    }

    // Reserva posiciones temporales para cambiar el orden sin duplicar índices dentro del trabajo.
    public function apply(int $work, array $items, Collection $current, array $replacements): void
    {
        $gallery = $this->images($work);
        (clone $gallery)->whereNotIn('id', array_column($items, 'id'))->delete();
        $gallery->increment('sort_order', (int)$current->max('sort_order') + count($items) + 1);

        foreach ($items as $index => $item) {
            $changes = ['alt_text' => $item['alt_text'] ?? '', 'sort_order' => $index];
            if (isset($replacements[$item['id']])) {
                $changes['image_path'] = $replacements[$item['id']];
            }
            (clone $gallery)->where('id', $item['id'])->update($changes);
        }
    }

    // Las operaciones de galería siempre parten de un trabajo autorizado por el controlador.
    private function images(int $work)
    {
        return DB::table('portfolio_project_images')->where('portfolio_project_id', $work);
    }
}
