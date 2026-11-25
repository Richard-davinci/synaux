<?php

namespace App\Services\Presupuestos;

use Illuminate\Validation\ValidationException;

class PlanPago
{
    public const MODALIDADES = ['sena_saldo', 'al_finalizar'];

    // El reparto se calcula sobre el total final. El saldo absorbe cualquier diferencia de redondeo.
    public static function preparar(array $datos, int $total): ?array
    {
        $modalidad = $datos['modalidad_pago'] ?? null;
        if ($modalidad === null) return null;

        $instrucciones = trim($datos['instrucciones_transferencia'] ?? '');
        if ($total > 0 && $datos['send'] && $instrucciones === '') {
            throw ValidationException::withMessages([
                'instrucciones_transferencia' => 'Ingresá los datos de transferencia antes de enviar el presupuesto.',
            ]);
        }

        $porcentaje = $modalidad === 'sena_saldo' ? (int)$datos['porcentaje_sena'] : 0;
        $sena = intdiv($total, 100) * $porcentaje + (int)round(($total % 100) * $porcentaje / 100);
        if ($porcentaje && $total > 0 && ($sena === 0 || $sena === $total)) {
            throw ValidationException::withMessages(['porcentaje_sena' => 'El total es demasiado bajo para dividirlo. Elegí pago al finalizar.']);
        }

        $cuotas = [];
        if ($sena > 0) {
            $cuotas[] = ['concepto' => 'sena', 'titulo' => 'Seña', 'momento' => 'Antes de iniciar el proyecto', 'importe_centavos' => $sena];
        }
        if ($total > 0) {
            $cuotas[] = ['concepto' => 'saldo', 'titulo' => $sena ? 'Saldo' : 'Pago total', 'momento' => 'Al finalizar el trabajo', 'importe_centavos' => $total - $sena];
        }

        return ['modalidad' => $modalidad, 'porcentaje_sena' => $porcentaje, 'instrucciones' => $instrucciones, 'cuotas' => $cuotas];
    }
}
