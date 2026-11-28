<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PreciosCatalogo;
use App\Services\Tariffs\CotizacionCcl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TariffController extends Controller
{
    // El precio se cambia desde la ficha y solo puede afectar al servicio identificado en la ruta.
    public function guardarServicio(Request $request, int $service, PreciosCatalogo $catalogo)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);
        $precios = $request->validate(PreciosCatalogo::reglas())['precios'];

        DB::transaction(function () use ($catalogo, $service, $precios) {
            $catalogo->bloquearVersiones();
            $servicio = DB::table('services')->where('id', $service)->lockForUpdate()->firstOrFail();
            if ($servicio->estimation_mode !== 'automatic') {
                throw ValidationException::withMessages(['precios' => 'Este servicio se cotiza mediante un presupuesto profesional.']);
            }
            $catalogo->guardarServicio($service, $precios);
        }, 3);

        return redirect("/admin/servicios/$service/editar?config=precios")->with('notice', 'Precios del servicio guardados.');
    }

    // Consultar crea una propuesta temporal en esta sesión y no toca la cotización publicada.
    public function consultarCcl(Request $request, CotizacionCcl $cotizacion)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);

        try {
            $propuesta = $cotizacion->consultar();
        } catch (Throwable $error) {
            report($error);
            return response()->json(['message' => 'DolarApi no devolvió una cotización utilizable. Probá más tarde o cargala manualmente.'], 503)
                ->header('Cache-Control', 'no-store');
        }

        $propuesta['token'] = (string) Str::uuid();
        $request->session()->put('propuesta_ccl', [...$propuesta, 'expira' => now()->addMinutes(15)->timestamp]);

        return response()->json($propuesta)->header('Cache-Control', 'no-store');
    }

    // La confirmación aplica exactamente el valor que presentó el servidor.
    public function actualizarCcl(Request $request, CotizacionCcl $cotizacion)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);
        if ($request->filled('token')) {
            $token = $request->validate(['token' => 'required|uuid'])['token'];
            $propuesta = $request->session()->get('propuesta_ccl');
            if (!$propuesta || $propuesta['token'] !== $token || $propuesta['expira'] < now()->timestamp) {
                throw ValidationException::withMessages(['cotizacion' => 'La consulta venció o fue reemplazada. Consultá el CCL de nuevo.']);
            }
            $cambio = $cotizacion->aplicar($propuesta['valor'], CotizacionCcl::FUENTE_API, Carbon::parse($propuesta['fecha_fuente']));
            $request->session()->forget('propuesta_ccl');
        } else {
            $datos = $request->validate(['ccl_rate' => CotizacionCcl::REGLA_VALOR]);
            $cambio = $cotizacion->aplicar($datos['ccl_rate'], 'manual');
            $request->session()->forget('propuesta_ccl');
        }

        return redirect('/admin/tarifario')->with('notice', $cambio ? 'Cotización CCL aplicada al catálogo.' : 'La cotización consultada ya está aplicada.');
    }

    // Valida rangos y cobertura antes de crear una versión nueva e independiente del tarifario.
    public function store(Request $request, PreciosCatalogo $catalogo)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])],
            'ccl_rate' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:1000000'], 'active' => 'required|boolean',
            'services' => 'present|array|max:100', 'services.*.id' => 'required|integer|distinct|exists:services,id',
            'services.*.min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'services.*.max' => 'required|numeric|decimal:0,2|min:0|max:100000000',
            'components' => 'present|array|max:1000', 'components.*.id' => 'required|integer|distinct|exists:service_components,id',
            'components.*.min' => 'nullable|numeric|decimal:0,2|min:0|max:100000000', 'components.*.max' => 'nullable|numeric|decimal:0,2|min:0|max:100000000',
            'segments' => 'present|array|max:300', 'segments.*.service_id' => 'required|integer|exists:services,id',
            'segments.*.option_id' => 'required|integer|exists:estimator_options,id',
            'segments.*.min' => 'nullable|numeric|decimal:0,2|min:0|max:100000000',
            'segments.*.max' => 'nullable|numeric|decimal:0,2|min:0|max:100000000']);
        if ($data['currency_code'] === 'USD' && empty($data['ccl_rate'])) {
            throw ValidationException::withMessages(['ccl_rate' => 'Ingresá la cotización CCL usada para convertir a pesos.']);
        }
        foreach (['services', 'components', 'segments'] as $type) {
            foreach ($data[$type] as $index => $price) {
                if (isset($price['min'], $price['max']) && $price['max'] < $price['min']) {
                    throw ValidationException::withMessages(["$type.$index.max" => 'El máximo debe ser mayor o igual al mínimo.']);
                }
            }
        }
        $segmentKeys = collect($data['segments'])->map(fn($row) => $row['service_id'] . ':' . $row['option_id']);
        if ($segmentKeys->unique()->count() !== $segmentKeys->count()) {
            throw ValidationException::withMessages(['segments' => 'No repitas el precio de un mismo servicio y tipo de cliente.']);
        }
        $validOptionIds = $catalogo->segmentos()->pluck('id')->map(fn($id) => (int)$id);
        if (collect($data['segments'])->contains(fn($row) => !$validOptionIds->contains((int)$row['option_id']))) {
            throw ValidationException::withMessages(['segments' => 'Elegí una variante activa de la pregunta general de tipo de cliente.']);
        }
        $automaticServiceIds = DB::table('services')->where('active', 1)->where('estimation_mode', 'automatic')->pluck('id')->map(fn($id) => (int)$id);
        $allAutomaticServiceIds = DB::table('services')->where('estimation_mode', 'automatic')->pluck('id')->map(fn($id) => (int)$id);
        if (collect($data['segments'])->contains(fn($row) => !$allAutomaticServiceIds->contains((int)$row['service_id']))) {
            throw ValidationException::withMessages(['segments' => 'Los precios por variante deben pertenecer a un servicio automático.']);
        }
        if ($data['active']) {
            $automatic = $automaticServiceIds;
            $required = DB::table('service_components')->whereIn('service_id', $automatic)->where('active', 1)
                ->where('slug', '!=', 'segmento-tarifario-2026-11')->pluck('id');
            $componentesCompletos = collect($data['components'])->filter(fn($precio) => isset($precio['min'], $precio['max']))->pluck('id');
            if ($automatic->diff(array_column($data['services'], 'id'))->isNotEmpty() || $required->diff($componentesCompletos)->isNotEmpty()) {
                throw ValidationException::withMessages(['active' => 'Completá la base y todos los componentes de los servicios automáticos antes de activar.']);
            }
            // Evita activar una versión que no pueda cotizar cada opción general del catálogo.
            $providedSegments = collect($data['segments'])->filter(fn($precio) => isset($precio['min'], $precio['max']))
                ->map(fn($precio) => $precio['service_id'] . ':' . $precio['option_id']);
            $missingSegments = false;
            foreach ($automatic as $serviceId) {
                foreach ($validOptionIds as $optionId) {
                    if (!$providedSegments->contains($serviceId . ':' . $optionId)) $missingSegments = true;
                }
            }
            if ($validOptionIds->isNotEmpty() && $missingSegments) {
                throw ValidationException::withMessages(['segments' => 'Completá el precio de cada tipo de cliente para todos los servicios automáticos.']);
            }
        }
        DB::transaction(function () use ($data, $catalogo) {
            // El acceso anterior comparte el bloqueo con la edición por servicio y el CCL.
            $catalogo->bloquearVersiones();
            // Cada publicación crea una versión; no se editan los valores que originaron cálculos anteriores.
            if ($data['active']) {
                // Solo una versión queda vigente; los precios previos permanecen intactos para el historial.
                DB::table('tariff_versions')->where('active', 1)->update(['active' => false]);
            }
            $id = DB::table('tariff_versions')->insertGetId([
                'name' => $data['name'], 'currency_code' => $data['currency_code'], 'ccl_rate' => $data['ccl_rate'] ?? null,
                'ccl_rate_at' => isset($data['ccl_rate']) ? now() : null,
                'effective_from' => now(), 'active' => $data['active'], 'created_at' => now(),
            ]);
            foreach (['services' => ['service_prices', 'service_id'], 'components' => ['component_prices', 'service_component_id']] as $type => [$table, $key]) {
                foreach ($data[$type] as $price) {
                    // Las versiones en preparación conservan solo las filas con importes cargados.
                    if ($type === 'components' && (!isset($price['min'], $price['max']))) continue;
                    DB::table($table)->insert(['tariff_version_id' => $id, $key => $price['id'], 'price_min' => $price['min'], 'price_max' => $price['max']]);
                }
            }
            foreach ($data['segments'] as $price) {
                if (!isset($price['min'], $price['max'])) continue;
                DB::table('service_segment_prices')->insert([
                    'tariff_version_id' => $id, 'service_id' => $price['service_id'],
                    'estimator_option_id' => $price['option_id'], 'price_min' => $price['min'], 'price_max' => $price['max'],
                ]);
            }
        }, 3);

        return redirect('/admin/tarifario')->with('notice', 'Nueva versión del tarifario guardada.');
    }
}
