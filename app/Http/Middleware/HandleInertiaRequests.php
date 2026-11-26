<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

// Comparte la sesión propia y la navegación; los registros privados se consultan por pantalla.
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    // Comparte navegación, avisos y datos mínimos de la sesión entre las páginas de Inertia.
    public function share(Request $request): array
    {
        $user = $request->user();
        $professional = $user && Schema::hasTable('professional_profiles')
            ? DB::table('professional_profiles')->where('user_id', $user->id)->exists() : false;

        // La navegación usa los mismos permisos efectivos que protegen las rutas de synaux.
        $conEquipo = $user && Schema::hasTable('organizacion_miembros');
        $rolOrganizacion = $conEquipo ? $user->rolEnSynaux() : null;

        return [...parent::share($request), 'appName' => 'synaux',
            'stageTwo' => (bool)(config('synaux.stage_two_enabled') || $user?->is_admin), 'homeUrl' => '/', 'platformUrl' => '/plataforma', 'year' => (int)date('Y'),
            'auth' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'foto_perfil' => $user->foto_perfil ? '/mi-perfil/foto?v=' . $user->updated_at?->format('Uu') : null,
                'is_admin' => (bool)$user->is_admin, 'professional' => $professional, 'rol_organizacion' => $rolOrganizacion,
                'es_cuenta_interna' => $conEquipo ? $user->esCuentaInterna() : (bool)$user->is_admin,
                'puede_gestionar' => $conEquipo ? $user->puedeGestionarSynaux() : (bool)$user->is_admin,
                'puede_administrar' => $conEquipo ? $user->puedeAdministrarSynaux() : (bool)$user->is_admin,
                'panel_principal' => $conEquipo ? $user->panelPrincipal() : ($user->is_admin ? '/gestion' : '/cliente'),
            ] : null,
            'notice' => fn() => $request->session()->get('notice'),
            // Los avisos y badges se calculan para la sesión propia, también en las recargas parciales.
            'notifications' => fn() => app(\App\Services\Notifications\NotificationCenter::class)->data($user),
        ];
    }
}
