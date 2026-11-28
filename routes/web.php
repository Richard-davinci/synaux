<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivacionCuentaController;
use App\Http\Controllers\Admin\CatalogConfigurationController;
use App\Http\Controllers\Admin\DirectorioUsuariosController;
use App\Http\Controllers\Admin\OrganizacionController;
use App\Http\Controllers\Admin\SimuladorEstimacionesController;
use App\Http\Controllers\Admin\TariffController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EspacioTrabajoController;
use App\Http\Controllers\Estimates\EstimateController;
use App\Http\Controllers\Marketplace\InvitationController;
use App\Http\Controllers\Marketplace\ProfessionalProfileController;
use App\Http\Controllers\Marketplace\ProposalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Portfolio\PortfolioWorkController;
use App\Http\Controllers\Presupuestos\PdfPresupuestoController;
use App\Http\Controllers\Presupuestos\PresupuestoController;
use App\Http\Controllers\Projects\PagoProyectoController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Solicitudes\SolicitudController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// La campaña, la portada del sitio y el catálogo tienen entradas públicas independientes.
Route::get('/', fn() => Inertia::render('Home'))->name('home');
Route::get('/inicio', [PublicPageController::class, 'inicio'])->name('site.home');
Route::get('/plataforma', [PublicPageController::class, 'show'])->name('platform');
// La galería no tiene rutas de escritura y el controlador la bloquea fuera del entorno local.
Route::get('/prototipo/{screen?}', [PrototypeController::class, 'show']);
Route::get('/media/portfolio/{file}', [PortfolioWorkController::class, 'media']);

// Pantallas públicas: los detalles se resuelven por identificador y nunca muestran datos privados.
foreach (['portfolio', 'nosotros' => 'perfil', 'contacto', 'oportunidades' => 'muro', 'profesionales', 'estimador'] as $path => $screen) {
    $path = is_int($path) ? $screen : $path;
    Route::get('/' . $path, fn(Request $r, PublicPageController $c) => $c->show($r, $screen));
}
foreach (['servicios' => 'servicio', 'trabajos' => 'trabajo', 'oportunidades' => 'oportunidad', 'profesionales' => 'perfil-profesional'] as $path => $screen) {
    Route::get('/' . $path . '/{id}', fn(Request $r, PublicPageController $c, string $id) => $c->show($r, $screen, $id));
}
Route::post('/contacto', [ContactController::class, 'store'])->middleware('throttle:6,1');
// El formulario nuevo es público; consultar registros guardados y enviar pedidos sigue requiriendo autenticación.
Route::get('/solicitudes/nueva', [SolicitudController::class, 'create']);
Route::post('/solicitudes/preparar', [SolicitudController::class, 'prepare'])->middleware('throttle:20,1');
Route::post('/estimador/reiniciar', [EstimateController::class, 'restart'])->middleware('throttle:15,1,reiniciar-estimacion');
Route::post('/estimador/calcular', [EstimateController::class, 'calculate'])->middleware('throttle:30,1');

// Registro e ingreso usan sesiones y límites de intentos; la recuperación usa el broker de Laravel.
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', fn(Request $r, PublicPageController $c) => $c->show($r, 'ingreso'))->name('login');
    Route::get('/crear-cuenta', fn(Request $r, PublicPageController $c) => $c->show($r, 'registro'));
    Route::post('/ingresar', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('/crear-cuenta', [AuthController::class, 'register'])->middleware('throttle:6,1');
    // La activación del equipo tiene su propio enlace de un solo uso.
    Route::get('/activar-cuenta/{token}', [ActivacionCuentaController::class, 'edit'])
        ->where('token', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('cuenta.activar');
    Route::post('/activar-cuenta/{token}', [ActivacionCuentaController::class, 'update'])
        ->where('token', '[a-f0-9]{64}')->middleware('throttle:10,1');
    Route::get('/recuperar-acceso', fn(Request $r, PublicPageController $c) => $c->show($r, 'recuperar'))->name('password.request');
    Route::post('/recuperar-acceso', [AuthController::class, 'forgot'])->middleware('throttle:3,1');
    Route::get('/restablecer/{token}', fn(Request $r, PublicPageController $c, string $token) => $c->show($r, 'restablecer', $token))->name('password.reset');
    Route::post('/restablecer', [AuthController::class, 'reset'])->middleware('throttle:6,1');
});

