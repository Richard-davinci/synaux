<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Los precios conservan sus claves foráneas. Solo se identifican las filas existentes.
        Schema::table('estimator_questions', fn (Blueprint $tabla) => $tabla->string('clave_sistema', 32)->nullable()->unique());
        Schema::table('estimator_options', function (Blueprint $tabla) {
            $tabla->string('clave_segmento', 16)->nullable();
            $tabla->unique(['question_id', 'clave_segmento'], 'estimator_option_segmento_unique');
        });

        // Si ya hay tarifas, la relación identifica la pregunta incluso si le editaron el título antes de migrar.
        $preguntasConPrecio = DB::table('service_segment_prices as p')->join('estimator_options as o', 'o.id', '=', 'p.estimator_option_id')
            ->join('estimator_questions as q', 'q.id', '=', 'o.question_id')->whereNull('q.service_id')
            ->distinct()->pluck('q.id');
        $pregunta = $preguntasConPrecio->count() === 1 ? $preguntasConPrecio->first() : DB::table('estimator_questions')->whereNull('service_id')
            ->where('question', '¿Para qué tipo de cliente?')->orderByDesc('active')->orderBy('id')->value('id');
        if ($pregunta) {
            DB::table('estimator_questions')->where('id', $pregunta)->update(['clave_sistema' => 'tipo_cliente']);
        }

        // También identifica las opciones de configuraciones antiguas vinculadas al concepto tarifario original.
        $heredadas = DB::table('estimator_options as o')->join('estimation_rules as r', 'o.id', '=', 'r.estimator_option_id')
            ->join('service_components as c', 'c.id', '=', 'r.service_component_id')
            ->where('c.slug', 'segmento-tarifario-2026-11')->distinct()->pluck('o.question_id');
        foreach ($heredadas->push($pregunta)->filter()->unique() as $id) {
            foreach (['particular' => 'Particular', 'pyme' => 'PyME', 'empresa' => 'Empresa'] as $clave => $etiqueta) {
                $opciones = DB::table('estimator_options')->where('question_id', $id)->where('label', $etiqueta);
                // Si una etiqueta fue recreada, conserva primero el ID que ya contiene precios.
                $opcion = (clone $opciones)->whereIn('id', DB::table('service_segment_prices')->select('estimator_option_id'))
                    ->orderByDesc('active')->orderBy('id')->value('id') ?? $opciones
                    ->orderByDesc('active')->orderBy('id')->value('id');
                if ($opcion) DB::table('estimator_options')->where('id', $opcion)->update(['clave_segmento' => $clave]);
            }
        }
    }

    public function down(): void
    {
        // Solo retira los metadatos de identificación que agrega esta migración.
        Schema::table('estimator_options', function (Blueprint $tabla) {
            $tabla->dropUnique('estimator_option_segmento_unique');
            $tabla->dropColumn('clave_segmento');
        });
        Schema::table('estimator_questions', function (Blueprint $tabla) {
            $tabla->dropUnique(['clave_sistema']);
            $tabla->dropColumn('clave_sistema');
        });
    }
};
