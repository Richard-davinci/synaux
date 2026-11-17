<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Cada destinatario conserva su lectura; la clave del evento evita avisos duplicados por reintentos.
    public function up(): void
    {
        Schema::create('synaux_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 120);
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('title');
            $table->string('message');
            $table->string('href');
            $table->boolean('requires_admin')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'read_at', 'id']);
            $table->index(['user_id', 'entity_type', 'entity_id']);
        });
    }

    // La reversión elimina únicamente el historial de avisos de este módulo.
    public function down(): void
    {
        Schema::dropIfExists('synaux_notifications');
    }
};
