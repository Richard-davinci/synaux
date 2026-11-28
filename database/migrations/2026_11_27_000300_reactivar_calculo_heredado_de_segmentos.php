<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Mantiene el cálculo de las tarifas antiguas hasta que una nueva versión incluya precios directos por variante.
return new class extends Migration {
    public function up(): void
    {
        $componentIds = DB::table('service_components')->where('slug', 'segmento-tarifario-2026-11')->pluck('id');
        if ($componentIds->isEmpty()) return;

        $optionIds = DB::table('estimator_options as o')->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
            ->whereNull('q.service_id')->where('q.question', '¿Para qué tipo de cliente?')
            ->where('q.active', 1)->where('o.active', 1)->pluck('o.id');
        DB::table('service_components')->whereIn('id', $componentIds)->update(['active' => true]);
        DB::table('estimation_rules')->whereIn('service_component_id', $componentIds)
            ->whereIn('estimator_option_id', $optionIds)->update(['active' => true]);
    }

    public function down(): void
    {
        // Al revertir, conserva los datos y vuelve a ocultar la regla heredada.
        $componentIds = DB::table('service_components')->where('slug', 'segmento-tarifario-2026-11')->pluck('id');
        if ($componentIds->isEmpty()) return;
        DB::table('estimation_rules')->whereIn('service_component_id', $componentIds)->update(['active' => false]);
        DB::table('service_components')->whereIn('id', $componentIds)->update(['active' => false]);
    }
};
