<?php

namespace App\Http\Controllers;

use App\Services\Catalog\ServiceCatalog;
use App\Services\Marketplace\MarketplaceQueries;
use App\Services\Portfolio\PortfolioQueries;
use App\Services\Solicitudes\ConsultasSolicitudes;
use App\Services\Solicitudes\RecorridoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PublicPageController extends Controller
{
    public function __construct(
        private ServiceCatalog       $catalog,
        private MarketplaceQueries   $marketplaceQueries,
        private PortfolioQueries     $portfolioQueries,
        private ConsultasSolicitudes $requestQueries,
        private RecorridoSolicitud   $journey,
    )
    {
    }

    // Selecciona una fila de servicios activos con las referencias vigentes del catálogo.
    public function inicio()
    {
        $featuredServices = $this->catalog->featuredServices();

        // La portada recibe solo los campos visibles de los últimos trabajos publicados.
        $featuredWorks = array_map(
            fn(array $work) => Arr::only($work, ['id', 'title', 'description', 'cover_path']),
            $this->portfolioQueries->works(limit: 2),
        );

        return Inertia::render('SiteHome', [
            'featuredServices' => array_map(
                fn(array $service) => Arr::only($service, ['id', 'name', 'category', 'summary', 'includes']),
                $featuredServices,
            ),
            'featuredReferences' => (object)$this->catalog->references(services: $featuredServices),
            'featuredWorks' => $featuredWorks,
        ]);
    }

    // Prepara una pantalla pública y agrega únicamente los datos que su recorrido necesita.
    public function show(Request $request, string $screen = 'servicios', ?string $id = null)
    {
        // Una cuenta administrativa recibe la salida hacia Gestión al abrir el estimador de cliente.
        abort_if($screen === 'estimador' && $request->user()?->is_admin, 403);
        $data = ['screen' => $screen, 'services' => $this->catalog->services()];
        // El catálogo y el estimador comparten la referencia de la versión vigente.
        if (in_array($screen, ['servicios', 'servicio', 'estimador'], true)) {
            $data['references'] = $this->catalog->references();
        }
        $ownProfile = $request->user() ? DB::table('professional_profiles')->where('user_id', $request->user()->id)->first() : null;
        $data['profile'] = $ownProfile ? $this->marketplaceQueries->profile($ownProfile) : null;
        if ($screen === 'servicio') {
            $data['selected'] = $this->catalog->service($id);
            // El catálogo puede indicar el segmento inicial, pero el servidor conserva el control de los precios.
            $data['initialClientType'] = in_array($request->query('cliente'), ['Particular', 'PyME', 'Empresa'], true)
                ? $request->query('cliente') : null;
        }
        if (in_array($screen, ['portfolio', 'trabajo'], true)) {
            $data['works'] = $this->portfolioQueries->works();
            $data['selected'] = $id ? collect($data['works'])->firstWhere('id', (int)$id) ?? abort(404) : null;
        }
        if (in_array($screen, ['muro', 'oportunidad'], true)) {
            $data['opportunities'] = $this->requestQueries->requests(null, public: true);
            $data['selected'] = $id ? collect($data['opportunities'])->firstWhere('id', (int)$id) : null;
            if ($id && !$data['selected']) {
                // Una invitación aceptada permite ver el contexto privado, sin revelar respuestas ni contacto.
                $record = DB::table('project_requests')->where('id', $id)->firstOrFail();
                $profile = $request->user() ? DB::table('professional_profiles')->where('user_id', $request->user()->id)->first() : null;
                $invited = $profile && DB::table('project_request_invitations')->where('project_request_id', $id)->where('professional_profile_id', $profile->id)->where('status', 'accepted')->exists();
                abort_unless($request->user() && ($record->user_id === $request->user()->id || $invited), 404);
                $data['selected'] = collect($this->requestQueries->requests($record->user_id, only: [(int)$id]))->firstWhere('id', (int)$id);
                unset($data['selected']['answers'], $data['selected']['estimate_id'], $data['selected']['user_id'], $data['selected']['contact']);
            }
            if ($id) {
                // Estos indicadores pertenecen al visitante actual; no publican la identidad del cliente.
                $data['selected']['is_owner'] = $request->user() && DB::table('project_requests')->where('id', $id)->where('user_id', $request->user()->id)->exists();
                $data['existingProposal'] = $ownProfile && DB::table('proposals')->where('project_request_id', $id)->where('professional_profile_id', $ownProfile->id)->exists();
            }
        }
        if (in_array($screen, ['profesionales', 'perfil-profesional'], true)) {
            $data['professionals'] = $this->marketplaceQueries->professionals();
            $data['selected'] = $id ? collect($data['professionals'])->firstWhere('id', (int)$id) ?? abort(404) : null;
            if ($id) {
                $data['works'] = collect($this->portfolioQueries->works())->where('professional_profile_id', (int)$id)->values();
                $data['requests'] = $request->user() ? $this->requestQueries->requests($request->user()->id) : [];
            }
        }
        if ($screen === 'estimador') {
            // Al volver de la cuenta, el resultado reabre el servicio del cálculo guardado en sesión.
            $step = max(1, min(4, (int)$request->query('paso', 1)));
            $pending = $request->session()->get('pending_estimate');
            $pendingSlug = $pending['service']['id'] ?? null;
            $slug = (string)$request->query('servicio', ($step === 4 ? $pendingSlug : null) ?? 'sitio-institucional');
            $service = $this->catalog->service($slug);
            $data['questions'] = $this->catalog->questions($service['database_id']);
            $data['selected'] = $service;
            // Solo preselecciona opciones activas del servicio: la URL no aporta precios ni respuestas libres.
            $data['initialAnswers'] = [];
            $clientType = $request->query('cliente');
            if (in_array($clientType, ['Particular', 'PyME', 'Empresa'], true)) {
                foreach ($data['questions'] as $question) {
                    $options = collect($question['options']);
                    if ($question['input_type'] === 'single_choice' && $options->pluck('label')->intersect(['Particular', 'PyME', 'Empresa'])->count() === 3) {
                        $data['initialAnswers'][$question['id']] = $options->firstWhere('label', $clientType)->id;
                    }
                }
            }
            // Las respuestas de otro servicio no se pueden reutilizar en el formulario actual.
            $data['pending'] = $pendingSlug === $slug ? $pending : null;
            $data['step'] = $step === 4 && !$data['pending'] ? 1 : $step;
            // El acceso anterior al estimador abre ahora el mismo recorrido público de solicitud.
            $data = $this->journey->pageData($request, $data);
        }
        // El formulario recibe solo un indicador; la dirección a retomar queda en la sesión.
        if ($screen === 'ingreso') {
            $data['loginRequired'] = $request->session()->has('url.intended');
        }
        if ($screen === 'restablecer') {
            $data['token'] = $id;
            $data['email'] = (string)$request->query('email');
        }

        return Inertia::render($screen === 'servicios' ? 'Catalog' : 'EspacioTrabajo', $data);
    }
}
