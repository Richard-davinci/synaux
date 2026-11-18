<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Las preguntas existentes siguen siendo obligatorias; las nuevas pueden ser opcionales.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('estimator_questions', fn (Blueprint $table) => $table->boolean('required')->default(true));
    }
    public function down(): void
    {
        Schema::table('estimator_questions', fn (Blueprint $table) => $table->dropColumn('required'));
    }
};
