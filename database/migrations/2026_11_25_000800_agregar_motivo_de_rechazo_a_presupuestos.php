<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Los rechazos anteriores conservan su estado y pueden quedar sin motivo registrado.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('budget_versions', fn(Blueprint $tabla) => $tabla->text('motivo_rechazo')->nullable());
    }

    public function down(): void
    {
        Schema::table('budget_versions', fn(Blueprint $tabla) => $tabla->dropColumn('motivo_rechazo'));
    }
};
