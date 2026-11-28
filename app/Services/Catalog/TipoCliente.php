<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Las claves identifican el segmento en el catálogo y el ID de cada opción conserva sus relaciones de precio.
final class TipoCliente
{
    public const PREGUNTA = 'tipo_cliente';
    public const ETIQUETAS = ['particular' => 'Particular', 'pyme' => 'PyME', 'empresa' => 'Empresa'];
    public const CONCEPTO_HEREDADO = 'segmento-tarifario-2026-11';

    public static function opcionesActivas(): Collection
    {
        return DB::table('estimator_options as o')->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
            ->where('q.clave_sistema', self::PREGUNTA)->where('q.active', 1)->where('o.active', 1)
            ->whereIn('o.clave_segmento', array_keys(self::ETIQUETAS))->orderBy('o.sort_order')
            ->get(['o.id', 'o.question_id', 'o.label', 'o.clave_segmento']);
    }

    // Admite los enlaces anteriores que usaban el nombre original del segmento.
    public static function desdeUrl(mixed $valor): ?string
    {
        $clave = is_string($valor) ? strtolower(trim($valor)) : '';

        return array_key_exists($clave, self::ETIQUETAS) ? $clave : null;
    }

    // Recupera la identidad desde la pareja pregunta y opción de una instantánea, incluso si cambió su etiqueta.
    public static function desdeRespuestas(array $respuestas): ?string
    {
        $pares = [];
        foreach ($respuestas as $respuesta) {
            $valor = $respuesta['value'] ?? null;
            if (!is_int($valor) && !(is_string($valor) && ctype_digit($valor))) continue;
            $pares[$valor] = $respuesta['id'] ?? null;
        }
        if (!$pares) return null;

        return DB::table('estimator_options as o')->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
            ->whereIn('o.id', array_keys($pares))->whereIn('o.clave_segmento', array_keys(self::ETIQUETAS))
            ->orderByDesc('q.clave_sistema')->get(['o.id', 'o.question_id', 'o.clave_segmento'])
            ->first(fn($opcion) => (int)$opcion->question_id === (int)$pares[$opcion->id])?->clave_segmento;
    }

    // Los enlaces seleccionan opciones activas por clave en la pregunta general y en las variantes antiguas del servicio.
    public static function preseleccion(array $preguntas, mixed $cliente): array
    {
        $clave = self::desdeUrl($cliente);
        if ($clave === null) return [];

        $respuestas = [];
        foreach ($preguntas as $pregunta) {
            $opcion = collect($pregunta['options'])->firstWhere('clave_segmento', $clave);
            if ($opcion) $respuestas[$pregunta['id']] = $opcion->id;
        }

        return $respuestas;
    }
}
