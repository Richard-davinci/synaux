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
    private const ROLES = ['administrador', 'supervisor', 'profesional'];

    // Administración lista solo miembros. Gestión abre su cartera por una ruta propia.
    public function __invoke(Request $request)
    {
        return $this->listado($request, equipo: true);
    }

    public function clientes(Request $request)
    {
        return $this->listado($request, equipo: false);
    }

    private function listado(Request $request, bool $equipo)
    {
        $organizacion = Organizacion::principal();
        $search = $this->busqueda($request);
        $usuarios = $equipo ? $organizacion->miembros() : $organizacion->clientes();
        $users = $usuarios
            ->when($search !== '', fn ($query) => $query->where(fn ($account) => $account
                ->whereLike('users.name', '%' . $search . '%')
                ->orWhereLike('users.email', '%' . $search . '%')))
            ->latest('users.id')
            ->paginate(20, ['users.id', 'users.name', 'users.email', 'users.is_admin', 'users.created_at', 'users.activacion_pendiente'])
            ->appends($search !== '' ? ['buscar' => $search] : []);

        // Solo se exponen los campos de la tabla, sin las claves de la relación.
        $users->through(fn (User $usuario) => [
            ...$usuario->only('id', 'name', 'email', 'is_admin', 'created_at', 'activacion_pendiente'),
            'rol_organizacion' => $equipo ? $usuario->pivot->rol : null,
        ]);

        return Inertia::render('EspacioTrabajo', [
            'screen' => $equipo ? 'admin' : 'gestion',
            'area' => 'gestion',
            'section' => $equipo ? 'usuarios' : 'clientes',
            'users' => $users,
            'userSearch' => $search,
            'dominioEquipo' => $equipo ? $organizacion->dominio_correo_equipo : null,
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    // Un miembro nuevo siempre recibe una cuenta corporativa independiente.
    public function guardarMiembro(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:254',
            'rol' => ['required', Rule::in(self::ROLES)],
        ]);
        $correo = Str::lower($data['email']);

        DB::transaction(function () use ($data, $correo) {
            $organizacion = Organizacion::principal();
            if (!$organizacion->dominio_correo_equipo || Str::afterLast($correo, '@') !== $organizacion->dominio_correo_equipo) {
                throw ValidationException::withMessages(['email' => $organizacion->dominio_correo_equipo
                    ? 'Usá una cuenta con el dominio @' . $organizacion->dominio_correo_equipo . '.'
                    : 'Configurá primero el dominio de correo del equipo en Datos de la organización.']);
            }

            $usuario = User::unguarded(fn () => User::query()->createOrFirst(['email' => $correo], [
                'name' => $data['name'], 'password' => Str::random(64), 'activacion_pendiente' => true,
            ]));
            if (!$usuario->wasRecentlyCreated) {
                throw ValidationException::withMessages(['email' => 'Ese correo ya tiene una cuenta. Creá el miembro con otro correo de la empresa.']);
            }
            $organizacion->miembros()->attach($usuario->id, ['rol' => $data['rol']]);
        });

        return redirect()->route('admin.users.index', ['buscar' => $correo])
            ->with('notice', 'Cuenta creada. Usá Compartir para entregar el enlace de activación.');
    }

    // El identificador de la URL fija la cuenta. El correo y los datos personales quedan fuera de esta operación.
    public function cambiarRol(Request $request, User $user)
    {
        $data = $request->validate([
            'rol' => ['required', Rule::in(self::ROLES)],
            'email' => 'prohibited',
        ]);
        $miembros = Organizacion::principal()->miembros();
        abort_if($user->is_admin, 403);
        abort_unless($miembros->whereKey($user->id)->exists(), 404);
        $miembros->updateExistingPivot($user->id, ['rol' => $data['rol']]);

        return back()->with('notice', 'Actualizamos el rol en el equipo.');
    }

    // Quita la membresía y revoca cualquier enlace pendiente. La cuenta queda disponible para su historial.
    public function quitarMiembro(User $user)
    {
        DB::transaction(function () use ($user) {
            $cuenta = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($cuenta->is_admin, 403);
            abort_unless(Organizacion::principal()->miembros()->detach($cuenta->id), 404);
            $cuenta->forceFill(['activacion_hash' => null, 'activacion_vence' => null])->save();
        });

        return back()->with('notice', 'Quitamos la cuenta del equipo.');
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

    // La ficha y sus proyectos se entregan únicamente para clientes vinculados a la organización.
    public function show(Request $request, User $user, ProjectQueries $projects)
    {
        abort_unless(Organizacion::principal()->clientes()->whereKey($user->id)->exists(), 404);

        $search = $this->busqueda($request);
        $directoryPage = max(1, $request->integer('pagina_usuarios', 1));
        $directoryFilters = array_filter([
            'buscar' => $search ?: null,
            'page' => $directoryPage > 1 ? $directoryPage : null,
        ]);
        $projectFilters = array_filter([
            'buscar' => $directoryFilters['buscar'] ?? null,
            'pagina_usuarios' => $directoryFilters['page'] ?? null,
        ]);

        $solicitudes = DB::table('project_requests')->where('user_id', $user->id)->where('status', '<>', 'draft');
        $presupuestos = DB::table('budget_versions as v')->join('budgets as b', 'b.id', '=', 'v.budget_id')
            ->leftJoin('proposals as p', 'p.id', '=', 'b.proposal_id')
            ->where(fn ($query) => $query->where('b.client_user_id', $user->id)
                ->orWhereIn(DB::raw('COALESCE(b.project_request_id, p.project_request_id)'), (clone $solicitudes)->select('id')))
            ->whereNotNull('v.sent_at');

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'gestion',
            'area' => 'gestion',
            'section' => 'clientes',
            'selectedUser' => $user->only('id', 'name', 'email', 'created_at'),
            'solicitudesCliente' => $solicitudes->latest('id')->limit(10)->get(['id', 'title']),
            'presupuestosCliente' => $presupuestos->latest('v.id')->limit(10)->get(['v.id', 'v.title', 'v.version_number']),
            'clientProjects' => $projects->forClient($user->id)->appends($projectFilters),
            'userDirectoryUrl' => route('gestion.clientes.index', $directoryFilters, absolute: false),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    private function busqueda(Request $request): string
    {
        $query = $request->query('buscar');

        return is_string($query) ? Str::substr(trim($query), 0, 120) : '';
    }
}
