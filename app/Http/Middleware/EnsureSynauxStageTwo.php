<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Protege las entradas del marketplace, incluso si se escribe directamente su dirección.
class EnsureSynauxStageTwo
{
    public function handle(Request $request, Closure $next): Response
    {
        $restricted = $request->is(
            'oportunidades', 'oportunidades/*', 'profesionales', 'profesionales/*',
            'profesional', 'profesional/*', 'cliente/publicaciones', 'cliente/propuestas', 'cliente/invitaciones',
            'acciones/perfil', 'acciones/propuesta', 'acciones/seleccionar-propuesta',
            'acciones/retirar-propuesta', 'acciones/invitacion', 'acciones/responder-invitacion'
        );
        if ($restricted) {
            abort_unless(config('synaux.stage_two_enabled') || $request->user()?->is_admin, 404);
        }

        return $next($request);
    }
}
