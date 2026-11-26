<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cada tarea conserva su alta y su finalización dentro del historial del proyecto.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $tabla->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $tabla->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $tabla->string('title', 180);
            $tabla->boolean('completed')->default(false);
            $tabla->timestamp('completed_at')->nullable();
            $tabla->timestamps();
            $tabla->index(['project_id', 'completed', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
