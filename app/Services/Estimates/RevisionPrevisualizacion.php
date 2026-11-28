<?php

namespace App\Services\Estimates;

/** Huella económica para detectar cambios de tarifa/CCL; jamás se usa como importe confiable. */
final class RevisionPrevisualizacion
{
    public static function generar(array $principal, array $adicionales = []): string
    {
        $normalizarLineas = static fn(array $items): array => array_map(static fn(array $item) => [
            'component_id' => $item['component_id'] ?? null,
            'quantity' => $item['quantity'] ?? null,
            'min' => $item['min'] ?? null,
            'max' => $item['max'] ?? null,
            'subtotal_min' => $item['subtotal_min'] ?? null,
            'subtotal_max' => $item['subtotal_max'] ?? null,
        ], $items);
        $extras = array_map(static fn(array $extra) => [
            'service_id' => $extra['service_id'] ?? null,
            'quantity' => $extra['quantity'] ?? null,
            'currency' => $extra['pricing_reference']['currency'] ?? $extra['pricing_partial']['currency']
                ?? $extra['pricing_breakdown']['currency'] ?? null,
            'tariff_id' => $extra['pricing_breakdown']['tariff_version_id'] ?? null,
            'ccl_rate' => $extra['pricing_breakdown']['ccl_rate'] ?? null,
            'base_min' => $extra['pricing_breakdown']['base_min'] ?? null,
            'base_max' => $extra['pricing_breakdown']['base_max'] ?? null,
            'items' => $normalizarLineas($extra['pricing_breakdown']['items'] ?? []),
        ], $adicionales);
        $economia = [
            'tariff_id' => $principal['tariff_id'] ?? null,
            'currency' => $principal['currency'] ?? null,
            'ccl_rate' => $principal['ccl_rate'] ?? null,
            'base_min' => $principal['base_min'] ?? null,
            'base_max' => $principal['base_max'] ?? null,
            'min' => $principal['min'] ?? null,
            'max' => $principal['max'] ?? null,
            'items' => $normalizarLineas($principal['items'] ?? []),
            'extras' => $extras,
        ];
        $json = json_encode($economia, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);

        return hash_hmac('sha256', $json, (string)config('app.key'));
    }
}
