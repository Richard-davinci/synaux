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
        $data = $request->validate([
            'id' => 'nullable|integer',
            'estimate_id' => 'nullable|integer',
            'service_id' => 'required|integer|exists:services,id',
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10|max:10000',
            'visibility' => ['required', Rule::in(['private', 'public'])],
            'category' => 'nullable|string|max:255',
            'skills' => 'nullable|string|max:1000',
            'budget_reference' => 'nullable|string|max:255',
            'desired_deadline' => 'nullable|string|max:255',
            'publish' => 'required|boolean',
        ]);

        // La publicación en el muro pertenece al marketplace y requiere acceso a la segunda etapa.
        if ($data['visibility'] === 'public' && ! config('synaux.stage_two_enabled') && ! $request->user()->is_admin) {
            throw \Illuminate\Validation\ValidationException::withMessages(['visibility' => 'En esta etapa las solicitudes se envían de forma privada a Synaux.']);
        }

        // Las habilidades llegan separadas por comas y se guardan sin espacios externos ni duplicados.
        $data['skills'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['skills'] ?? '')))));
        $id = $this->requests->save($request->user()->id, $data);

        return redirect('/solicitudes/'.$id)->with('notice', $data['publish'] ? 'Solicitud enviada.' : 'Borrador guardado.');
    }
}
