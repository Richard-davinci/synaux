<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\TipoCliente;

abstract class TestCase extends BaseTestCase
{
    // Construye una respuesta válida con la configuración actual para aislar los recorridos que prueba cada caso.
    protected function respuestasValidas(string $slug, string $segmento = 'particular'): array
    {
        $service = app(ServiceCatalog::class)->service($slug);
        $answers = [];
        foreach (app(ServiceCatalog::class)->questions($service['database_id']) as $question) {
            if (!$question['required']) continue;
            if (in_array($question['input_type'], ['single_choice', 'multiple_choice'], true)) {
                $option = collect($question['options'])->first(fn($candidate) => ($question['clave_sistema'] ?? null) === TipoCliente::PREGUNTA
                    ? $candidate->clave_segmento === $segmento
                    : ($candidate->clave_segmento ?? null) === $segmento)
                    ?? ($question['options'][0] ?? null);
                if ($option) $answers[$question['id']] = $question['input_type'] === 'multiple_choice' ? [$option->id] : $option->id;
            } elseif ($question['input_type'] === 'number') {
                $answers[$question['id']] = 1;
            } else {
                $answers[$question['id']] = 'Respuesta de prueba suficiente.';
            }
        }

        return $answers;
    }
}
