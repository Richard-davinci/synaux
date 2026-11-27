<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // La etapa agrupa las tareas del plan de trabajo sin alterar los registros ya existentes.
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $tabla) {
            $tabla->string('etapa', 50)->default('Ejecución')->after('title');
            $tabla->index(['project_id', 'etapa', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $tabla) {
            $tabla->dropIndex(['project_id', 'etapa', 'id']);
            $tabla->dropColumn('etapa');
        });
    }
};
