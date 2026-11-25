<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Administración cambia la configuración; Gestión también admite supervisores del mismo espacio.
class EnsureSynauxManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        $autorizado = $request->is('admin', 'admin/*')
            ? $usuario?->puedeAdministrarSynaux()
            : $usuario?->puedeGestionarSynaux();
        abort_unless($autorizado, 403);

        return $next($request);
    }
}
