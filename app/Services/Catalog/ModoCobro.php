<?php

namespace App\Services\Catalog;

// Resuelve cuántas unidades se cobran desde las respuestas y la cantidad incluida por defecto.
enum ModoCobro: string
{
    case Heredado = 'heredado';
    case Unico = 'unico';
    case Acumulativo = 'acumulativo';
    case Cantidad = 'cantidad';

    public function unidades(float $porDefecto, array $cantidades, float $porReglas): float
    {
        return match ($this) {
            self::Heredado => ($cantidades ? end($cantidades) : $porDefecto) + $porReglas,
            self::Unico => $porDefecto > 0 || $cantidades || $porReglas > 0 ? 1 : 0,
            self::Acumulativo => ($cantidades ? array_sum($cantidades) : $porDefecto) + $porReglas,
            self::Cantidad => $cantidades ? array_sum($cantidades) : ($porReglas > 0 ? $porReglas : $porDefecto),
        };
    }
}
