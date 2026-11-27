<?php

namespace App\Services\Tariffs;

use Illuminate\Support\Facades\DB;

// Asegura la configuración mínima que necesita el importador institucional, sin publicar precios.
class PrepararConfiguracionTarifaInstitucional
{
    private const SERVICIO = 'sitio-institucional';
    private const COMPONENTE = 'segmento-tarifario-2026-11';
    private const PREGUNTA = '¿Para qué tipo de cliente es el sitio?';

    // Crea o repara los segmentos al guardar el servicio; repetirlo no genera duplicados.
    public function preparar(int $serviceId): bool
    {
        return DB::transaction(function () use ($serviceId) {
            $service = DB::table('services')->where('id', $serviceId)->lockForUpdate()->first();
            if (!$service || $service->slug !== self::SERVICIO) {
                return false;
            }

            // El componente conserva su identificador si ya existe y queda listo para recibir precios importados.
            $component = DB::table('service_components')->where('service_id', $serviceId)
                ->where('slug', self::COMPONENTE)->lockForUpdate()->first();
            $componentValues = [
                'name' => 'Adecuación por tipo de cliente',
                'description' => 'Diferencia entre los segmentos publicados por Tarifario.ar.',
                'unit_code' => 'segmento', 'active' => true, 'is_default' => false, 'default_quantity' => 1,
            ];
            if ($component) {
                DB::table('service_components')->where('id', $component->id)->update($componentValues);
                $componentId = (int)$component->id;
            } else {
                $componentId = DB::table('service_components')->insertGetId([
                    ...$componentValues, 'service_id' => $serviceId, 'slug' => self::COMPONENTE,
                ]);
            }

            // La pregunta específica permanece activa aun si la ficha comercial se guarda como borrador.
            $question = DB::table('estimator_questions')->where('service_id', $serviceId)
                ->where('question', self::PREGUNTA)->lockForUpdate()->first();
            $questionValues = [
                'quantity_component_id' => null, 'input_type' => 'single_choice',
                'sort_order' => $question->sort_order ?? ((int)DB::table('estimator_questions')->where('service_id', $serviceId)->max('sort_order') + 1),
                'active' => true, 'required' => true,
            ];
            if ($question) {
                DB::table('estimator_questions')->where('id', $question->id)->update($questionValues);
                $questionId = (int)$question->id;
            } else {
                $questionId = DB::table('estimator_questions')->insertGetId([
                    ...$questionValues, 'service_id' => $serviceId, 'question' => self::PREGUNTA,
                ]);
            }

            // Las tres opciones traducen los precios de la fuente a reglas de cantidad del componente.
            $opciones = ['Particular' => 0, 'PyME' => 1, 'Empresa' => 2];
            foreach ($opciones as $label => $quantity) {
                $option = DB::table('estimator_options')->where('question_id', $questionId)->where('label', $label)->first();
                if ($option) {
                    DB::table('estimator_options')->where('id', $option->id)->update(['sort_order' => $quantity, 'active' => true]);
                    $optionId = (int)$option->id;
                } else {
                    $optionId = DB::table('estimator_options')->insertGetId([
                        'question_id' => $questionId, 'label' => $label, 'sort_order' => $quantity, 'active' => true,
                    ]);
                }

                $rule = DB::table('estimation_rules')->where('estimator_option_id', $optionId)
                    ->where('service_component_id', $componentId)->first();
                $ruleValues = ['quantity' => $quantity, 'active' => true];
                if ($rule) {
                    DB::table('estimation_rules')->where('id', $rule->id)->update($ruleValues);
                } else {
                    DB::table('estimation_rules')->insert([
                        ...$ruleValues, 'estimator_option_id' => $optionId, 'service_component_id' => $componentId,
                    ]);
                }
            }

            // Las opciones anteriores quedan guardadas por si hay respuestas históricas, pero no se ofrecen al cliente.
            DB::table('estimator_options')->where('question_id', $questionId)
                ->whereNotIn('label', array_keys($opciones))->update(['active' => false]);

            return true;
        });
    }
}
