<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Permite quitar estimaciones de la cuenta sin borrar los antecedentes de solicitudes y presupuestos.
    public function up(): void
    {
        Schema::table('estimates', fn(Blueprint $table) => $table->timestamp('deleted_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('estimates', fn(Blueprint $table) => $table->dropColumn('deleted_at'));
    }
};
