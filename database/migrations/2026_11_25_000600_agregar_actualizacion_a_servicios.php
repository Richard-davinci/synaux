<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Los servicios existentes conservan un valor vacío hasta el primer guardado de su ficha.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', fn(Blueprint $tabla) => $tabla->dateTime('updated_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('services', fn(Blueprint $tabla) => $tabla->dropColumn('updated_at'));
    }
};
