<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Conserva el pedido y los datos de las partes tal como se enviaron en cada etapa.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            $table->json('contact_snapshot')->nullable();
            $table->json('requested_services')->nullable();
        });
        Schema::table('budget_versions', function (Blueprint $table) {
            $table->json('client_snapshot')->nullable();
            $table->json('issuer_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('budget_versions', fn(Blueprint $table) => $table->dropColumn(['client_snapshot', 'issuer_snapshot']));
        Schema::table('project_requests', fn(Blueprint $table) => $table->dropColumn(['contact_snapshot', 'requested_services']));
    }
};
