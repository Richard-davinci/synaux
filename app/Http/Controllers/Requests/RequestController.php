<?php

namespace App\Http\Controllers\Requests;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Estimates\EstimateStore;
use App\Services\Notifications\NotificationCenter;
use App\Services\Requests\RequestJourney;
use App\Services\Requests\RequestQueries;
use App\Services\Requests\RequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RequestController extends Controller
{
    public function __construct(private RequestService $requests) {}

    // Valida el formulario autenticado y redirige al detalle del borrador o la solicitud publicada.
    public function store(Request $request): RedirectResponse
    {
        // La cuenta de Gestión puede responder pedidos de clientes, pero no emitir pedidos propios.
        abort_if($request->user()->is_admin, 403);

        $data = $request->validate([
            'id' => 'nullable|integer',
            'estimate_id' => 'nullable|integer',
            'answers' => 'sometimes|array|max:100',
            'service_id' => 'required|integer|exists:services,id',
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10|max:10000',
            'visibility' => ['required', Rule::in(['private', 'public'])],
            'category' => 'nullable|string|max:255',
            'skills' => 'nullable|string|max:1000',
            'budget_reference' => 'nullable|string|max:255',
            'desired_deadline' => 'nullable|string|max:255',
            'publish' => 'required|boolean',
            'contact' => 'sometimes|array:name,email,company,phone,address',
            'contact.name' => 'required_with:contact|string|max:255',
            'contact.email' => 'required_with:contact|email|max:254',
            'contact.company' => 'nullable|string|max:255',
            'contact.phone' => 'nullable|string|max:50',
            'contact.address' => 'nullable|string|max:500',
            'additional_services' => 'sometimes|array|max:20',
            'additional_services.*.service_id' => 'required|integer|distinct|exists:services,id',
            'additional_services.*.quantity' => 'required|integer|min:1|max:1000',
            'additional_services.*.notes' => 'nullable|string|max:2000',
            'additional_services.*.answers' => 'sometimes|array|max:100',
        ]);

        // La publicación en el muro pertenece al marketplace y requiere acceso a la segunda etapa.
        if ($data['visibility'] === 'public' && ! config('synaux.stage_two_enabled')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['visibility' => 'En esta etapa las solicitudes se envían de forma privada a synaux.']);
        }

        // Las habilidades llegan separadas por comas y se guardan sin espacios externos ni duplicados.
        $data['skills'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['skills'] ?? '')))));
        // La estimación integrada se calcula en el servidor y se guarda junto al pedido en una transacción.
        $id = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data) {
            $journey = app(\App\Services\Requests\RequestJourney::class);
            $pending = $journey->pending($request);
            if (array_key_exists('answers', $data) && empty($data['estimate_id'])) {
                $calculation = $journey->calculate($data);
                if ($pending && ! ($pending['form']['estimate_id'] ?? null) && $pending['calculation']['service']['database_id'] === (int) $data['service_id']
                    && (int) ($pending['form']['id'] ?? 0) === (int) ($data['id'] ?? 0)
                    && $pending['calculation']['answers'] == $calculation['answers']) {
                    $calculation = $pending['calculation'];
                }
                $data['estimate_id'] = app(\App\Services\Estimates\EstimateStore::class)->save($request->user()->id, $calculation);
            }

            return $this->requests->save($request->user()->id, $data, $pending);
        });
        $request->session()->forget('pending_request');

        return redirect('/solicitudes/'.$id)->with('notice', $data['publish'] ? 'Solicitud enviada.' : 'Borrador guardado.');
    }

    // Abre el formulario público con la estimación elegida por su titular, si corresponde.
    public function create(Request $request, ServiceCatalog $catalog, EstimateStore $estimates, RequestJourney $journey)
    {
        if ($request->user()?->is_admin) {
            return redirect('/gestion');
        }

        $id = (int) $request->query('estimacion', 0);
        if ($id && ! $request->user()) {
            return redirect()->guest('/ingresar');
        }

        return Inertia::render('EspacioTrabajo', $journey->pageData($request, [
            'services' => $catalog->services(),
            'references' => $catalog->references(),
            'selectedEstimate' => $id ? ($estimates->find($request->user()->id, $id) ?? abort(404)) : null,
        ]));
    }

    // Los borradores se reabren con las preguntas y la estimación que usó el titular.
    public function show(Request $request, RequestQueries $requestQueries, ServiceCatalog $catalog, EstimateStore $estimates, RequestJourney $journey, NotificationCenter $notifications, int $id)
    {
        $user = $request->user();
        $management = (bool) $user->is_admin;
        $selected = $requestQueries->requests($user->id, $management, only: [$id])[0] ?? abort(404);
        $data = ['screen' => 'solicitud', 'area' => $management ? 'gestion' : 'cliente', 'selected' => $selected];

        if ($selected['status'] === 'draft') {
            $data['services'] = $catalog->services($management);
            $data['references'] = $catalog->references();
            $data['selectedEstimate'] = $selected['estimate_id']
                ? $estimates->find($user->id, $selected['estimate_id'], includeDeleted: true)
                : null;
            $data['estimates'] = $data['selectedEstimate'] && ! $data['selectedEstimate']['deleted_at']
                ? [$data['selectedEstimate']]
                : [];
            $data = $journey->pageData($request, $data, $id);
        }

        $notifications->readEntity($user, 'solicitud', $id);

        return Inertia::render('EspacioTrabajo', $data);
    }

    // Conserva el proyecto en la sesión antes del ingreso y vuelve después al paso de contacto.
    public function prepare(Request $request, RequestJourney $journey)
    {
        abort_if($request->user()?->is_admin, 403);

        return redirect($journey->prepare($request));
    }

    // Comprueba propiedad o administración antes de revisar, cerrar o cancelar una solicitud.
    public function updateStatus(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer', 'status' => ['required', Rule::in(['review', 'closed', 'cancelled'])]]);
        $r = DB::table('project_requests')->where('id', $d['id'])->firstOrFail();
        abort_unless($r->user_id === $request->user()->id || $request->user()->is_admin, 403);
        if ($d['status'] === 'review') {
            abort_unless($request->user()->is_admin && $r->status === 'open', 403);
            DB::table('project_requests')->where('id', $r->id)->update(['reviewed_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('project_requests')->where('id', $r->id)->update(['status' => $d['status'], 'updated_at' => now()]);
        }

        return back()->with('notice', 'Estado actualizado.');
    }
}
