<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guarda procedencia y conversión para poder explicar el importe de cada versión importada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_versions', function (Blueprint $table) {
            $table->string('source_url')->nullable();
            $table->decimal('source_usd', 15, 2)->nullable();
            $table->decimal('source_rate', 15, 4)->nullable();
            $table->timestamp('source_checked_at')->nullable();
            $table->string('source_fingerprint', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('tariff_versions', function (Blueprint $table) {
            $table->dropUnique(['source_fingerprint']);
            $table->dropColumn(['source_url', 'source_usd', 'source_rate', 'source_checked_at', 'source_fingerprint']);
        });
    }
};
