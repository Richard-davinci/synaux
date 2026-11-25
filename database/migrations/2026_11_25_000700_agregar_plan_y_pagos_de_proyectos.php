<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La versión conserva lo acordado y el proyecto registra cada cobro por separado.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('budget_versions', fn(Blueprint $tabla) => $tabla->json('plan_pago')->nullable());
        Schema::create('proyecto_pagos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $tabla->string('concepto', 16);
            $tabla->unsignedBigInteger('importe_centavos');
            $tabla->string('estado', 20)->default('pendiente');
            $tabla->unsignedInteger('intento')->default(0);
            $tabla->string('comprobante')->nullable();
            $tabla->text('observacion')->nullable();
            $tabla->timestamp('presentado_at')->nullable();
            $tabla->timestamp('revisado_at')->nullable();
            $tabla->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->unique(['project_id', 'concepto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_pagos');
        Schema::table('budget_versions', fn(Blueprint $tabla) => $tabla->dropColumn('plan_pago'));
    }
};
