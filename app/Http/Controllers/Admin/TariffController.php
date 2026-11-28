<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TariffController extends Controller
{
    // Valida rangos y cobertura antes de crear una versión nueva e independiente del tarifario.
    public function store(Request $request)
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
        $validOptionIds = DB::table('estimator_options as o')->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
            ->whereNull('q.service_id')->where('q.question', '¿Para qué tipo de cliente?')
            ->where('q.active', 1)->where('o.active', 1)->pluck('o.id')->map(fn($id) => (int)$id);
        if (collect($data['segments'])->contains(fn($row) => !$validOptionIds->contains((int)$row['option_id']))) {
            throw ValidationException::withMessages(['segments' => 'Elegí una variante activa de la pregunta general de tipo de cliente.']);
        }
        $automaticServiceIds = DB::table('services')->where('active', 1)->where('estimation_mode', 'automatic')->pluck('id')->map(fn($id) => (int)$id);
        if (collect($data['segments'])->contains(fn($row) => !$automaticServiceIds->contains((int)$row['service_id']))) {
            throw ValidationException::withMessages(['segments' => 'Los precios por variante deben pertenecer a un servicio automático activo.']);
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
        DB::transaction(function () use ($data) {
            // Cada publicación crea una versión; no se editan los valores que originaron cálculos anteriores.
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
        });

        return back()->with('notice', 'Nueva versión del tarifario guardada.');
    }
}
