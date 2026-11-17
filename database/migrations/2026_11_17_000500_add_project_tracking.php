<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Los avances guardan instantáneas para que una actualización no borre el seguimiento anterior.
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('next_steps')->nullable();
        });
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30);
            $table->unsignedTinyInteger('progress');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('next_steps')->nullable();
            $table->timestamp('created_at');
            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('next_steps'));
    }
};
