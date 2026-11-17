<?php

use App\Http\Controllers\SynauxAdminController;
use App\Http\Controllers\SynauxTariffImportController;
use App\Http\Controllers\SynauxAuthController;
use App\Http\Controllers\SynauxAccountController;
use App\Http\Controllers\SynauxBudgetPdfController;
use App\Http\Controllers\SynauxController;
use App\Http\Controllers\SynauxReferenceController;
use App\Http\Controllers\SynauxRequestController;
use App\Http\Controllers\SynauxEstimateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// La presentación de marca sigue siendo independiente del catálogo y de las cuentas privadas.
Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/plataforma', fn (Request $r, SynauxController $c) => $c->publicPage($r))->name('platform');
// La galería no tiene rutas de escritura y el controlador la bloquea fuera del entorno local.
Route::get('/prototipo/{screen?}', [SynauxReferenceController::class, 'show']);
Route::get('/media/portfolio/{file}', [SynauxController::class, 'media']);

// Pantallas públicas: los detalles se resuelven por identificador y nunca muestran datos privados.
foreach (['portfolio', 'nosotros' => 'perfil', 'contacto', 'oportunidades' => 'muro', 'profesionales', 'estimador'] as $path => $screen) {
    $path = is_int($path) ? $screen : $path;
    Route::get('/'.$path, fn (Request $r, SynauxController $c) => $c->publicPage($r, $screen));
}
foreach (['servicios' => 'servicio', 'trabajos' => 'trabajo', 'oportunidades' => 'oportunidad', 'profesionales' => 'perfil-profesional'] as $path => $screen) {
    Route::get('/'.$path.'/{id}', fn (Request $r, SynauxController $c, string $id) => $c->publicPage($r, $screen, $id));
}
Route::post('/contacto', [SynauxController::class, 'contact'])->middleware('throttle:6,1');
// El formulario nuevo es público; consultar registros guardados y enviar pedidos sigue requiriendo autenticación.
Route::get('/solicitudes/nueva', [SynauxController::class, 'newRequest']);
Route::post('/solicitudes/preparar', [SynauxController::class, 'prepareRequest'])->middleware('throttle:20,1');
Route::post('/estimador/calcular', [SynauxController::class, 'calculate'])->middleware('throttle:30,1');

// Registro e ingreso usan sesiones y límites de intentos; la recuperación usa el broker de Laravel.
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', fn (Request $r, SynauxController $c) => $c->publicPage($r, 'ingreso'))->name('login');
    Route::get('/crear-cuenta', fn (Request $r, SynauxController $c) => $c->publicPage($r, 'registro'));
    Route::post('/ingresar', [SynauxAuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('/crear-cuenta', [SynauxAuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/recuperar-acceso', fn (Request $r, SynauxController $c) => $c->publicPage($r, 'recuperar'))->name('password.request');
    Route::post('/recuperar-acceso', [SynauxAuthController::class, 'forgot'])->middleware('throttle:3,1');
    Route::get('/restablecer/{token}', fn (Request $r, SynauxController $c, string $token) => $c->publicPage($r, 'restablecer', $token))->name('password.reset');
    Route::post('/restablecer', [SynauxAuthController::class, 'reset'])->middleware('throttle:6,1');
});

// El servidor vuelve a comprobar propiedad y permisos para cada formulario y cada detalle privado.
Route::middleware('auth')->group(function () {
    // Marcar avisos como leídos requiere sesión y no acepta destinatarios enviados por el navegador.
    Route::patch('/notificaciones/leer-todas', [\App\Http\Controllers\SynauxNotificationController::class, 'read'])->middleware('throttle:60,1');
    Route::patch('/notificaciones/{id}/leer', [\App\Http\Controllers\SynauxNotificationController::class, 'read'])->whereNumber('id')->middleware('throttle:60,1');
    // Las rutas de perfil siempre operan sobre la cuenta autenticada.
    Route::get('/mi-perfil', [SynauxAccountController::class, 'edit']);
    Route::patch('/mi-perfil', [SynauxAccountController::class, 'update'])->middleware('throttle:10,1');
    Route::put('/mi-perfil/clave', [SynauxAccountController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::patch('/mi-perfil/emision', [SynauxAccountController::class, 'updateIssuer'])->middleware('throttle:10,1');
    Route::post('/salir', [SynauxAuthController::class, 'logout']);
    foreach (['cliente', 'profesional'] as $area) {
        Route::get('/'.$area.'/{section?}', fn (Request $r, SynauxController $c, string $section = '') => $c->area($r, $area, $section));
    }
    Route::get('/presupuestos/{id}/pdf', [SynauxBudgetPdfController::class, 'show'])->whereNumber('id')->middleware('throttle:20,1');
    foreach (['solicitudes' => 'solicitud', 'estimaciones' => 'estimacion', 'presupuestos' => 'presupuesto', 'proyectos' => 'proyecto'] as $path => $kind) {
        Route::get('/'.$path.'/{id}', fn (Request $r, SynauxController $c, int $id) => $c->detail($r, $kind, $id))->whereNumber('id');
    }
    foreach (['presupuesto' => 'editor-presupuesto', 'trabajo' => 'editor-trabajo'] as $path => $kind) {
        Route::get('/editor/'.$path.'/{id?}', fn (Request $r, SynauxController $c, int $id = 0) => $c->detail($r, $kind, $id))->whereNumber('id');
    }
    // El formulario de solicitudes conserva su URL y el mismo límite de envíos.
    Route::post('/acciones/solicitud', [SynauxRequestController::class, 'store'])->middleware('throttle:60,1');
    // El borrado requiere una sesión y vuelve a comprobar la propiedad en el controlador.
    Route::delete('/estimaciones/{id}', [SynauxEstimateController::class, 'destroy'])->whereNumber('id')->middleware('throttle:20,1');
    // El guardado de estimaciones mantiene la URL y los límites que usa el frontend.
    Route::post('/acciones/guardar-estimacion', [SynauxEstimateController::class, 'store'])->middleware('throttle:60,1');
    Route::post('/acciones/{action}', [SynauxController::class, 'action'])->middleware('throttle:60,1');
    // Gestión y Administración comparten autorización de área, además de la autenticación del grupo exterior.
    Route::middleware('synaux.management')->group(function () {
        Route::post('/admin/tarifario/importar', [SynauxTariffImportController::class, 'store'])->middleware('throttle:2,1');
        // Entrada administrativa de revisión para las funciones de la segunda etapa.
        Route::get('/admin/etapa-2', fn () => Inertia::render('StageTwo'))->name('stage-two');
        // El asistente usa una página propia y conserva el permiso real del grupo administrativo.
        Route::get('/admin/servicios/nuevo', fn (Request $r, SynauxController $c) => $c->area($r, 'admin', 'servicios'));
        Route::get('/admin/servicios/{service}/editar', function (Request $r, SynauxController $c, int $service) {
            abort_unless(\Illuminate\Support\Facades\DB::table('services')->where('id', $service)->exists(), 404);

            return $c->area($r, 'admin', 'servicios');
        })->whereNumber('service');
        foreach (['gestion', 'admin'] as $area) {
            Route::get('/'.$area.'/{section?}', fn (Request $r, SynauxController $c, string $section = '') => $c->area($r, $area, $section));
        }
        Route::post('/admin/guardar/{resource}', [SynauxAdminController::class, 'save'])->middleware('throttle:30,1');
    });
});
