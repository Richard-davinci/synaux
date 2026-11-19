<?php

namespace App\Http\Controllers\Presupuestos;

use App\Http\Controllers\Controller;
use App\Services\Presupuestos\ConsultasPresupuestos;
use App\Services\Presupuestos\GestionPresupuestos;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Marketplace\MarketplaceQueries;
use App\Services\Notifications\NotificationCenter;
use App\Services\Projects\ProjectQueries;
use App\Services\Solicitudes\ConsultasSolicitudes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PresupuestoController extends Controller
{
    public function __construct(
        private ConsultasPresupuestos $budgetQueries,
        private MarketplaceQueries $marketplaceQueries,
        private ProjectQueries $projectQueries,
        private ConsultasSolicitudes $requestQueries,
        private NotificationCenter $notifications,
    ) {}

    // Carga el documento autorizado, su historial de versiones y el contexto de esa solicitud.
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $management = (bool) $user->is_admin;
        $selected = $this->budgetQueries->quotes($user->id, $management, id: $id)[0] ?? abort(404);
        $this->notifications->readEntity($user, 'presupuesto', $id);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'presupuesto',
            'area' => $management ? 'gestion' : 'cliente',
            'selected' => $selected,
            'quotes' => $this->budgetQueries->quotes($user->id, $management, budgetId: $selected['budget_id']),
            'requests' => $selected['request_id'] ? $this->requestQueries->requests($user->id, $management, only: [$selected['request_id']]) : [],
            'projects' => $this->projectQueries->projects([$selected]),
        ]);
    }

    // Conserva las solicitudes elegibles y las referencias históricas que necesita el editor.
    public function edit(Request $request, ServiceCatalog $catalog, int $id = 0)
    {
        $user = $request->user();
        $management = (bool) $user->is_admin;
        $selected = $id ? ($this->budgetQueries->quotes($user->id, $management, id: $id)[0] ?? abort(404)) : null;
        abort_if($selected && $selected['issuer_id'] !== $user->id, 403);
        $direct = $selected ? $selected['external'] : ($request->boolean('directo') && ! $request->integer('solicitud'));
        abort_if($direct && ! $management, 403);

        $profile = DB::table('professional_profiles')->where('user_id', $user->id)->first();
        $chosen = $profile && ! $management ? DB::table('proposals')->where('professional_profile_id', $profile->id)
            ->where('status', 'accepted')->pluck('project_request_id')->all() : [];
        $sources = $management ? $this->requestQueries->requests($user->id, true)
            : ($chosen ? $this->requestQueries->requests(null, true, only: $chosen) : []);
        $budgetRequests = array_values(array_filter($sources, fn ($source) => $source['user_id'] !== $user->id));

        // Recupera referencias de adicionales antiguos desde la versión vinculada a la solicitud.
        $referencesByTariff = [];
        $slugs = array_column($catalog->services($management), 'id', 'database_id');
        foreach ($budgetRequests as &$source) {
            $tariffId = $source['tariff_version_id'];
            if (! $tariffId) {
                continue;
            }
            foreach ($source['additional_services'] as &$extra) {
                if (array_key_exists('pricing_reference', $extra)) {
                    continue;
                }
                $references = $referencesByTariff[$tariffId] ??= $catalog->references((int) $tariffId);
                $slug = $slugs[$extra['service_id']] ?? '';
                $reference = $catalog->extraReference($references[$slug] ?? null, $source['answers'], $source['pricing_reference']['currency'] ?? 'ARS');
                if ($reference) {
                    $extra['pricing_reference'] = [...$reference, 'recovered' => true];
                }
            }
            unset($extra);
        }
        unset($source);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'editor-presupuesto',
            'area' => $management ? 'gestion' : 'cliente',
            'requestId' => (int) $request->query('solicitud', 0),
            'selected' => $selected,
            'directBudget' => $direct,
            'budgetRequests' => $budgetRequests,
            'profile' => $profile ? $this->marketplaceQueries->profile($profile) : null,
            'issuerDetails' => $user->issuerDetails($profile?->brand_name),
            'quotes' => $selected ? $this->budgetQueries->quotes($user->id, $management, budgetId: $selected['budget_id']) : [],
        ]);
    }

    // Delega el guardado y la emisión al servicio que controla sus versiones.
    public function store(Request $request, GestionPresupuestos $budgets)
    {
        return redirect('/presupuestos/'.$budgets->save($request))->with('notice', 'Versión guardada.');
    }

    // Redirige al proyecto cuando se acepta la versión vigente del presupuesto.
    public function decide(Request $request, GestionPresupuestos $budgets)
    {
        $id = $budgets->decide($request);

        return redirect($id ? '/proyectos/'.$id : '/cliente/presupuestos')->with('notice', $id ? 'Presupuesto aceptado y proyecto creado.' : 'Presupuesto rechazado.');
    }
}
