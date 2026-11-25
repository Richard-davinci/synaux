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
            ->select('id', 'name', 'email', 'is_admin', 'created_at', 'activacion_pendiente')
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

    // El alta queda pendiente de activación. La membresía de una cuenta existente se actualiza por correo.
    public function guardarMiembro(Request $request)
    {
        $data = $request->validate([
            'modo' => ['required', Rule::in(['existente', 'nueva'])],
            'email' => 'required|email|max:254',
            'name' => 'exclude_unless:modo,nueva|required|string|max:255',
            'rol' => ['required', Rule::in(['administrador', 'supervisor', 'profesional'])],
        ]);

        DB::transaction(function () use ($data) {
            $correo = Str::lower($data['email']);
            $usuario = User::query()->where('email', $correo)->first();
            if ($data['modo'] === 'nueva') {
                if ($usuario) {
                    throw ValidationException::withMessages(['email' => 'Ese correo ya tiene una cuenta. Elegí Cuenta existente.']);
                }
                $usuario = User::unguarded(fn () => User::query()->createOrFirst(['email' => $correo], [
                    'name' => $data['name'], 'password' => Str::random(64), 'activacion_pendiente' => true,
                ]));
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

        if ($data['modo'] === 'nueva') {
            return redirect()->route('admin.users.index', ['buscar' => Str::lower($data['email'])])
                ->with('notice', 'Cuenta creada. Usá Compartir para entregar el enlace de activación.');
        }

        return back()->with('notice', 'Guardamos el rol en el equipo de synaux.');
    }

    // Solo un alta pendiente del espacio puede recibir un enlace. Compartir uno nuevo revoca el anterior.
    public function compartirActivacion(User $user)
    {
        $organizacion = Organizacion::principal();
        $token = bin2hex(random_bytes(32));
        $actualizadas = User::query()->whereKey($user->id)->where('activacion_pendiente', true)
            ->whereExists(DB::table('organizacion_miembros')->selectRaw('1')
                ->where('organizacion_id', $organizacion->id)->whereColumn('user_id', 'users.id'))
            ->update([
                'activacion_hash' => hash('sha256', $token),
                'activacion_vence' => now()->addHours(48),
            ]);
        abort_unless($actualizadas, 404, 'Esta cuenta ya no está pendiente de activación.');

        return response()->json([
            'enlace' => rtrim(config('app.url'), '/') . route('cuenta.activar', $token, absolute: false),
        ])->header('Cache-Control', 'private, no-store');
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
            'selectedUser' => $user->only('id', 'name', 'email', 'is_admin', 'created_at', 'activacion_pendiente'),
            'clientProjects' => $projects->forClient($user->id)->appends($projectFilters),
            'userDirectoryUrl' => route('admin.users.index', $directoryFilters, absolute: false),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
