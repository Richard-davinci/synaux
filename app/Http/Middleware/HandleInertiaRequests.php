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

        return [...parent::share($request), 'appName' => 'synaux',
            'stageTwo' => (bool) (config('synaux.stage_two_enabled') || $user?->is_admin), 'homeUrl' => '/', 'platformUrl' => '/plataforma', 'year' => (int) date('Y'),
            'auth' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_admin' => (bool) $user->is_admin, 'professional' => $professional] : null,
            'notice' => fn () => $request->session()->get('notice'),
            // Los avisos y badges se calculan para la sesión propia, también en las recargas parciales.
            'notifications' => fn () => app(\App\Services\SynauxNotificationCenter::class)->data($user),
        ];
    }
}
