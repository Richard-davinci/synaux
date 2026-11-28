<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Agrega metadatos opcionales sin tocar identificadores, reglas ni respuestas guardadas.
        Schema::table('estimator_questions', function (Blueprint $tabla) {
            $tabla->boolean('is_yes_no')->default(false);
            $tabla->unsignedBigInteger('visibility_question_id')->nullable();
            $tabla->string('visibility_operator', 24)->nullable();
            $tabla->unsignedBigInteger('visibility_option_id')->nullable();
            $tabla->index('visibility_question_id', 'estimator_questions_visibility_source_idx');
        });
    }

    public function down(): void
    {
        // El rollback retira solo los metadatos introducidos por esta etapa.
        Schema::table('estimator_questions', function (Blueprint $tabla) {
            $tabla->dropIndex('estimator_questions_visibility_source_idx');
            $tabla->dropColumn(['is_yes_no', 'visibility_question_id', 'visibility_operator', 'visibility_option_id']);
        });
    }
};
