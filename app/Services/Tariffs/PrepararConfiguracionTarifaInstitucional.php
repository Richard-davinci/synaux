<?php

namespace App\Services\Tariffs;

use Illuminate\Support\Facades\DB;

// Asegura la configuración mínima que necesita el importador institucional, sin publicar precios.
class PrepararConfiguracionTarifaInstitucional
{
    private const SERVICIO = 'sitio-institucional';
    private const COMPONENTE = 'segmento-tarifario-2026-11';
    private const PREGUNTA = '¿Para qué tipo de cliente?';
    private const PREGUNTA_ANTERIOR = '¿Para qué tipo de cliente es el sitio?';

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

            // Reutiliza la pregunta general o convierte la versión anterior del servicio sin perder sus respuestas.
            $question = DB::table('estimator_questions')->whereNull('service_id')
                ->where('question', self::PREGUNTA)->lockForUpdate()->first();
            $legacyQuestion = DB::table('estimator_questions')->where('service_id', $serviceId)
                ->whereIn('question', [self::PREGUNTA, self::PREGUNTA_ANTERIOR])->lockForUpdate()->first();
            if (!$question && $legacyQuestion) {
                DB::table('estimator_questions')->where('id', $legacyQuestion->id)->update([
                    'service_id' => null, 'question' => self::PREGUNTA,
                ]);
                $question = DB::table('estimator_questions')->where('id', $legacyQuestion->id)->first();
            }

            // Si ya había una pregunta general, apaga duplicados específicos sin borrar referencias históricas.
            if ($question) {
                $duplicadas = DB::table('estimator_questions')->where('service_id', $serviceId)
                    ->whereIn('question', [self::PREGUNTA, self::PREGUNTA_ANTERIOR])
                    ->where('id', '!=', $question->id)->pluck('id')->all();
                foreach ($duplicadas as $duplicateId) {
                    $optionIds = DB::table('estimator_options')->where('question_id', $duplicateId)->pluck('id')->all();
                    if ($optionIds) {
                        DB::table('estimation_rules')->whereIn('estimator_option_id', $optionIds)->update(['active' => false]);
                        DB::table('estimator_options')->whereIn('id', $optionIds)->update(['active' => false]);
                    }
                    DB::table('estimator_questions')->where('id', $duplicateId)->update(['active' => false]);
                }
            }

            $questionValues = [
                'quantity_component_id' => null, 'input_type' => 'single_choice',
                'sort_order' => $question->sort_order ?? ((int)(DB::table('estimator_questions')->whereNull('service_id')->max('sort_order') ?? -1) + 1),
                'active' => true, 'required' => true, 'question' => self::PREGUNTA, 'service_id' => null,
            ];
            if ($question) {
                DB::table('estimator_questions')->where('id', $question->id)->update($questionValues);
                $questionId = (int)$question->id;
            } else {
                $questionId = DB::table('estimator_questions')->insertGetId($questionValues);
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
