<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Permite quitar servicios del catálogo sin perder proyectos ni solicitudes históricas.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->integer('service_id')->nullable()->change();
        });

        Schema::table('project_requests', function (Blueprint $table) {
            $table->string('service_name_snapshot')->nullable();
            $table->string('service_slug_snapshot')->nullable();
            $table->json('estimate_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['service_name_snapshot', 'service_slug_snapshot', 'estimate_snapshot'],
            fn (string $column) => Schema::hasColumn('project_requests', $column),
        ));
        if ($columns) {
            Schema::table('project_requests', fn (Blueprint $table) => $table->dropColumn($columns));
        }

        // Los proyectos sin servicio no se pueden volver a asociar automáticamente.
        // En ese caso, la reversión de nulabilidad se deja a una restauración de datos.
    }
};
