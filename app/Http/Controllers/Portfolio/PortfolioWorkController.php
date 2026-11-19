<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Portfolio\PortfolioQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortfolioWorkController extends Controller
{
    // Protege el editor por propiedad y carga el trabajo elegido junto a los servicios disponibles.
    public function edit(Request $request, PortfolioQueries $portfolioQueries, ServiceCatalog $catalog, int $id = 0)
    {
        $user = $request->user();
        $selected = $id ? ($portfolioQueries->works($user->id, id: $id)[0] ?? abort(404)) : null;
        $profile = DB::table('professional_profiles')->where('user_id', $user->id)->first();
        abort_unless($profile, 403, 'Completá tu perfil profesional.');

        return Inertia::render('Workspace', [
            'screen' => 'editor-trabajo',
            'area' => $user->is_admin ? 'gestion' : 'cliente',
            'selected' => $selected,
            'services' => $catalog->services((bool) $user->is_admin),
        ]);
    }

    // Guarda un trabajo del perfil propio y controla formato y tamaño de sus imágenes.
    public function store(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|integer', 'service_id' => 'required|integer|exists:services,id', 'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000', 'category' => 'required|string|max:255', 'challenge' => 'nullable|string|max:10000', 'result' => 'nullable|string|max:10000',
            'year' => 'nullable|integer|min:1990|max:2100', 'is_public' => 'required|boolean', 'live_url' => 'nullable|url:http,https|max:2048',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', 'gallery' => 'nullable|array|max:8', 'gallery.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120']);
        $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->firstOrFail();
        $old = ! empty($d['id']) ? DB::table('portfolio_projects')->where('id', $d['id'])->where('professional_profile_id', $profile->id)->firstOrFail() : null;
        // Los archivos se limitan a imágenes raster; se guardan con nombres generados por Laravel.
        $values = collect($d)->except(['id', 'cover', 'gallery'])->all();
        if ($request->hasFile('cover')) {
            $values['cover_path'] = '/media/'.$request->file('cover')->store('portfolio', 'public');
        }
        $id = DB::transaction(function () use ($request, $profile, $old, $values) {
            if ($old) {
                DB::table('portfolio_projects')->where('id', $old->id)->update([...$values, 'updated_at' => now()]);
                $id = $old->id;
            } else {
                $id = DB::table('portfolio_projects')->insertGetId([...$values, 'professional_profile_id' => $profile->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $order = (DB::table('portfolio_project_images')->where('portfolio_project_id', $id)->max('sort_order') ?? -1) + 1;
            foreach ($request->file('gallery', []) as $image) {
                DB::table('portfolio_project_images')->insert(['portfolio_project_id' => $id, 'image_path' => '/media/'.$image->store('portfolio', 'public'), 'alt_text' => $values['title'], 'sort_order' => $order++]);
            }

            return $id;
        });

        return redirect('/editor/trabajo/'.$id)->with('notice', 'Trabajo guardado.');
    }

    // Entrega una imagen solo si el trabajo es público o pertenece a la cuenta que la solicita.
    public function media(Request $request, string $file)
    {
        // Se resuelve un nombre generado por Laravel; la consulta determina si la imagen es pública o propia.
        abort_unless(preg_match('/^[a-zA-Z0-9]+\.(jpg|jpeg|png|webp)$/i', $file), 404);
        $path = '/media/portfolio/'.$file;
        $work = DB::table('portfolio_projects as w')->join('professional_profiles as p', 'p.id', '=', 'w.professional_profile_id')
            ->where(fn ($q) => $q->where('w.cover_path', $path)->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('portfolio_project_images as i')->whereColumn('i.portfolio_project_id', 'w.id')->where('i.image_path', $path)))
            ->select('w.is_public', 'p.active', 'p.user_id')->first();
        abort_unless($work && (($work->is_public && $work->active) || $request->user()?->id === $work->user_id), 404);
        abort_unless(Storage::disk('public')->exists('portfolio/'.$file), 404);

        return Storage::disk('public')->response('portfolio/'.$file, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
