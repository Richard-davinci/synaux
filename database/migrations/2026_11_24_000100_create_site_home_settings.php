<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Una sola fila guarda la selección ordenada. Sin fila, se conserva la portada automática anterior.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_home_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('featured_service_ids');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_home_settings');
    }
};
