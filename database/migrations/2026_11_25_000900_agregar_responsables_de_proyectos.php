<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// El seguimiento y la ejecución son asignaciones distintas dentro del mismo proyecto.
return new class extends Migration {
    public function up(): void
    {
        // SQLite puede agregar estas referencias sin reconstruir la clave única original.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE projects ADD COLUMN supervisor_id INTEGER REFERENCES users(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE projects ADD COLUMN responsable_id INTEGER REFERENCES users(id) ON DELETE SET NULL');
        } else {
            Schema::table('projects', function (Blueprint $tabla) {
                $tabla->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
                $tabla->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        Schema::table('projects', fn(Blueprint $tabla) => $tabla->index(['responsable_id', 'id']));
        Schema::create('proyecto_asignaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $tabla->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_asignaciones');
        Schema::table('projects', fn(Blueprint $tabla) => $tabla->dropIndex(['responsable_id', 'id']));
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE projects DROP COLUMN supervisor_id');
            DB::statement('ALTER TABLE projects DROP COLUMN responsable_id');
            return;
        }

        Schema::table('projects', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('supervisor_id');
            $tabla->dropConstrainedForeignId('responsable_id');
        });
    }
};
