<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TariffController extends Controller
{
    // Editar los precios de un servicio publica otra versión completa sin reescribir el historial.
    public function storeServicePrices(Request $request)
    {
        $values = $request->validate([
            'service_id' => 'required|integer|exists:services,id', 'name' => 'required|string|max:255',
            'min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'max' => 'required|numeric|decimal:0,2|min:0|max:100000000',
            'components' => 'present|array|max:1000', 'components.*.id' => 'required|integer|distinct|exists:service_components,id',
            'components.*.min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'components.*.max' => 'required|numeric|decimal:0,2|min:0|max:100000000',
        ]);
        return DB::transaction(function () use ($request, $values) {
            $tariff = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->lockForUpdate()->first();
            $components = DB::table('service_components')->where('service_id', $values['service_id'])->where('active', 1)->pluck('id');
            if ($components->diff(array_column($values['components'], 'id'))->isNotEmpty()
                || collect(array_column($values['components'], 'id'))->diff($components)->isNotEmpty()) {
                throw ValidationException::withMessages(['components' => 'Completá únicamente los componentes activos de este servicio.']);
            }
            $services = $tariff ? DB::table('service_prices')->where('tariff_version_id', $tariff->id)->get()->map(fn ($p) => ['id' => $p->service_id, 'min' => $p->price_min, 'max' => $p->price_max])->keyBy('id') : collect();
            $prices = $tariff ? DB::table('component_prices')->where('tariff_version_id', $tariff->id)->get()->map(fn ($p) => ['id' => $p->service_component_id, 'min' => $p->price_min, 'max' => $p->price_max])->keyBy('id') : collect();
            $services[$values['service_id']] = ['id' => $values['service_id'], 'min' => $values['min'], 'max' => $values['max']];
            foreach ($values['components'] as $price) $prices[$price['id']] = $price;
            $request->merge(['name' => $values['name'], 'currency_code' => $tariff->currency_code ?? 'ARS', 'active' => true,
                'services' => $services->values()->all(), 'components' => $prices->values()->all()]);
            return $this->store($request);
        });
    }

    // Valida rangos y cobertura antes de crear una versión nueva e independiente del tarifario.
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])], 'active' => 'required|boolean',
            'services' => 'present|array|max:100', 'services.*.id' => 'required|integer|distinct|exists:services,id',
            'services.*.min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'services.*.max' => 'required|numeric|decimal:0,2|min:0|max:100000000',
            'components' => 'present|array|max:1000', 'components.*.id' => 'required|integer|distinct|exists:service_components,id',
            'components.*.min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'components.*.max' => 'required|numeric|decimal:0,2|min:0|max:100000000']);
        foreach (['services', 'components'] as $type) {
            foreach ($data[$type] as $index => $price) {
                if ($price['max'] < $price['min']) {
                    throw ValidationException::withMessages(["$type.$index.max" => 'El máximo debe ser mayor o igual al mínimo.']);
                }
            }
        }
        if ($data['active']) {
            $automatic = DB::table('services')->where('active', 1)->where('estimation_mode', 'automatic')->pluck('id');
            $required = DB::table('service_components')->whereIn('service_id', $automatic)->where('active', 1)->pluck('id');
            if ($automatic->diff(array_column($data['services'], 'id'))->isNotEmpty() || $required->diff(array_column($data['components'], 'id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['active' => 'Completá la base y todos los componentes de los servicios automáticos antes de activar.']);
            }
        }
        DB::transaction(function () use ($data) {
            // Cada publicación crea una versión; no se editan los valores que originaron cálculos anteriores.
            $id = DB::table('tariff_versions')->insertGetId(['name' => $data['name'], 'currency_code' => $data['currency_code'], 'effective_from' => now(), 'active' => $data['active'], 'created_at' => now()]);
            foreach (['services' => ['service_prices', 'service_id'], 'components' => ['component_prices', 'service_component_id']] as $type => [$table, $key]) {
                foreach ($data[$type] as $price) {
                    DB::table($table)->insert(['tariff_version_id' => $id, $key => $price['id'], 'price_min' => $price['min'], 'price_max' => $price['max']]);
                }
            }
        });

        return back()->with('notice', 'Nueva versión del tarifario guardada.');
    }
}
