<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Conserva tarifas por tipo de cliente sin forzar diferencias lineales entre segmentos.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tariff_segment_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_version_id')->constrained('tariff_versions');
            $table->foreignId('service_id')->constrained('services');
            $table->string('segment', 32);
            $table->decimal('price_min', 15, 2);
            $table->decimal('price_max', 15, 2);
            $table->unique(['tariff_version_id', 'service_id', 'segment'], 'tariff_segment_prices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_segment_prices');
    }
};