// El servidor vuelve a comprobar propiedad y permisos para cada formulario y cada detalle privado.
Route::middleware('auth')->group(function () {
    // Marcar avisos como leídos requiere sesión y no acepta destinatarios enviados por el navegador.
    Route::patch('/notificaciones/leer-todas', [NotificationController::class, 'read'])->middleware('throttle:60,1');
    Route::patch('/notificaciones/{id}/leer', [NotificationController::class, 'read'])->whereNumber('id')->middleware('throttle:60,1');
    // Las rutas de perfil siempre operan sobre la cuenta autenticada.
    Route::get('/mi-perfil', [AccountController::class, 'edit']);
    Route::get('/mi-perfil/foto', [AccountController::class, 'foto']);
    Route::post('/mi-perfil', [AccountController::class, 'update'])->middleware('throttle:10,1');
    Route::patch('/mi-perfil', [AccountController::class, 'update'])->middleware('throttle:10,1');
    Route::put('/mi-perfil/clave', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::patch('/mi-perfil/emision', [OrganizacionController::class, 'update'])->middleware('throttle:10,1');
    Route::post('/salir', [AuthController::class, 'logout']);
    foreach (['cliente', 'profesional'] as $area) {
        Route::get('/' . $area . '/{section?}', fn(Request $r, EspacioTrabajoController $c, string $section = '') => $c->show($r, $area, $section));
    }
    Route::get('/presupuestos/{id}/pdf', [PdfPresupuestoController::class, 'show'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/solicitudes/{id}', [SolicitudController::class, 'show'])->whereNumber('id');
    Route::get('/estimaciones/{id}', [EstimateController::class, 'show'])->whereNumber('id');
    Route::get('/presupuestos/{id}', [PresupuestoController::class, 'show'])->whereNumber('id');
    Route::get('/proyectos/{id}', [ProjectController::class, 'show'])->whereNumber('id');
    Route::patch('/proyectos/{id}/responsable', [ProjectController::class, 'asignar'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/proyectos/{id}/tareas', [ProjectController::class, 'agregarTarea'])->whereNumber('id')->middleware('throttle:60,1');
    Route::patch('/proyectos/{id}/tareas/{tarea}', [ProjectController::class, 'completarTarea'])
        ->whereNumber(['id', 'tarea'])->middleware('throttle:60,1');
    // Cada operación verifica la asociación entre el proyecto, el comprobante y la cuenta.
    Route::prefix('/proyectos/{proyecto}/pagos/{pago}')->whereNumber(['proyecto', 'pago'])->middleware('throttle:30,1')->group(function () {
        Route::post('/comprobante', [PagoProyectoController::class, 'presentar']);
        Route::get('/comprobante', [PagoProyectoController::class, 'descargar']);
        Route::patch('/revision', [PagoProyectoController::class, 'revisar']);
    });
    Route::get('/editor/presupuesto/{id?}', [PresupuestoController::class, 'edit'])->whereNumber('id');
    Route::get('/editor/trabajo/{id?}', [PortfolioWorkController::class, 'edit'])->whereNumber('id');
    // El formulario de solicitudes conserva su URL y el mismo límite de envíos.
    Route::post('/acciones/solicitud', [SolicitudController::class, 'store'])->middleware('throttle:60,1');
    // El borrado requiere una sesión y vuelve a comprobar la propiedad en el controlador.
    Route::delete('/estimaciones/{id}', [EstimateController::class, 'destroy'])->whereNumber('id')->middleware('throttle:20,1');
    // El guardado de estimaciones mantiene la URL y los límites que usa el frontend.
    Route::post('/acciones/guardar-estimacion', [EstimateController::class, 'store'])->middleware('throttle:60,1');
    // Cada acción conserva su URL y su límite, con autorización específica en el controlador.
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/acciones/estado-solicitud', [SolicitudController::class, 'updateStatus']);
        Route::post('/acciones/perfil', [ProfessionalProfileController::class, 'update']);
        Route::post('/acciones/propuesta', [ProposalController::class, 'store']);
        Route::post('/acciones/seleccionar-propuesta', [ProposalController::class, 'select']);
        Route::post('/acciones/retirar-propuesta', [ProposalController::class, 'withdraw']);
        Route::post('/acciones/invitacion', [InvitationController::class, 'store']);
        Route::post('/acciones/responder-invitacion', [InvitationController::class, 'respond']);
        Route::post('/acciones/presupuesto', [PresupuestoController::class, 'store']);
        Route::post('/acciones/decidir-presupuesto', [PresupuestoController::class, 'decide']);
        Route::post('/acciones/proyecto', [ProjectController::class, 'update']);
        Route::post('/acciones/trabajo', [PortfolioWorkController::class, 'store']);
        Route::post('/acciones/{action}', fn() => abort(404));
    });
    // Gestión y Administración comparten autorización de área, además de la autenticación del grupo exterior.
    Route::middleware('synaux.management')->group(function () {
        // La ficha de synaux queda en Administración y requiere el permiso de Gestión.
        Route::get('/admin/organizacion', [OrganizacionController::class, 'edit']);
        Route::patch('/admin/organizacion/dominio-equipo', [OrganizacionController::class, 'actualizarDominio'])->middleware('throttle:10,1,dominio-equipo');
        Route::patch('/admin/organizacion', [OrganizacionController::class, 'update'])->middleware('throttle:10,1');
        // El listado de cuentas usa el mismo permiso administrativo que el catálogo.
        Route::get('/admin/usuarios', DirectorioUsuariosController::class)->name('admin.users.index');
        Route::post('/admin/usuarios/equipo', [DirectorioUsuariosController::class, 'guardarMiembro'])->middleware('throttle:10,1,alta-equipo');
        Route::post('/admin/usuarios/{user}/activacion', [DirectorioUsuariosController::class, 'compartirActivacion'])
            ->whereNumber('user')->middleware('throttle:10,1,activacion-equipo');
        Route::patch('/admin/usuarios/{user}/rol', [DirectorioUsuariosController::class, 'cambiarRol'])->whereNumber('user')->middleware('throttle:20,1,rol-equipo');
        Route::delete('/admin/usuarios/{user}/equipo', [DirectorioUsuariosController::class, 'quitarMiembro'])->whereNumber('user')->middleware('throttle:10,1,baja-equipo');
        Route::get('/gestion/clientes', [DirectorioUsuariosController::class, 'clientes'])->name('gestion.clientes.index');
        Route::get('/gestion/clientes/{user}', [DirectorioUsuariosController::class, 'show'])->whereNumber('user')->name('gestion.clientes.show');
        // Entrada administrativa de revisión para las funciones de la segunda etapa.
        Route::get('/admin/etapa-2', fn() => Inertia::render('RevisionEtapaDos'))->name('stage-two');
        // El asistente usa una página propia y conserva el permiso real del grupo administrativo.
        Route::get('/admin/servicios/nuevo', fn(Request $r, EspacioTrabajoController $c) => $c->show($r, 'admin', 'servicios'));
        Route::get('/admin/servicios/{service}/editar', [EspacioTrabajoController::class, 'editService'])->whereNumber('service');
        // La vista previa está separada de los recorridos comerciales y solo la opera Administración.
        Route::post('/admin/servicios/{service}/simular', [SimuladorEstimacionesController::class, 'preview'])
            ->whereNumber('service')->middleware('throttle:30,1');
        foreach (['gestion', 'admin'] as $area) {
            Route::get('/' . $area . '/{section?}', fn(Request $r, EspacioTrabajoController $c, string $section = '') => $c->show($r, $area, $section));
        }
        // Los precios publican versiones del tarifario; el resto configura el catálogo.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/admin/servicios/{service}/precios', [TariffController::class, 'guardarServicio'])->whereNumber('service');
            Route::get('/admin/tarifario/ccl', [TariffController::class, 'consultarCcl']);
            Route::post('/admin/tarifario/ccl', [TariffController::class, 'actualizarCcl']);
            Route::post('/admin/guardar/tarifario', [TariffController::class, 'store']);
            Route::post('/admin/guardar/servicios-destacados', [CatalogConfigurationController::class, 'saveFeatured']);
            Route::post('/admin/guardar/{resource}', [CatalogConfigurationController::class, 'save']);
            Route::delete('/admin/catalogo/{resource}/{id}', [CatalogConfigurationController::class, 'eliminar'])
                ->whereIn('resource', ['servicios', 'categorias'])->whereNumber('id');
        });
    });
});
