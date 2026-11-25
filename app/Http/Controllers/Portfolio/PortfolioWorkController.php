<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Portfolio\GaleriaTrabajos;
use App\Services\Portfolio\PortfolioQueries;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

class PortfolioWorkController extends Controller
{
    // Protege el editor por propiedad y carga el trabajo elegido junto a los servicios disponibles.
    public function edit(Request $request, PortfolioQueries $portfolioQueries, GaleriaTrabajos $gallery, ServiceCatalog $catalog, int $id = 0)
    {
        $user = $request->user();
        $selected = $id ? ($portfolioQueries->works($user->id, id: $id)[0] ?? abort(404)) : null;
        $profile = DB::table('professional_profiles')->where('user_id', $user->id)->first();
        abort_unless($profile, 403, 'Completá tu perfil profesional.');

        $services = $catalog->services($user->puedeGestionarSynaux());
        // El autor puede conservar el servicio original si dejó de estar activo en el catálogo.
        if ($selected && !$user->puedeGestionarSynaux() && !in_array($selected['service_id'], array_column($services, 'database_id'), true)) {
            $historical = collect($catalog->services(true))->firstWhere('database_id', $selected['service_id']);
            if ($historical) $services[] = $historical;
        }

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'editor-trabajo',
            'area' => $user->puedeGestionarSynaux() ? 'gestion' : 'cliente',
            'selected' => $selected,
            'galleryRevision' => $selected ? $gallery->revision($selected['images']) : null,
            'services' => $services,
        ]);
    }

    // Guarda el trabajo y la edición de su galería en una sola transacción de base de datos.
    public function store(Request $request, GaleriaTrabajos $gallery)
    {
        $imageRules = 'image|mimes:jpg,jpeg,png,webp|max:5120';
        $d = $request->validate([
            'id' => 'nullable|integer', 'service_id' => 'required|integer|exists:services,id', 'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000', 'challenge' => 'nullable|string|max:10000',
            'result' => 'nullable|string|max:10000', 'year' => 'nullable|integer|min:1990|max:2100', 'is_public' => 'required|boolean',
            'live_url' => 'nullable|url:http,https|max:2048', 'cover' => 'nullable|' . $imageRules,
            'gallery' => 'nullable|array|max:8', 'gallery.*' => $imageRules,
            'cover_choice' => ['nullable', 'regex:/^(?:current|none|saved:[1-9][0-9]*|new:[0-9]+)$/D'],
            'gallery_items' => 'nullable|json|max:250000', 'gallery_revision' => 'required_with:gallery_items|nullable|string|size:64',
            'gallery_replacements' => 'nullable|array|max:8', 'gallery_replacements.*' => $imageRules,
        ]);
        $items = isset($d['gallery_items']) ? Validator::make(
            ['gallery_items' => json_decode($d['gallery_items'], true)],
            ['gallery_items' => 'present|array|list', 'gallery_items.*.id' => 'required|integer|distinct',
                'gallery_items.*.alt_text' => 'nullable|string|max:255'],
        )->validate()['gallery_items'] : null;
        if (($items !== null && empty($d['id'])) || ($items === null && !empty($d['gallery_replacements']))) {
            throw ValidationException::withMessages(['gallery_items' => 'La edición de galería requiere un trabajo existente.']);
        }

        $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->firstOrFail();
        $values = collect($d)->except(['id', 'cover', 'gallery', 'gallery_items', 'gallery_revision', 'gallery_replacements', 'cover_choice'])->all();
        // La categoría se obtiene del catálogo y no depende de un valor enviado por el formulario.
        $values['category'] = DB::table('services as service')
            ->join('service_categories as category', 'category.id', '=', 'service.service_category_id')
            ->where('service.id', $d['service_id'])->value('category.name');
        $uploaded = [];

        try {
            $id = DB::transaction(function () use ($d, $request, $gallery, $items, $profile, $values, &$uploaded) {
                // El bloqueo evita pisar cambios de otra pestaña y la revisión protege la selección cargada por el editor.
                $work = !empty($d['id']) ? DB::table('portfolio_projects')->where('id', $d['id'])
                    ->where('professional_profile_id', $profile->id)->lockForUpdate()->firstOrFail() : null;
                $replacementFiles = $request->file('gallery_replacements', []);
                if ($items !== null) {
                    $current = $gallery->check($work->id, $items, $d['gallery_revision'], $replacementFiles);
                    $replacements = [];
                    foreach ($replacementFiles as $imageId => $file) {
                        $replacements[$imageId] = $this->saveImage($file, $uploaded);
                    }
                    $gallery->apply($work->id, $items, $current, $replacements);
                }

                if ($request->hasFile('cover')) {
                    $values['cover_path'] = $this->saveImage($request->file('cover'), $uploaded);
                }

                if ($work) {
                    DB::table('portfolio_projects')->where('id', $work->id)->update([...$values, 'updated_at' => now()]);
                    $id = $work->id;
                } else {
                    $id = DB::table('portfolio_projects')->insertGetId([...$values, 'professional_profile_id' => $profile->id,
                        'created_at' => now(), 'updated_at' => now()]);
                }

                $order = (DB::table('portfolio_project_images')->where('portfolio_project_id', $id)->max('sort_order') ?? -1) + 1;
                $newImages = [];
                foreach ($request->file('gallery', []) as $index => $image) {
                    $newImages[$index] = $this->saveImage($image, $uploaded);
                    DB::table('portfolio_project_images')->insert([
                        'portfolio_project_id' => $id, 'image_path' => $newImages[$index],
                        'alt_text' => $values['title'], 'sort_order' => $order++,
                    ]);
                }

                $coverChoice = $d['cover_choice'] ?? 'current';
                if ($coverChoice !== 'current') {
                    DB::table('portfolio_projects')->where('id', $id)->update([
                        'cover_path' => $this->coverPath($id, $coverChoice, $newImages),
                    ]);
                }

                return $id;
            });
        } catch (Throwable $error) {
            Storage::disk('public')->delete($uploaded);
            throw $error;
        }

        return redirect('/editor/trabajo/' . $id)->with('notice', 'Trabajo guardado.');
    }

    // Resuelve la portada dentro del trabajo autorizado y reutiliza el archivo de la galería.
    private function coverPath(int $work, string $choice, array $newImages): ?string
    {
        if ($choice === 'none') {
            return null;
        }

        [$source, $reference] = explode(':', $choice, 2);
        $path = $source === 'new'
            ? ($newImages[$reference] ?? null)
            : DB::table('portfolio_project_images')->where('portfolio_project_id', $work)->where('id', $reference)->value('image_path');
        if (!$path) {
            throw ValidationException::withMessages(['cover_choice' => 'Elegí una imagen disponible para la portada.']);
        }

        return $path;
    }

    // Registra los archivos nuevos para retirarlos si la transacción no se completa.
    private function saveImage(UploadedFile $image, array &$uploaded): string
    {
        $path = $image->store('portfolio', 'public');
        if (!$path) {
            throw new RuntimeException('No se pudo guardar una imagen del portfolio.');
        }

        $uploaded[] = $path;

        return '/media/' . $path;
    }

    // Entrega una imagen solo si el trabajo es público o pertenece a la cuenta que la solicita.
    public function media(Request $request, PortfolioQueries $portfolio, string $file)
    {
        // Se resuelve un nombre generado por Laravel; la consulta determina si la imagen es pública o propia.
        abort_unless(preg_match('/^[a-zA-Z0-9]+\.(jpg|jpeg|png|webp)$/i', $file), 404);
        $path = '/media/portfolio/' . $file;
        abort_unless($portfolio->canReadImage($path, $request->user()?->id), 404);
        abort_unless(Storage::disk('public')->exists('portfolio/' . $file), 404);

        return Storage::disk('public')->response('portfolio/' . $file, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
