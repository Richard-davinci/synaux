<?php

namespace App\Http\Controllers\Estimates;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Estimates\EstimateStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EstimateController extends Controller
{
    public function __construct(private EstimateStore $estimates) {}

    // Recupera el cálculo de sesión y conduce al detalle o a una solicitud basada en esa estimación.
    public function store(Request $request): RedirectResponse
    {
        // Guardar una estimación personal forma parte del recorrido de cliente.
        abort_if($request->user()->is_admin, 403);

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

    // Valida las respuestas, calcula en el servidor y conserva el resultado en la sesión para continuar.
    public function calculate(Request $request, ServiceCatalog $catalog)
    {
        abort_if($request->user()?->is_admin, 403);

        $data = $request->validate(['service' => 'required|string', 'answers' => 'required|array|max:100']);
        $result = $catalog->calculate($data['service'], $data['answers']);
        $request->session()->put('pending_estimate', $result);

        return redirect('/estimador?paso=4&servicio='.$data['service']);
    }

    // Carga una estimación visible de la propia cuenta junto con el catálogo que usa su resumen.
    public function show(Request $request, ServiceCatalog $catalog, int $id)
    {
        $selected = $this->estimates->find($request->user()->id, $id) ?? abort(404);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'estimacion',
            'area' => $request->user()->is_admin ? 'gestion' : 'cliente',
            'selected' => $selected,
            'services' => $catalog->services((bool) $request->user()->is_admin),
        ]);
    }
}
