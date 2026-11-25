<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
use App\Models\User;
use App\Services\Projects\ProjectQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DirectorioUsuariosController extends Controller
{
    // La ruta administrativa consulta solo los datos que muestra el listado y pagina en el servidor.
    public function __invoke(Request $request)
    {
        $query = $request->query('buscar');
        $search = is_string($query) ? Str::substr(trim($query), 0, 120) : '';

        // El rol de equipo se consulta solo para el espacio inicial de la organización.
        $organizacion = Organizacion::principal();
        $users = User::query()
            ->when($search !== '', fn($query) => $query->where(fn($account) => $account
                ->whereLike('name', '%' . $search . '%')
                ->orWhereLike('email', '%' . $search . '%')))
            ->select('id', 'name', 'email', 'is_admin', 'created_at')
            ->selectSub(DB::table('organizacion_miembros')->select('rol')
                ->where('organizacion_id', $organizacion->id)->whereColumn('user_id', 'users.id'), 'rol_organizacion')
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

    // Una cuenta existente se incorpora por correo. Una cuenta nueva recibe su propia contraseña.
    public function guardarMiembro(Request $request)
    {
        $data = $request->validate([
            'modo' => ['required', Rule::in(['existente', 'nueva'])],
            'email' => 'required|email|max:254',
            'name' => 'exclude_unless:modo,nueva|required|string|max:255',
            'password' => 'exclude_unless:modo,nueva|required|string|min:12|max:72',
            'rol' => ['required', Rule::in(['administrador', 'supervisor', 'profesional'])],
        ]);

        DB::transaction(function () use ($data) {
            $correo = Str::lower($data['email']);
            $usuario = User::query()->where('email', $correo)->first();
            if ($data['modo'] === 'nueva') {
                if ($usuario) {
                    throw ValidationException::withMessages(['email' => 'Ese correo ya tiene una cuenta. Elegí Cuenta existente.']);
                }
                $usuario = User::query()->createOrFirst(['email' => $correo], [
                    'name' => $data['name'], 'password' => $data['password'],
                ]);
                if (!$usuario->wasRecentlyCreated) {
                    throw ValidationException::withMessages(['email' => 'Ese correo ya tiene una cuenta. Elegí Cuenta existente.']);
                }
            } elseif (!$usuario) {
                throw ValidationException::withMessages(['email' => 'No encontramos una cuenta con ese correo.']);
            }

            // La membresía no modifica el permiso global de Administración ni las credenciales existentes.
            if ($usuario->is_admin && $data['rol'] !== 'administrador') {
                throw ValidationException::withMessages(['rol' => 'La cuenta administra la plataforma y debe conservar el rol Administrador.']);
            }
            DB::table('organizacion_miembros')->upsert([
                ['organizacion_id' => Organizacion::principal()->id, 'user_id' => $usuario->id, 'rol' => $data['rol']],
            ], ['organizacion_id', 'user_id'], ['rol']);
        });

        return back()->with('notice', 'Guardamos la cuenta y su rol en el equipo de synaux.');
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
