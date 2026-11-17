<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Los datos de contacto pertenecen a la cuenta; cada documento conserva su propia instantánea.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 50)->nullable();
            $table->string('company')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_email', 254)->nullable();
            $table->string('issuer_phone', 50)->nullable();
            $table->string('issuer_address', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'phone', 'company', 'address', 'issuer_name', 'issuer_email', 'issuer_phone', 'issuer_address',
        ]));
    }
};
