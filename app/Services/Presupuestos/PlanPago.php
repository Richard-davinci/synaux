<?php

namespace App\Services\Presupuestos;

use Illuminate\Validation\ValidationException;

class PlanPago
{
    public const MODALIDADES = ['sena_saldo', 'anticipo_dos_hitos', 'al_finalizar'];

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

        $porcentaje = in_array($modalidad, ['sena_saldo', 'anticipo_dos_hitos'], true)
            ? (int)$datos['porcentaje_sena'] : 0;
        $sena = intdiv($total, 100) * $porcentaje + (int)round(($total % 100) * $porcentaje / 100);
        if ($porcentaje && $total > 0 && ($sena === 0 || $sena === $total)) {
            throw ValidationException::withMessages(['porcentaje_sena' => 'El total es demasiado bajo para dividirlo. Elegí pago al finalizar.']);
        }
        if ($modalidad === 'anticipo_dos_hitos' && $total > 0 && $total - $sena < 2) {
            throw ValidationException::withMessages(['porcentaje_sena' => 'El saldo debe alcanzar para registrar los dos pagos por hitos.']);
        }

        $cuotas = [];
        if ($sena > 0) {
            $cuotas[] = ['concepto' => 'sena', 'titulo' => 'Anticipo', 'porcentaje' => $porcentaje,
                'momento' => 'Al aceptar el presupuesto y antes de iniciar el proyecto', 'importe_centavos' => $sena];
        }
        if ($modalidad === 'anticipo_dos_hitos' && $total > 0) {
            // El saldo se reparte en dos pagos iguales y el último absorbe el centavo impar.
            $restante = $total - $sena;
            $intermedio = intdiv($restante, 2);
            $cuotas[] = [
                'concepto' => 'hito_intermedio', 'titulo' => 'Segundo pago',
                'porcentaje' => intdiv(100 - $porcentaje, 2),
                'momento' => trim($datos['momento_hito_intermedio']), 'importe_centavos' => $intermedio,
            ];
            $cuotas[] = [
                'concepto' => 'saldo_final', 'titulo' => 'Pago final',
                'porcentaje' => 100 - $porcentaje - intdiv(100 - $porcentaje, 2),
                'momento' => trim($datos['momento_pago_final']), 'importe_centavos' => $restante - $intermedio,
            ];
        } elseif ($total > 0) {
            $cuotas[] = [
                'concepto' => $sena ? 'saldo' : 'pago_total',
                'titulo' => $sena ? 'Saldo' : 'Pago total', 'porcentaje' => 100 - $porcentaje,
                'momento' => 'Al finalizar el trabajo', 'importe_centavos' => $total - $sena,
            ];
        }

        return ['modalidad' => $modalidad, 'porcentaje_sena' => $porcentaje, 'instrucciones' => $instrucciones, 'cuotas' => $cuotas];
    }
}
