<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\TipoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SimuladorEstimacionesController extends Controller
{
    // Reutiliza el motor del catálogo y devuelve solo una vista previa temporal, sin persistir datos comerciales.
    public function preview(Request $request, int $service, ServiceCatalog $catalog)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);

        $servicio = collect($catalog->services(true))->firstWhere('database_id', $service) ?? abort(404);
        $preguntas = $catalog->questions($service);
        $data = $request->validate([
            'answers' => ['present', 'array', 'max:100'],
        ]);
        $idsPregunta = array_map('strval', array_column($preguntas, 'id'));
        $idsEnviados = array_map('strval', array_keys($data['answers']));
        if ($desconocidas = array_diff($idsEnviados, $idsPregunta)) {
            throw ValidationException::withMessages([
                'answers' => 'La simulación incluye respuestas de preguntas que no pertenecen a este servicio.',
            ]);
        }

        // El motor recibe el servicio por su slug y las preguntas guardadas para validar cada opción seleccionada.
        $resultado = $catalog->calculate($servicio['id'], $data['answers'], $preguntas, true, true);
        $preguntas = $catalog->visibleQuestions($preguntas, $data['answers']);
        $tarifa = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
        $preciosSegmento = $tarifa ? DB::table('service_segment_prices')->where('tariff_version_id', $tarifa->id)
            ->where('service_id', $service)->get()->keyBy('estimator_option_id') : collect();
        $preguntaCliente = collect($preguntas)->first(fn($pregunta) => ($pregunta['clave_sistema'] ?? null) === TipoCliente::PREGUNTA);
        $idSegmento = $preguntaCliente ? ($data['answers'][$preguntaCliente['id']] ?? null) : null;
        $segmento = $preguntaCliente ? collect($preguntaCliente['options'])->first(fn($opcion) => (string)$opcion->id === (string)$idSegmento) : null;
        $preciosBase = $tarifa ? DB::table('service_prices')->where('tariff_version_id', $tarifa->id)
            ->where('service_id', $service)->first() : null;
        $preguntasFaltantes = collect($preguntas)->filter(function ($pregunta) use ($data) {
            return $pregunta['required'] && (!array_key_exists($pregunta['id'], $data['answers'])
                || $data['answers'][$pregunta['id']] === null || $data['answers'][$pregunta['id']] === ''
                || $data['answers'][$pregunta['id']] === []);
        })->map(fn($pregunta) => $pregunta['question'])->values()->all();

        // Cada renglón indica qué respuesta lo activó; esto explica el cálculo sin volver a calcularlo en el controlador.
        $idsOpcionSeleccionada = [];
        foreach ($resultado['answers'] as $respuesta) {
            $valor = $respuesta['value'];
            foreach (is_array($valor) ? $valor : [$valor] as $idOpcion) {
                if (is_int($idOpcion) || (is_string($idOpcion) && ctype_digit($idOpcion))) $idsOpcionSeleccionada[] = (int)$idOpcion;
            }
        }
        $reglas = $idsOpcionSeleccionada ? DB::table('estimation_rules as r')
            ->join('estimator_options as o', 'o.id', '=', 'r.estimator_option_id')
            ->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
            ->whereIn('r.estimator_option_id', $idsOpcionSeleccionada)->where('r.active', 1)
            ->where('o.active', 1)->where('q.active', 1)
            ->select('r.service_component_id', 'q.question', 'o.label')->get() : collect();
        $respuestas = collect($resultado['answers'])->keyBy('id');
        $renglones = collect($resultado['items'])->map(function ($item) use ($reglas, $respuestas, $preguntas, $data) {
            $activadores = $reglas->where('service_component_id', $item['component_id'])
                ->map(fn($regla) => $regla->question . ' · ' . $regla->label)->all();
            foreach ($preguntas as $pregunta) {
                if ((int)($pregunta['quantity_component_id'] ?? 0) !== (int)$item['component_id']) continue;
                $respuesta = $respuestas->get($pregunta['id']);
                if ($respuesta) $activadores[] = $pregunta['question'] . ' · cantidad ' . $respuesta['label'];
            }
            return [...$item, 'activado_por' => array_values(array_unique($activadores))];
        })->values();
        $adicionalesMin = $renglones->sum(fn($item) => $item['subtotal_min'] ?? 0);
        $adicionalesMax = $renglones->sum(fn($item) => $item['subtotal_max'] ?? 0);
        $adicionalSinPrecio = $renglones->contains(fn($item) => $item['min'] === null || $item['max'] === null);
        $codigosEstado = [];
        if (!$tarifa) $codigosEstado[] = 'error_configuracion';
        if ($preguntasFaltantes) $codigosEstado[] = 'respuestas_pendientes';
        if ($preciosSegmento->isNotEmpty() && !$segmento) $codigosEstado[] = 'segmento_pendiente';
        if ($preciosSegmento->isNotEmpty() && $segmento && !$preciosSegmento->has($segmento->id)) $codigosEstado[] = 'segmento_sin_precio';
        if ($servicio['estimation_mode'] === 'automatic' && $preciosSegmento->isEmpty() && !$preciosBase) $codigosEstado[] = 'precio_base_sin_configurar';
        if ($adicionalSinPrecio) $codigosEstado[] = 'conceptos_sin_precio';
        if ($servicio['estimation_mode'] !== 'automatic') $codigosEstado[] = 'revision_profesional';
        if (!$codigosEstado) $codigosEstado[] = 'listo';

        // El subtotal conserva solo los importes conocidos y se etiqueta como parcial si falta algo para cerrar el precio.
        $listo = $codigosEstado === ['listo'];
        $subtotalMin = $resultado['subtotal_min'];
        $subtotalMax = $resultado['subtotal_max'];
        $moneda = $resultado['currency'];
        $tarifaCcl = $tarifa && in_array($tarifa->currency_code, ['USD', 'ARS'], true)
            ? app(\App\Services\Tariffs\CotizacionCcl::class)->vigente($tarifa) : null;

        return response()->json([
            'estado' => $codigosEstado,
            'preguntas_faltantes' => $preguntasFaltantes,
            'servicio' => ['id' => $service, 'nombre' => $servicio['name'], 'publicado' => (bool)$servicio['active']],
            'base' => [
                'segmento' => $segmento?->label,
                'minimo' => $segmento ? ($preciosSegmento->get($segmento->id)?->price_min) : ($preciosBase?->price_min),
                'maximo' => $segmento ? ($preciosSegmento->get($segmento->id)?->price_max) : ($preciosBase?->price_max),
            ],
            'adicionales' => $renglones->all(),
            'total_adicionales' => [
                'minimo' => round($adicionalesMin, 2), 'maximo' => round($adicionalesMax, 2),
                'parcial' => $adicionalSinPrecio,
            ],
            'subtotal_conocido' => $subtotalMin === null ? null : ['minimo' => $subtotalMin, 'maximo' => $subtotalMax, 'parcial' => !$listo],
            'total_estimado' => $listo ? ['minimo' => $resultado['min'], 'maximo' => $resultado['max']] : null,
            'moneda' => $moneda,
            'ccl' => $tarifaCcl ? ['valor' => $tarifaCcl['valor'], 'fuente' => $tarifaCcl['fuente']] : null,
            'version_tarifario' => $tarifa ? ['id' => $tarifa->id, 'nombre' => $tarifa->name] : null,
            'requiere_revision' => !$listo,
        ]);
    }
}
