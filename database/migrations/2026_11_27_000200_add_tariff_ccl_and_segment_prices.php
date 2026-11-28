<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Registra la cotización usada por cada versión y permite fijar importes independientes por tipo de cliente.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tariff_versions', function (Blueprint $table) {
            $table->decimal('ccl_rate', 15, 4)->nullable()->after('currency_code');
            $table->timestamp('ccl_rate_at')->nullable()->after('ccl_rate');
        });

        Schema::create('service_segment_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_version_id')->constrained('tariff_versions')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('estimator_option_id')->constrained('estimator_options')->restrictOnDelete();
            $table->decimal('price_min', 15, 2);
            $table->decimal('price_max', 15, 2);
            $table->unique(['tariff_version_id', 'service_id', 'estimator_option_id'], 'service_segment_price_unique');
            $table->index(['service_id', 'tariff_version_id']);
        });

        // Conserva las reglas viejas para consultas históricas, pero deja de usarlas como tarifa de los segmentos.
        $componentIds = DB::table('service_components')->where('slug', 'segmento-tarifario-2026-11')->pluck('id');
        if ($componentIds->isNotEmpty()) {
            DB::table('estimation_rules')->whereIn('service_component_id', $componentIds)->update(['active' => false]);
            DB::table('service_components')->whereIn('id', $componentIds)->update(['active' => false]);
        }

        // Asegura una única selección general para que los precios por variante apliquen a todos los servicios.
        $titles = ['¿Para qué tipo de cliente?', '¿Para qué tipo de cliente es el sitio?', '¿Para qué tipo de cliente es la landing?'];
        $question = DB::table('estimator_questions')->whereNull('service_id')->whereIn('question', $titles)->orderBy('id')->first();
        if (!$question) {
            $question = DB::table('estimator_questions')->whereIn('question', $titles)->orderBy('id')->first();
        }
        $questionValues = ['service_id' => null, 'quantity_component_id' => null,
            'question' => '¿Para qué tipo de cliente?', 'input_type' => 'single_choice',
            'sort_order' => 0, 'active' => true, 'required' => true];
        if ($question) {
            DB::table('estimator_questions')->where('id', $question->id)->update($questionValues);
            $questionId = (int)$question->id;
        } else {
            $questionId = DB::table('estimator_questions')->insertGetId($questionValues);
        }
        foreach (['Particular', 'PyME', 'Empresa'] as $order => $label) {
            $option = DB::table('estimator_options')->where('question_id', $questionId)->where('label', $label)->first();
            if ($option) {
                DB::table('estimator_options')->where('id', $option->id)->update(['sort_order' => $order, 'active' => true]);
            } else {
                DB::table('estimator_options')->insert(['question_id' => $questionId, 'label' => $label, 'sort_order' => $order, 'active' => true]);
            }
        }
        $extraOptionIds = DB::table('estimator_options')->where('question_id', $questionId)
            ->whereNotIn('label', ['Particular', 'PyME', 'Empresa'])->pluck('id');
        if ($extraOptionIds->isNotEmpty()) {
            DB::table('estimation_rules')->whereIn('estimator_option_id', $extraOptionIds)->update(['active' => false]);
            DB::table('estimator_options')->whereIn('id', $extraOptionIds)->update(['active' => false]);
        }
        // Evita que un servicio conserve una copia de la pregunta global y duplique la selección.
        $duplicates = DB::table('estimator_questions')->whereIn('question', $titles)
            ->where('id', '!=', $questionId)->pluck('id');
        if ($duplicates->isNotEmpty()) {
            $optionIds = DB::table('estimator_options')->whereIn('question_id', $duplicates)->pluck('id');
            if ($optionIds->isNotEmpty()) {
                DB::table('estimation_rules')->whereIn('estimator_option_id', $optionIds)->update(['active' => false]);
                DB::table('estimator_options')->whereIn('id', $optionIds)->update(['active' => false]);
            }
            DB::table('estimator_questions')->whereIn('id', $duplicates)->update(['active' => false]);
        }
    }

    public function down(): void
    {
        // La reversión restaura las reglas heredadas para volver al cálculo anterior.
        $componentIds = DB::table('service_components')->where('slug', 'segmento-tarifario-2026-11')->pluck('id');
        if ($componentIds->isNotEmpty()) {
            DB::table('service_components')->whereIn('id', $componentIds)->update(['active' => true]);
            DB::table('estimation_rules')->whereIn('service_component_id', $componentIds)->update(['active' => true]);
        }
        Schema::dropIfExists('service_segment_prices');
        Schema::table('tariff_versions', function (Blueprint $table) {
            $table->dropColumn(['ccl_rate', 'ccl_rate_at']);
        });
    }
};
