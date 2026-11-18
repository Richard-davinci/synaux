<?php

namespace App\Http\Controllers;

use App\Services\SynauxRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SynauxRequestController extends Controller
{
    public function __construct(private SynauxRequests $requests) {}

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
        ]);

        // La publicación en el muro pertenece al marketplace y requiere acceso a la segunda etapa.
        if ($data['visibility'] === 'public' && ! config('synaux.stage_two_enabled')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['visibility' => 'En esta etapa las solicitudes se envían de forma privada a synaux.']);
        }

        // Las habilidades llegan separadas por comas y se guardan sin espacios externos ni duplicados.
        $data['skills'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['skills'] ?? '')))));
        // La estimación integrada se calcula en el servidor y se guarda junto al pedido en una transacción.
        $id = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data) {
            if (! empty($data['answers']) || (array_key_exists('answers', $data) && empty($data['estimate_id']))) {
                if (empty($data['estimate_id'])) {
                    $journey = app(\App\Services\SynauxRequestJourney::class);
                    $calculation = $journey->calculate($data);
                    $pending = $journey->pending($request);
                    if ($pending && $pending['calculation']['service']['database_id'] === (int) $data['service_id']
                        && $pending['calculation']['answers'] == $calculation['answers']) {
                        $calculation = $pending['calculation'];
                    }
                    $data['estimate_id'] = app(\App\Services\SynauxEstimates::class)->save($request->user()->id, $calculation);
                }
            }
            return $this->requests->save($request->user()->id, $data);
        });
        $request->session()->forget('pending_request');

        return redirect('/solicitudes/'.$id)->with('notice', $data['publish'] ? 'Solicitud enviada.' : 'Borrador guardado.');
    }
}
