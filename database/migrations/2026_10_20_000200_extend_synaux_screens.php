<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Añade los campos de presentación y las instantáneas que necesitan las pantallas del prototipo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->text('summary')->nullable();
            $t->json('deliverables')->nullable();
        });
        Schema::table('professional_profiles', function (Blueprint $t) {
            $t->string('location')->nullable();
            $t->json('skills')->nullable();
            $t->boolean('active')->default(true);
        });
        Schema::table('portfolio_projects', function (Blueprint $t) {
            $t->string('category')->nullable();
            $t->text('challenge')->nullable();
            $t->text('result')->nullable();
            $t->string('cover_path')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
        });
        Schema::table('estimates', function (Blueprint $t) {
            $t->uuid('request_key')->unique();
            $t->json('answers_snapshot');
            $t->json('items_snapshot')->nullable();
            $t->char('currency_snapshot', 3)->nullable();
        });
        Schema::table('project_requests', function (Blueprint $t) {
            $t->json('answers_snapshot')->nullable();
            $t->json('skills')->nullable();
            $t->string('category')->nullable();
            $t->string('budget_reference')->nullable();
            $t->string('desired_deadline')->nullable();
            $t->timestamp('reviewed_at')->nullable();
        });
        Schema::table('proposals', function (Blueprint $t) {
            $t->decimal('amount', 15, 2)->nullable();
            $t->char('currency_code', 3)->default('ARS');
        });
        Schema::table('budget_versions', function (Blueprint $t) {
            $t->string('title');
            $t->timestamp('sent_at')->nullable();
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->unsignedTinyInteger('progress')->default(0);
            $t->text('notes')->nullable();
        });
        // La consulta de contacto se conserva realmente; no se simula un envío de correo.
        Schema::create('contact_inquiries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('topic');
            $t->text('message');
            $t->timestamps();
        });
        Schema::create('estimation_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('estimator_option_id')->constrained('estimator_options');
            $t->foreignId('service_component_id')->constrained('service_components');
            $t->decimal('quantity', 12, 3)->default(1);
            $t->boolean('active')->default(true);
            $t->unique(['estimator_option_id', 'service_component_id']);
        });
    }

    public function down(): void
    {
        // La reversión solo se ejecuta si el desarrollador la solicita expresamente.
        Schema::dropIfExists('estimation_rules');
        Schema::dropIfExists('contact_inquiries');
        foreach ([
            'services' => ['summary', 'deliverables'],
            'professional_profiles' => ['location', 'skills', 'active'],
            'portfolio_projects' => ['category', 'challenge', 'result', 'cover_path', 'year'],
            'estimates' => ['request_key', 'answers_snapshot', 'items_snapshot', 'currency_snapshot'],
            'project_requests' => ['answers_snapshot', 'skills', 'category', 'budget_reference', 'desired_deadline', 'reviewed_at'],
            'proposals' => ['amount', 'currency_code'],
            'budget_versions' => ['title', 'sent_at'],
            'projects' => ['progress', 'notes'],
        ] as $table => $columns) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
        }
    }
};
