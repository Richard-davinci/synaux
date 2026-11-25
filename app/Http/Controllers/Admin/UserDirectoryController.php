<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserDirectoryController extends Controller
{
    // La ruta administrativa consulta solo los datos que muestra el listado y pagina en el servidor.
    public function __invoke(Request $request)
    {
        $query = $request->query('buscar');
        $search = is_string($query) ? Str::substr(trim($query), 0, 120) : '';

        $users = User::query()
            ->select(['id', 'name', 'email', 'is_admin', 'created_at'])
            ->selectRaw('exists (select 1 from professional_profiles where professional_profiles.user_id = users.id) as is_professional')
            ->withCasts(['is_professional' => 'boolean'])
            ->when($search !== '', fn($query) => $query->where(fn($account) => $account
                ->whereLike('name', '%' . $search . '%')
                ->orWhereLike('email', '%' . $search . '%')))
            ->latest('id')
            ->paginate(20)
            ->appends($search !== '' ? ['buscar' => $search] : []);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'admin',
            'area' => 'gestion',
            'section' => 'usuarios',
            'users' => $users,
            'userSearch' => $search,
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
