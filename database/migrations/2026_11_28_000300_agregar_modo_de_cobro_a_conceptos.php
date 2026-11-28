<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Las filas anteriores siguen usando la fórmula histórica hasta elegir un modo explícito.
        Schema::table('service_components', fn (Blueprint $tabla) => $tabla->string('modo_cobro', 16)->default('heredado'));
    }

    public function down(): void
    {
        Schema::table('service_components', fn (Blueprint $tabla) => $tabla->dropColumn('modo_cobro'));
    }
};
