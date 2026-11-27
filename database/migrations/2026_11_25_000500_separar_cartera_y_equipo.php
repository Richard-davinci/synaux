<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // La cartera se vincula a la organización, de manera independiente a los miembros del equipo.
        Schema::create('organizacion_clientes', function (Blueprint $table) {
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['organizacion_id', 'user_id']);
        });
        Schema::table('organizaciones', function (Blueprint $table) {
            $table->string('dominio_correo_equipo', 253)->nullable();
        });

        // Se incorpora el trabajo histórico de la única organización instalada, excluyendo borradores.
        $id = DB::table('organizaciones')->where('slug', 'synaux')->value('id');
        $solicitudes = DB::table('project_requests')->where('status', '<>', 'draft')
            ->selectRaw('? as organizacion_id, user_id', [$id]);
        $presupuestos = DB::table('budgets')->whereNotNull('client_user_id')
            ->selectRaw('? as organizacion_id, client_user_id as user_id', [$id]);
        $cartera = DB::query()->fromSub($solicitudes->union($presupuestos), 'cartera')
            ->whereExists(DB::table('users')->whereColumn('users.id', 'cartera.user_id')->where('is_admin', false))
            ->whereNotExists(DB::table('organizacion_miembros')->where('organizacion_id', $id)
                ->whereColumn('organizacion_miembros.user_id', 'cartera.user_id'));
        DB::table('organizacion_clientes')->insertUsing(['organizacion_id', 'user_id'], $cartera);
    }

    public function down(): void
    {
        Schema::table('organizaciones', fn(Blueprint $table) => $table->dropColumn('dominio_correo_equipo'));
        Schema::dropIfExists('organizacion_clientes');
    }
};
