<?php

namespace App\Services\Catalog;

/** Resuelve condiciones sencillas de aparición por IDs de pregunta y opción, sin reglas económicas. */
final class VisibilidadPreguntas
{
    /** Devuelve solo preguntas visibles; una fuente sin respuesta nunca satisface una condición. */
    public function filtrar(array $preguntas, array $respuestas): array
    {
        $porId = collect($preguntas)->keyBy(fn ($pregunta) => (string)($pregunta['id'] ?? $pregunta->id));
        $visibles = [];
        $visiblesPorId = [];
        foreach ($preguntas as $pregunta) {
            $fila = (array)$pregunta;
            // La pregunta técnica del tipo de cliente se mantiene siempre visible aunque haya metadatos inválidos.
            $origenId = ($fila['clave_sistema'] ?? null) === TipoCliente::PREGUNTA
                ? null : ($fila['visibility_question_id'] ?? null);
            if ($origenId !== null) {
                $origen = $porId->get((string)$origenId);
                if (!$origen || !isset($visiblesPorId[(string)$origenId])
                    || !$this->coincide((array)$origen, $fila, $respuestas)) continue;
            }
            $visibles[] = $pregunta;
            $visiblesPorId[(string)$fila['id']] = true;
        }

        return $visibles;
    }

    /** Comprueba operadores compatibles sobre valores de opciones, sin confiar en etiquetas visibles. */
    private function coincide(?array $origen, array $destino, array $respuestas): bool
    {
        if (!$origen || !($origen['active'] ?? false) || !array_key_exists($origen['id'], $respuestas)) return false;
        $valor = $respuestas[$origen['id']];
        if ($valor === null || $valor === '' || $valor === []) return false;
        $seleccionadas = is_array($valor) ? array_map('strval', $valor) : [(string)$valor];
        $esperada = (string)($destino['visibility_option_id'] ?? '');
        $contiene = in_array($esperada, $seleccionadas, true);

        return match ($destino['visibility_operator'] ?? null) {
            'equals' => !is_array($valor) && $contiene,
            'not_equals' => !is_array($valor) && !$contiene,
            'contains' => is_array($valor) && $contiene,
            'not_contains' => is_array($valor) && !$contiene,
            default => false,
        };
    }
}
