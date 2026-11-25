<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // El CUIT del emisor es opcional para las cuentas existentes y se guarda con guiones.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('issuer_cuit', 13)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('issuer_cuit');
        });
    }
};
