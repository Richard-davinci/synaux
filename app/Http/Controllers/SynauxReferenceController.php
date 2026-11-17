<?php

namespace App\Http\Controllers;

use App\Services\SynauxCatalog;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

// Galería exclusivamente local: muestra los estados de Figma con datos aislados y nunca escribe en la base.
class SynauxReferenceController extends Controller
{
    // Resuelve un estado de Figma con datos aislados; no crea cuentas ni modifica la sesión real.
    public function show(string $screen = 'inicio')
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        $screens = json_decode(File::get(resource_path('reference/screens.json')), true);
        // La galería también oculta los estados del marketplace para visitantes de la primera etapa.
        if (! config('synaux.stage_two_enabled') && ! request()->user()?->is_admin) {
            $screens = array_values(array_filter($screens, fn ($item) => ! str_starts_with($item['name'], '3.')));
        }
        abort_unless(collect($screens)->contains('key', $screen), 404);
        $catalog = app(SynauxCatalog::class);
        $services = $catalog->services();
        $web = collect($services)->firstWhere('id', 'sitio-institucional');
        $questions = $catalog->questions($web['database_id']);
        $answers = array_map(fn ($q) => ['id' => $q['id'], 'question' => $q['question'], 'value' => (string) ($q['options'][0]->id ?? ''), 'label' => $q['options'][0]->label ?? 'Proyecto de referencia'], $questions);
        $professionals = [
            ['id' => 21, 'name' => 'Lucía Ferrer', 'headline' => 'Diseño UX/UI y sistemas de diseño', 'bio' => 'Diseño productos digitales claros y escalables para equipos que necesitan ordenar experiencias complejas.', 'skills' => ['UX research', 'Figma', 'Sistemas de diseño', 'Prototipado'], 'location' => 'Córdoba, Argentina'],
            ['id' => 34, 'name' => 'Nicolás Acosta', 'headline' => 'Desarrollo web frontend', 'bio' => 'Desarrollo interfaces accesibles y veloces con foco en arquitecturas mantenibles.', 'skills' => ['React', 'TypeScript', 'Accesibilidad', 'APIs'], 'location' => 'Rosario, Argentina'],
            ['id' => 47, 'name' => 'Paula Méndez', 'headline' => 'Estrategia de contenidos y marketing', 'bio' => 'Conecto estrategia, contenidos y medición para lanzamientos de servicios digitales.', 'skills' => ['Estrategia', 'SEO', 'Analítica', 'Campañas'], 'location' => 'Buenos Aires, Argentina'],
        ];
        $professionals = array_map(fn ($p) => [...$p, 'brand_name' => $p['name'], 'active' => true, 'availability_status' => 'available'], $professionals);
        $opportunities = [];
        foreach ([
            [318, 'Rediseño de plataforma para asociados', 'Buscamos simplificar la consulta de beneficios y trámites para más de 4.000 asociados.', 'Producto digital', ['UX research', 'Figma', 'React', 'Accesibilidad'], '$3.200.000 – $4.500.000 ARS', '8 a 10 semanas', 3],
            [322, 'Identidad y sitio para plataforma educativa', 'Necesitamos definir una identidad cercana y aplicarla a un sitio de lanzamiento responsive.', 'Marca y web', ['Identidad visual', 'Diseño web', 'Figma'], '$2.000.000 – $2.800.000 ARS', '6 semanas', 1],
            [305, 'Campaña digital para nueva línea', 'Estrategia y piezas para presentar una línea de vinos jóvenes en canales digitales.', 'Marketing', ['Estrategia', 'Campañas', 'Analítica'], '', 'Inicio en agosto', 2],
        ] as [$id, $title, $description, $category, $skills, $budget, $deadline, $count]) {
            $opportunities[] = ['id' => $id, 'title' => $title, 'description' => $description, 'category' => $category, 'skills' => $skills, 'budget_reference' => $budget, 'desired_deadline' => $deadline, 'proposal_count' => $count, 'visibility' => 'public', 'status' => 'open', 'published_at' => '2025-06-18', 'created_at' => '2025-06-18', 'user_id' => 1, 'service_id' => $web['database_id'], 'service_name' => $web['name'], 'service_slug' => $web['id'], 'answers' => [], 'reviewed_at' => null];
        }
        $request = [...$opportunities[0], 'id' => 1042, 'title' => 'Renovación web para Estudio Norte', 'description' => 'Necesitamos ordenar la propuesta del estudio y captar consultas calificadas.', 'visibility' => 'private', 'answers' => $answers, 'reviewed_at' => '2025-06-18', 'estimate_id' => 64];
        $draft = [...$opportunities[2], 'id' => 1038, 'title' => 'Campaña de lanzamiento Aurora', 'status' => 'draft', 'visibility' => 'private', 'estimate_id' => null];
        $items = [
            ['description' => 'Diseño UX/UI · Arquitectura, wireframes y diseño responsive', 'quantity' => 1, 'unit_price' => 720000],
            ['description' => 'Desarrollo web · Hasta 10 secciones y formulario', 'quantity' => 1, 'unit_price' => 1430000],
            ['description' => 'Asistencia de contenidos · Edición y carga inicial', 'quantity' => 1, 'unit_price' => 300000],
        ];
        $quote = ['id' => 2082, 'budget_id' => 208, 'request_id' => 1042, 'request_owner' => 1, 'issuer_id' => 2, 'title' => $request['title'], 'request_title' => $request['title'], 'version_number' => 2, 'status' => 'sent', 'display_status' => 'sent', 'current' => true, 'created_at' => '2025-06-18', 'sent_at' => '2025-06-18', 'valid_until' => today()->addDays(14)->toDateString(), 'currency_code' => 'ARS', 'tax_rate' => 0, 'subtotal' => 2450000, 'total' => 2450000, 'estimated_delivery_days' => 42, 'terms' => "2 instancias de revisión por etapa.\n50% al inicio y 50% contra entrega.", 'items' => $items];
        // El documento de ejemplo usa identidades ficticias, igual que la vista previa de Gestión.
        $quote['client'] = ['name' => 'Cliente de ejemplo', 'company' => 'Estudio Norte', 'email' => 'cliente@example.test'];
        $quote['issuer'] = ['name' => 'Synaux', 'email' => 'contacto@example.test'];
        $previous = [...$quote, 'id' => 2081, 'version_number' => 1, 'current' => false, 'sent_at' => '2025-06-12'];
        $expired = [...$quote, 'id' => 1961, 'budget_id' => 196, 'title' => 'Sistema visual Horizonte', 'version_number' => 1, 'display_status' => 'expired', 'valid_until' => '2025-05-10', 'subtotal' => 980000, 'total' => 980000];
        $project = ['id' => 78, 'title' => 'Sitio institucional Andina', 'status' => 'in_progress', 'progress' => 60, 'accepted_budget_version_id' => 2082, 'created_at' => '2025-06-12', 'start_date' => '2025-06-12', 'end_date' => null, 'notes' => 'Diseño aprobado. Estamos avanzando en desarrollo y carga de contenidos. Próximo paso: revisión del sitio.'];
        $works = [];
        foreach ([['Sitio institucional Andina', 'Desarrollo web', 'andina.png'], ['Campaña Aurora', 'Marketing', 'aurora.png'], ['Sistema visual Horizonte', 'Diseño', 'horizonte.png']] as $i => [$title, $category, $cover]) {
            $works[] = ['id' => $i + 1, 'title' => $title, 'category' => $category, 'description' => 'Estrategia, diseño y tecnología para una experiencia digital clara.', 'challenge' => 'Ordenar la información y construir una presencia digital consistente.', 'result' => 'Una propuesta visual y funcional alineada con los objetivos del proyecto.', 'year' => 2025, 'cover_path' => '/images/prototype/'.$cover, 'images' => [], 'is_public' => true, 'service_id' => $web['database_id'], 'professional_profile_id' => 21, 'live_url' => null];
        }
        $proposals = [];
        foreach ($professionals as $i => $p) {
            $proposals[] = ['id' => 81 + $i, 'project_request_id' => 318, 'professional_profile_id' => $p['id'], 'professional_user_id' => 2 + $i, 'owner_id' => 1, 'name' => $p['name'], 'headline' => $p['headline'], 'skills' => $p['skills'], 'title' => $opportunities[0]['title'], 'message' => ['Comenzaría con entrevistas y un mapa de tareas para priorizar la experiencia de asociados.', 'Propongo una implementación incremental, con accesibilidad validada en cada entrega.', 'Estrategia de lanzamiento en tres momentos con tablero de indicadores.'][$i], 'estimated_delivery_days' => [56, 63, 42][$i], 'amount' => [3650000, 3980000, 1850000][$i], 'currency_code' => 'ARS', 'status' => 'submitted', 'created_at' => '2025-06-18'];
        }
        $invitations = array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'project_request_id' => 318, 'professional_profile_id' => $p['id'], 'professional_user_id' => $p['professional_user_id'], 'owner_id' => 1, 'title' => $p['title'], 'status' => 'pending', 'request_status' => 'open', 'created_at' => '2025-06-18'], $proposals);
        $estimate = ['id' => 64, 'service_id' => $web['database_id'], 'status' => 'calculated', 'summary' => $web['name'], 'price_min' => 1900000, 'price_max' => 2600000, 'currency_snapshot' => 'ARS', 'created_at' => '2025-06-18', 'answers' => $answers, 'items' => []];
        // Este resultado ilustra el archivo de diseño: no se incorpora al tarifario operativo.
        $data = ['preview' => true, 'referenceKey' => $screen, 'referenceScreens' => $screens, 'area' => 'public', 'screen' => $screen, 'section' => '', 'services' => $services, 'questions' => $questions, 'works' => $works, 'professionals' => $professionals, 'profile' => $professionals[0], 'opportunities' => $opportunities, 'requests' => [$request, $draft, ...$opportunities], 'quotes' => [$quote, $previous, $expired], 'projects' => [$project], 'estimates' => [$estimate], 'proposals' => $proposals, 'invitations' => $invitations, 'selected' => null, 'pending' => ['key' => 'reference-only', 'mode' => 'automatic', 'service' => $web, 'min' => 1900000, 'max' => 2600000, 'currency' => 'ARS', 'date' => '2025-06-18', 'answers' => $answers, 'items' => []]];
        $data['referenceRoutes'] = ['/' => 'inicio', '/plataforma' => 'servicios', '/portfolio' => 'portfolio', '/nosotros' => 'perfil', '/contacto' => 'contacto', '/ingresar' => 'ingreso', '/crear-cuenta' => 'registro', '/recuperar-acceso' => 'recuperar', '/estimador' => 'estimador-1', '/cliente' => 'cliente', '/solicitudes/nueva' => 'publicar', '/oportunidades' => 'muro', '/profesionales' => 'profesionales', '/profesional' => 'profesional', '/gestion' => 'gestion', '/editor/presupuesto' => 'editor-presupuesto', '/editor/trabajo' => 'editor-trabajo'];
        foreach (['estimaciones', 'solicitudes', 'presupuestos', 'proyectos', 'publicaciones', 'propuestas', 'invitaciones'] as $section) {
            $data['referenceRoutes']['/cliente/'.$section] = ['publicaciones' => 'mis-publicaciones', 'propuestas' => 'propuestas-recibidas', 'invitaciones' => 'invitaciones-enviadas'][$section] ?? $section;
        }
        foreach (['solicitudes', 'presupuestos', 'proyectos', 'portfolio'] as $section) {
            $data['referenceRoutes']['/gestion/'.$section] = 'g-'.$section;
        }
        foreach (['propuestas', 'invitaciones', 'perfil', 'presupuestos', 'proyectos'] as $section) {
            $data['referenceRoutes']['/profesional/'.$section] = ['propuestas' => 'mis-propuestas', 'invitaciones' => 'invitaciones-recibidas', 'perfil' => 'mi-perfil'][$section] ?? 'profesional-'.$section;
        }
        foreach (['servicios', 'preguntas', 'componentes', 'reglas', 'tarifario'] as $section) {
            $data['referenceRoutes']['/admin/'.$section] = 'admin-'.$section;
        }
        // Los estados se resuelven explícitamente; ningún parámetro permite seleccionar un usuario real.
        if (str_starts_with($screen, 'servicio-')) {
            $data['screen'] = 'servicio';
            $data['selected'] = $services[match ($screen) {
                'servicio-identidad' => 1, 'servicio-marketing' => 2, default => 0
            }];
        } elseif (str_starts_with($screen, 'estimador-')) {
            $data['screen'] = 'estimador';
            $data['step'] = (int) substr($screen, -1);
            $data['selected'] = $web;
        } elseif (in_array($screen, ['estimaciones', 'solicitudes', 'presupuestos', 'proyectos', 'mis-publicaciones', 'propuestas-recibidas', 'invitaciones-enviadas', 'comparar-propuestas'], true)) {
            $data['screen'] = 'cliente';
            $data['area'] = 'cliente';
            $data['section'] = ['mis-publicaciones' => 'publicaciones', 'propuestas-recibidas' => 'propuestas', 'comparar-propuestas' => 'propuestas', 'invitaciones-enviadas' => 'invitaciones'][$screen] ?? $screen;
        } elseif (str_starts_with($screen, 'presupuesto-') || $screen === 'presupuesto') {
            $data['screen'] = 'presupuesto';
            $data['area'] = 'cliente';
            $data['selected'] = $screen === 'presupuesto-vencido' ? $expired : $quote;
            // Estos estados permiten revisar las decisiones sin modificar presupuestos ni proyectos reales.
            if ($screen === 'presupuesto-anterior') {
                $data['selected'] = $previous;
            } elseif (in_array($screen, ['presupuesto-aceptado', 'presupuesto-rechazado'], true)) {
                $state = $screen === 'presupuesto-aceptado' ? 'accepted' : 'rejected';
                $data['selected'] = [...$quote, 'status' => $state, 'display_status' => $state];
                $data['quotes'][0] = $data['selected'];
                if ($state === 'accepted') {
                    $data['requests'][0]['status'] = 'closed';
                }
            }
        } elseif (in_array($screen, ['solicitud', 'solicitud-borrador', 'gestion-solicitud'], true)) {
            $data['screen'] = 'solicitud';
            $data['area'] = $screen === 'gestion-solicitud' ? 'gestion' : 'cliente';
            $data['selected'] = $screen === 'solicitud-borrador' ? $draft : $request;
        } elseif (str_starts_with($screen, 'g-')) {
            $data['screen'] = 'gestion';
            $data['area'] = 'gestion';
            $data['section'] = substr($screen, 2);
        } elseif (str_starts_with($screen, 'admin-')) {
            $data['screen'] = 'admin';
            $data['area'] = 'gestion';
            $data['section'] = substr($screen, 6);
            $data['administration'] = app(SynauxAdminController::class)->data();
        } elseif (in_array($screen, ['mis-propuestas', 'invitaciones-recibidas', 'mi-perfil', 'profesional-presupuestos', 'profesional-proyectos', 'profesional-presupuestos-cargados'], true)) {
            $data['screen'] = 'profesional';
            $data['area'] = 'profesional';
            $data['section'] = ['mis-propuestas' => 'propuestas', 'invitaciones-recibidas' => 'invitaciones', 'mi-perfil' => 'perfil', 'profesional-proyectos' => 'proyectos'][$screen] ?? 'presupuestos';
            if (in_array($screen, ['profesional-presupuestos', 'profesional-proyectos'], true)) {
                $data['quotes'] = [];
                $data['projects'] = [];
            }
            if ($screen === 'profesional-presupuestos-cargados') {
                $data['screen'] = 'editor-presupuesto';
                $data['selected'] = $quote;
            }
        } elseif (str_starts_with($screen, 'muro-')) {
            $data['screen'] = 'muro';
            $data['area'] = 'profesional';
        } elseif ($screen === 'propuesta-formulario') {
            $data['screen'] = 'oportunidad';
            $data['selected'] = $opportunities[0];
            $data['area'] = 'profesional';
        } elseif ($screen === 'publicar-revision') {
            $data['screen'] = 'publicar';
            $data['selected'] = [...$opportunities[0], 'status' => 'draft'];
            $data['area'] = 'cliente';
        } else {
            $data['screen'] = ['contacto-enviado' => 'contacto', 'ingreso-error' => 'ingreso', 'catalogo-vacio' => 'servicios'][$screen] ?? $screen;
            $data['selected'] = match ($screen) {
                'trabajo', 'editor-trabajo' => $works[0], 'oportunidad' => $opportunities[0], 'perfil-profesional' => $professionals[0], 'proyecto' => $project, 'editor-presupuesto' => $quote, default => null
            };
            $data['area'] = match ($screen) {
                'cliente', 'proyecto', 'publicar' => 'cliente', 'profesional', 'muro', 'oportunidad' => 'profesional', 'gestion', 'editor-presupuesto', 'editor-trabajo' => 'gestion', default => 'public'
            };
        }

        // Los datos de contacto de Gestión son ficticios y nunca se guardan en cuentas reales.
        if (in_array($screen, ['editor-presupuesto', 'g-solicitudes'], true)) {
            $data['requests'][0]['contact'] = ['name' => 'Cliente de ejemplo', 'email' => 'cliente@example.test', 'company' => 'Estudio Norte'];
        }
        if ($screen === 'editor-presupuesto') {
            $data['profile'] = [...$data['profile'], 'brand_name' => 'Synaux'];
        }

        // El recorrido de solicitud pertenece a la primera etapa y usa una estimación de ejemplo aislada.
        if (in_array($screen, ['publicar', 'publicar-revision'], true)) {
            $data['selectedEstimate'] = $estimate;
        }

        return Inertia::render($screen === 'inicio' ? 'Home' : ($data['screen'] === 'servicios' ? 'Catalog' : 'Workspace'), $data);
    }
}
