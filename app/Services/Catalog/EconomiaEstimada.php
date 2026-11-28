<?php

namespace App\Services\Catalog;

// Suma importes ya determinados. Una línea sin precio queda fuera del subtotal y un cero confirmado sí cuenta.
final class EconomiaEstimada
{
    public static function subtotal(mixed $baseMin, mixed $baseMax, array $items): array
    {
        $lineas = $baseMin === null ? [] : [[$baseMin, $baseMax]];
        foreach ($items as $item) {
            if (isset($item['subtotal_min'], $item['subtotal_max'])) {
                $lineas[] = [$item['subtotal_min'], $item['subtotal_max']];
            }
        }
        if (!$lineas) return ['subtotal_min' => null, 'subtotal_max' => null];

        $totalMin = $totalMax = 0;
        foreach ($lineas as [$min, $max]) {
            $totalMin += (int)round($min * 100);
            $totalMax += (int)round($max * 100);
        }

        return ['subtotal_min' => $totalMin / 100, 'subtotal_max' => $totalMax / 100];
    }

    // Un resultado guardado conserva el subtotal del cálculo o de las líneas existentes en su instantánea.
    public static function referenciaParcial(mixed $baseMin, mixed $baseMax, array $items, ?string $moneda): ?array
    {
        $subtotal = self::subtotal($baseMin, $baseMax, $items);
        if ($subtotal['subtotal_min'] === null || $moneda === null) return null;

        return ['min' => $subtotal['subtotal_min'], 'max' => $subtotal['subtotal_max'], 'currency' => $moneda, 'partial' => true];
    }
}
