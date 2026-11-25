<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Projects\ProjectQueries;
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
            ->when($search !== '', fn($query) => $query->where(fn($account) => $account
                ->whereLike('name', '%' . $search . '%')
                ->orWhereLike('email', '%' . $search . '%')))
            ->latest('id')
            ->paginate(20, ['id', 'name', 'email', 'is_admin', 'created_at'])
            ->appends($search !== '' ? ['buscar' => $search] : []);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'admin',
            'area' => 'gestion',
            'section' => 'usuarios',
            'users' => $users,
            'userSearch' => $search,
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    // Expone datos de la cuenta y una lista resumida; los proyectos se editan en el panel de Gestión.
    public function show(Request $request, User $user, ProjectQueries $projects)
    {
        $query = $request->query('buscar');
        $search = is_string($query) ? Str::substr(trim($query), 0, 120) : '';
        $directoryPage = max(1, $request->integer('pagina_usuarios', 1));
        $directoryFilters = array_filter([
            'buscar' => $search !== '' ? $search : null,
            'page' => $directoryPage > 1 ? $directoryPage : null,
        ], fn($value) => $value !== null);
        $projectFilters = array_filter([
            'buscar' => $directoryFilters['buscar'] ?? null,
            'pagina_usuarios' => $directoryFilters['page'] ?? null,
        ], fn($value) => $value !== null);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'admin',
            'area' => 'gestion',
            'section' => 'usuarios',
            'selectedUser' => $user->only('id', 'name', 'email', 'is_admin', 'created_at'),
            'clientProjects' => $projects->forClient($user->id)->appends($projectFilters),
            'userDirectoryUrl' => route('admin.users.index', $directoryFilters, absolute: false),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
