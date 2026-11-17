<?php

namespace App\Http\Controllers;

use App\Services\SynauxEstimates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SynauxEstimateController extends Controller
{
    public function __construct(private SynauxEstimates $estimates) {}

    // Recupera el cálculo de sesión y conduce al detalle o a una solicitud basada en esa estimación.
    public function store(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('pending_estimate');
        abort_unless($pending, 422, 'Primero completá el estimador.');
        $id = $this->estimates->save($request->user()->id, $pending);

        return redirect($request->boolean('to_request') ? '/solicitudes/nueva?estimacion='.$id : '/estimaciones/'.$id)
            ->with('notice', 'Estimación guardada. Podés crear una solicitud con estas respuestas.');
    }
}
