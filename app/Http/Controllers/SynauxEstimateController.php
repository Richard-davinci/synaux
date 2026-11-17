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
    // Elimina de la lista solo una estimación propia; los documentos vinculados conservan su referencia histórica.
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->validate(['confirmed' => 'required|accepted']);
        $estimate = \Illuminate\Support\Facades\DB::table('estimates')->where('id', $id)
            ->where('user_id', $request->user()->id)->whereNull('deleted_at')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('estimates')->where('id', $estimate->id)
            ->where('user_id', $request->user()->id)->update(['deleted_at' => now(), 'updated_at' => now()]);

        return redirect('/cliente/estimaciones')->with('notice', 'Estimación eliminada de tu lista. Las solicitudes vinculadas conservan su historial.');
    }
}
