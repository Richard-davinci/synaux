<?php

namespace App\Services\Tariffs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CotizacionCcl
{
    public const ENDPOINT = 'https://dolarapi.com/v1/dolares/contadoconliqui';
    public const FUENTE_API = 'dolarapi';
    public const REGLA_VALOR = 'required|numeric|decimal:0,4|min:0.0001|max:1000000';

    // La lectura del catálogo depende solo de la base local.
    public function vigente(?object $tarifaAnterior = null): ?array
    {
        $fila = DB::table('cotizacion_ccl')->find(1);
        if ($fila?->valor === null) {
            // Admite una tarifa heredada que se haya restaurado después de migrar.
            $valor = $tarifaAnterior?->ccl_rate ?? $tarifaAnterior?->source_rate;
            $fecha = $tarifaAnterior?->ccl_rate_at ?? $tarifaAnterior?->source_checked_at ?? $tarifaAnterior?->effective_from;
            return $valor ? [
                'valor' => $valor, 'fuente' => 'registrada', 'fecha_fuente' => null,
                'aplicada_en' => $fecha ? Carbon::parse($fecha)->toISOString() : null,
            ] : null;
        }

        return [
            'valor' => $fila->valor,
            'fuente' => $fila->fuente,
            'fecha_fuente' => $fila->fecha_fuente ? Carbon::parse($fila->fecha_fuente)->toISOString() : null,
            'aplicada_en' => Carbon::parse($fila->aplicada_en)->toISOString(),
        ];
    }

    // Se consulta solo el CCL de venta. Un resultado válido se reutiliza hasta tres horas.
    public function consultar(): array
    {
        return Cache::remember('synaux:cotizacion-ccl-venta', now()->addHours(3), function () {
            $datos = Http::acceptJson()->connectTimeout(3)->timeout(6)->get(self::ENDPOINT)->throw()->json();
            $valor = $datos['venta'] ?? null;
            $fecha = $datos['fechaActualizacion'] ?? null;
            if (($datos['casa'] ?? '') !== 'contadoconliqui' || !is_numeric($valor)
                || $valor < 0.0001 || $valor > 1000000 || !is_string($fecha)
                || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $fecha)) {
                throw new RuntimeException('DolarApi entregó una cotización CCL incompleta.');
            }
            $fecha = Carbon::parse($fecha);
            if (!$this->fechaUtilizable($fecha)) {
                throw new RuntimeException('DolarApi entregó una cotización CCL fuera de fecha.');
            }

            return ['valor' => round((float) $valor, 4), 'fecha_fuente' => $fecha->toISOString()];
        });
    }

    // Manual y API comparten el registro aplicado, con fechas y origen independientes.
    public function aplicar(string|float $valor, string $fuente, ?Carbon $fecha = null): bool
    {
        return DB::transaction(function () use ($valor, $fuente, $fecha) {
            $vigente = DB::table('cotizacion_ccl')->where('id', 1)->lockForUpdate()->firstOrFail();
            $fechaAnterior = $vigente->fecha_fuente ? Carbon::parse($vigente->fecha_fuente) : null;
            if ($fecha && (!$this->fechaUtilizable($fecha) || ($fechaAnterior && $fecha->lt($fechaAnterior)))) {
                throw ValidationException::withMessages(['cotizacion' => 'La cotización consultada quedó desactualizada. Volvé a consultar.']);
            }
            if ((float) $vigente->valor === (float) $valor && $vigente->fuente === $fuente
                && (!$fecha || $fechaAnterior?->equalTo($fecha))) {
                return false;
            }

            DB::table('cotizacion_ccl')->where('id', 1)->update([
                'valor' => $valor,
                'fuente' => $fuente,
                'fecha_fuente' => $fecha?->copy()->setTimezone(config('app.timezone')),
                'aplicada_en' => now(),
            ]);
            return true;
        }, 3);
    }

    private function fechaUtilizable(Carbon $fecha): bool
    {
        // Permite fines de semana y feriados sin admitir una cotización demasiado vieja o futura.
        return $fecha->betweenIncluded(now()->subDays(7), now()->addMinutes(5));
    }
}
