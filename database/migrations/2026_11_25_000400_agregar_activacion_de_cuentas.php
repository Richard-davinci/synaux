<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Las cuentas previas siguen activas. Solo las altas del equipo esperan un enlace.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activacion_pendiente')->default(false);
            $table->string('activacion_hash', 64)->nullable()->unique();
            $table->timestamp('activacion_vence')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['activacion_hash']);
            $table->dropColumn(['activacion_pendiente', 'activacion_hash', 'activacion_vence']);
        });
    }
};
