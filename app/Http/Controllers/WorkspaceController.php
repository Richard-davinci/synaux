<?php

namespace App\Http\Controllers;

use App\Services\Admin\CatalogConfiguration;
use App\Services\SynauxCatalog;
use App\Services\SynauxNotificationCenter;
use App\Services\SynauxWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function __construct(
        private SynauxCatalog $catalog,
        private SynauxWorkspace $workspace,
        private CatalogConfiguration $configuration,
        private SynauxNotificationCenter $notifications,
    ) {}

    // Resuelve el área y su sección; Gestión y Administración exigen el permiso administrativo real.
    public function show(Request $request, string $area, string $section = '')
    {
        $admin = in_array($area, ['gestion', 'admin'], true);
        abort_if($admin && ! $request->user()->is_admin, 403);
        $allowed = [
            'cliente' => ['', 'estimaciones', 'solicitudes', 'presupuestos', 'proyectos', 'publicaciones', 'propuestas', 'invitaciones'],
            'profesional' => ['', 'perfil', 'propuestas', 'invitaciones', 'presupuestos', 'proyectos', 'portfolio'],
            'gestion' => ['', 'solicitudes', 'presupuestos', 'proyectos', 'portfolio', 'contactos'],
            // Catálogo reutiliza las mismas tablas y la autorización administrativa del área.
            'admin' => ['', 'servicios', 'categorias', 'preguntas', 'componentes', 'reglas', 'tarifario'],
        ];
        abort_unless(isset($allowed[$area]) && in_array($section, $allowed[$area], true), 404);
        // Las vistas de cliente de la etapa 2 siguen disponibles desde su entrada de revisión.
        if ($area === 'cliente' && $request->user()->is_admin
            && ! in_array($section, ['publicaciones', 'propuestas', 'invitaciones'], true)) {
            return redirect('/gestion');
        }
        // Los accesos anteriores llevan a la configuración del servicio, sin duplicar administraciones.
        if ($area === 'admin' && in_array($section, ['preguntas', 'componentes', 'reglas'], true)) {
            $serviceId = DB::table('services')->when($request->integer('servicio'), fn ($q) => $q->where('id', $request->integer('servicio')))->orderBy('id')->value('id');
            return redirect($serviceId ? "/admin/servicios/$serviceId/editar?config=".($section === 'preguntas' ? 'preguntas' : 'estimacion') : '/admin/servicios');
        }
        $data = $area === 'admin'
            ? ['administration' => $this->configuration->data()]
            : $this->privateData($request, $admin);
        $data['screen'] = $area;
        $data['area'] = $area === 'admin' ? 'gestion' : $area;
        $data['section'] = $section;
        if ($area === 'profesional' && in_array($section, ['presupuestos', 'proyectos'], true)) {
            // Distingue los presupuestos emitidos del historial de esta misma persona como cliente.
            $data['quotes'] = array_values(array_filter($data['quotes'], fn ($q) => $q['issuer_id'] === $request->user()->id));
            $data['projects'] = $this->workspace->projects($data['quotes']);
        }
        // El resumen necesita el total de consultas, sin cargar el contenido de sus mensajes.
        if ($area === 'gestion' && $section === '') {
            $data['inquiryCount'] = DB::table('contact_inquiries')->count();
        }
        if ($section === 'contactos') {
            $data['inquiries'] = DB::table('contact_inquiries')->orderByDesc('id')->get();
            // El enlace del aviso identifica la consulta que se abrió sin marcar todo el listado.
            if ($request->integer('consulta') && $data['inquiries']->contains('id', $request->integer('consulta'))) {
                $this->notifications->readEntity($request->user(), 'consulta', $request->integer('consulta'));
            }
        }

        return Inertia::render('Workspace', $data);
    }

    // La edición usa la pantalla de servicios y comprueba que exista el registro solicitado.
    public function editService(Request $request, int $service)
    {
        abort_unless(DB::table('services')->where('id', $service)->exists(), 404);

        return $this->show($request, 'admin', 'servicios');
    }

    // Reúne las colecciones de la cuenta sin mezclar registros privados de otras personas.
    private function privateData(Request $request, bool $management = false): array
    {
        $id = $request->user()->id;
        $profile = DB::table('professional_profiles')->where('user_id', $id)->first();
        $quotes = $this->workspace->quotes($id, $management);
        // El profesional solo puede presupuestar solicitudes donde fue elegido; no recibe otras solicitudes privadas.
        $chosen = $profile ? DB::table('proposals')->where('professional_profile_id', $profile->id)->where('status', 'accepted')->pluck('project_request_id')->all() : [];
        $budgetRequests = $management ? $this->workspace->requests($id, true) : ($chosen ? $this->workspace->requests(null, true, only: $chosen) : []);

        // Los formularios reciben solo el contacto y la identidad de emisión de su propia cuenta.
        return ['accountContact' => $request->user()->contactDetails(),
            'issuerDetails' => $request->user()->issuerDetails($profile?->brand_name),
            'area' => $management ? 'gestion' : 'cliente', 'services' => $this->catalog->services($management), 'requests' => $this->workspace->requests($id, $management),
            'references' => $this->catalog->references(), 'budgetRequests' => $budgetRequests,
            'quotes' => $quotes, 'projects' => $this->workspace->projects($quotes),
            'proposals' => $this->workspace->proposals($id, $management), 'invitations' => $this->workspace->invitations($id, $management),
            'profile' => $profile ? $this->workspace->profile($profile) : null, 'professionals' => $this->workspace->professionals(),
            'works' => $this->workspace->works($id), 'estimates' => DB::table('estimates')->where('user_id', $id)->whereNull('deleted_at')->orderByDesc('id')->get()->map(fn ($e) => [
                ...((array) $e), 'answers' => json_decode($e->answers_snapshot, true), 'items' => json_decode($e->items_snapshot ?? '[]', true),
            ])->all()];
    }
}
