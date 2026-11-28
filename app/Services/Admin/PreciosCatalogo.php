<?php

namespace App\Services\Admin;

use App\Services\Catalog\TipoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreciosCatalogo
{
    // El catálogo y la administración consultan el mismo tarifario publicado.
    public function vigente()
    {
        return DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    public function segmentos()
    {
        return TipoCliente::opcionesActivas();
    }

    private function conceptos(int $servicio, bool $soloIncluidos = false)
    {
        return DB::table('service_components')->where('service_id', $servicio)->where('active', 1)
            ->where('slug', '!=', TipoCliente::CONCEPTO_HEREDADO)
            ->when($soloIncluidos, fn($consulta) => $consulta->where('is_default', 1))->pluck('id');
    }

    public static function reglas(): array
    {
        $importe = 'required|numeric|decimal:0,2|min:0|max:100000000';

        return [
            'precios' => 'required|array:segmentos,conceptos',
            'precios.segmentos' => 'required|array|max:100',
            'precios.conceptos' => 'present|array|max:1000',
            'precios.segmentos.*.id' => 'required|integer|distinct',
            'precios.segmentos.*.min' => $importe,
            'precios.segmentos.*.max' => $importe,
            'precios.conceptos.*.id' => 'required|integer|distinct',
            'precios.conceptos.*.min' => 'nullable|numeric|decimal:0,2|min:0|max:100000000',
            'precios.conceptos.*.max' => 'nullable|numeric|decimal:0,2|min:0|max:100000000',
        ];
    }

    // Las operaciones comparten el bloqueo de la primera versión para copiar siempre la última publicada.
    public function bloquearVersiones(): void
    {
        DB::table('tariff_versions')->orderBy('id')->lockForUpdate()->first();
    }

    // Una edición de importes crea una versión de precios para conservar el cálculo histórico.
    public function guardarServicio(int $servicio, array $precios): void
    {
        $vigente = $this->vigente();
        $segmentos = $this->segmentos()->pluck('id');
        $conceptos = $this->conceptos($servicio);
        if ($segmentos->count() !== count(TipoCliente::ETIQUETAS) || $segmentos->diff(array_column($precios['segmentos'], 'id'))->isNotEmpty()
            || count($precios['segmentos']) !== $segmentos->count()
            || $conceptos->diff(array_column($precios['conceptos'], 'id'))->isNotEmpty()
            || count($precios['conceptos']) !== $conceptos->count()) {
            throw ValidationException::withMessages(['precios' => 'Completá los tipos de cliente y los adicionales activos de este servicio.']);
        }
        // Los opcionales pueden quedar a cotizar. Los incluidos necesitan un importe explícito, incluso si es cero.
        $incluidos = $this->conceptos($servicio, soloIncluidos: true);
        foreach ($precios as $grupo => $filas) {
            foreach ($filas as $i => $precio) {
                $min = $precio['min'] ?? null;
                $max = $precio['max'] ?? null;
                if (($min === null) !== ($max === null) || ($min === null && $incluidos->contains($precio['id']) && $grupo === 'conceptos')) {
                    throw ValidationException::withMessages(["precios.$grupo.$i." . ($min === null ? 'min' : 'max') => 'Completá el importe del adicional.']);
                }
                if ($min !== null && $max < $min) {
                    throw ValidationException::withMessages(["precios.$grupo.$i.max" => 'El máximo debe ser mayor o igual al mínimo.']);
                }
            }
        }
        if ($vigente && $vigente->currency_code !== 'USD'
            && DB::table('service_prices')->where('tariff_version_id', $vigente->id)->exists()) {
            throw ValidationException::withMessages(['precios' => 'La tarifa vigente contiene precios en otra moneda. Para operar en USD primero hay que migrar esa tarifa con una cotización acordada.']);
        }

        $version = $this->copiarVersion($vigente, $servicio);
        // La variante ya representa el importe inicial; la fila base es un respaldo técnico sin cargo.
        DB::table('service_prices')->insert([
            'tariff_version_id' => $version, 'service_id' => $servicio, 'price_min' => 0, 'price_max' => 0,
        ]);
        foreach (['segmentos' => ['service_segment_prices', 'estimator_option_id'], 'conceptos' => ['component_prices', 'service_component_id']] as $grupo => [$tabla, $columna]) {
            foreach ($precios[$grupo] as $precio) {
                if (!isset($precio['min'], $precio['max'])) continue;
                $fila = [
                    'tariff_version_id' => $version, $columna => $precio['id'],
                    'price_min' => $precio['min'], 'price_max' => $precio['max'],
                ];
                if ($grupo === 'segmentos') $fila['service_id'] = $servicio;
                DB::table($tabla)->insert($fila);
            }
        }
    }

    public function tieneCobertura(int $servicio): bool
    {
        $version = $this->vigente()?->id;
        if (!$version) return false;

        $segmentos = $this->segmentos()->pluck('id');
        $cargados = DB::table('service_segment_prices')->where('tariff_version_id', $version)
            ->where('service_id', $servicio)->pluck('estimator_option_id');
        $conceptos = DB::table('component_prices')->where('tariff_version_id', $version)->pluck('service_component_id');

        return DB::table('service_prices')->where('tariff_version_id', $version)->where('service_id', $servicio)->exists()
            && $segmentos->count() === count(TipoCliente::ETIQUETAS) && $segmentos->diff($cargados)->isEmpty()
            && $this->conceptos($servicio, soloIncluidos: true)->diff($conceptos)->isEmpty();
    }

    private function copiarVersion($vigente, int $servicio): int
    {
        $ahora = now();
        DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', $ahora)->update(['active' => false]);
        $version = DB::table('tariff_versions')->insertGetId([
            'name' => 'Precios del catálogo', 'currency_code' => 'USD',
            'effective_from' => $ahora, 'active' => true, 'created_at' => $ahora,
        ]);
        if (!$vigente) return $version;

        $columnas = ['price_min', 'price_max'];
        foreach ([
            'service_prices' => ['service_id'],
            'service_segment_prices' => ['service_id', 'estimator_option_id'],
            'component_prices' => ['service_component_id'],
        ] as $tabla => $claves) {
            $origen = DB::table($tabla)->where('tariff_version_id', $vigente->id)
                ->selectRaw('? as tariff_version_id', [$version])->addSelect([...$claves, ...$columnas]);
            if ($tabla === 'component_prices') {
                $origen->whereNotIn('service_component_id', DB::table('service_components')->where('service_id', $servicio)->select('id'));
            } else {
                $origen->where('service_id', '!=', $servicio);
            }
            DB::table($tabla)->insertUsing(['tariff_version_id', ...$claves, ...$columnas], $origen);
        }
        return $version;
    }
}
