<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Los datos ampliados del perfil se guardan sin reinterpretar las direcciones anteriores.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('profile_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_details');
        });
    }
};
