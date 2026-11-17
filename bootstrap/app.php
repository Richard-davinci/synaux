<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureSynauxManagement;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Configura el arranque de Laravel a partir de la carpeta raíz del proyecto.
return Application::configure(basePath: dirname(__DIR__))
    // Define los archivos de rutas web, los comandos y la comprobación de disponibilidad /up.
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Incorpora Inertia al grupo web, que ya incluye sesiones y protección CSRF de Laravel.
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        // Las rutas de gestión declaran explícitamente el permiso administrativo.
        $middleware->alias(['synaux.management' => EnsureSynauxManagement::class]);
    })
    // Conserva el manejo de errores predeterminado de Laravel.
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los manejadores específicos se agregarán aquí si el proyecto los necesita.
    })->create();
