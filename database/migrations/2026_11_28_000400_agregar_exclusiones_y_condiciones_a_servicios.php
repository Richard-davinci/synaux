<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Las inclusiones ya viven en deliverables; estos campos nuevos guardan solo exclusiones y condiciones.
        Schema::table('services', function (Blueprint $tabla) {
            $tabla->json('excluded_items')->nullable();
            $tabla->text('conditions')->nullable();
        });
    }

    public function down(): void
    {
        // Se pueden retirar únicamente los campos incrementales de alcance agregados por esta migración.
        Schema::table('services', function (Blueprint $tabla) {
            $tabla->dropColumn(['excluded_items', 'conditions']);
        });
    }
};
