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
        // El equipo puede consultar sus asignaciones aunque el marketplace siga cerrado.
        if ($request->is('profesional/proyectos') && $request->user()?->esProfesionalInterno()) {
            return $next($request);
        }
        if ($restricted) {
            abort_unless(config('synaux.stage_two_enabled') || $request->user()?->is_admin, 404);
        }

        return $next($request);
    }
}
