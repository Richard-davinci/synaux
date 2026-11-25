<?php

use App\Http\Middleware\EnsureSynauxManagement;
use App\Http\Middleware\EnsureSynauxStageTwo;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// Configura el arranque de Laravel a partir de la carpeta raíz del proyecto.
return Application::configure(basePath: dirname(__DIR__))
    // Define los archivos de rutas web, los comandos y la comprobación de disponibilidad /up.
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    // Incorpora Inertia al grupo web, que ya incluye sesiones y protección CSRF de Laravel.
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureSynauxStageTwo::class, HandleInertiaRequests::class]);
        // Las rutas de gestión declaran explícitamente el permiso administrativo.
        $middleware->alias(['synaux.management' => EnsureSynauxManagement::class]);
    })
    // Presenta los errores de navegación con la interfaz del sitio, sin cambiar las respuestas de API o de formularios.
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->expectsJson() || !in_array($request->method(), ['GET', 'HEAD'], true)) {
                return $response;
            }

            // Laravel ya guardó el destino en sesión al preparar la redirección de autenticación.
            $status = $exception instanceof AuthenticationException && $response->isRedirect()
                ? 401
                : $response->getStatusCode();
            if (!in_array($status, [401, 403, 404], true)) {
                return $response;
            }

            // La pantalla recibe el rol para el enlace de salida; nunca recibe detalles de la excepción.
            $user = $status === 403 ? $request->user() : null;
            $page = Inertia::render('AccessError', [
                'status' => $status,
                'accountArea' => $user ? ($user->is_admin ? 'management' : 'client') : null,
            ])->toResponse($request)->setStatusCode($status);

            $page->headers->set('Vary', 'X-Inertia');
            $page->headers->set('Cache-Control', 'no-store');

            return $page;
        });
    })->create();
