<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Una tarifa fuente igual puede volver a publicarse después de que el equipo active una versión manual.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tariff_versions', function (Blueprint $table) {
            $table->dropUnique(['source_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('tariff_versions', function (Blueprint $table) {
            $table->unique('source_fingerprint');
        });
    }
};
