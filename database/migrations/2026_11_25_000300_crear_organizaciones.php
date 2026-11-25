<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // La identidad fiscal pertenece al espacio de trabajo. El rol se asigna a cada cuenta dentro de ese espacio.
        Schema::create('organizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('tipo', ['empresa', 'independiente']);
            $table->string('nombre');
            $table->string('razon_social')->nullable();
            $table->string('cuit', 13)->nullable();
            $table->string('correo', 254)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('domicilio', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('organizacion_miembros', function (Blueprint $table) {
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('rol', ['administrador', 'supervisor', 'profesional']);
            $table->primary(['organizacion_id', 'user_id']);
        });

        // La instalación existente representa a synaux. Se copia la ficha configurada más reciente con CUIT si hay varias.
        $administradores = DB::table('users')->where('is_admin', true);
        $emisor = (clone $administradores)->whereNotNull('issuer_name')
            ->orderByRaw('CASE WHEN issuer_cuit IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('updated_at')->orderBy('id')->first();
        $id = DB::table('organizaciones')->insertGetId([
            'slug' => 'synaux', 'tipo' => 'empresa', 'nombre' => 'synaux',
            'razon_social' => $emisor?->issuer_name, 'cuit' => $emisor?->issuer_cuit,
            'correo' => $emisor?->issuer_email, 'telefono' => $emisor?->issuer_phone,
            'domicilio' => $emisor?->issuer_address, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $administradores->select('id')->chunkById(500, function ($usuarios) use ($id) {
            DB::table('organizacion_miembros')->insert($usuarios->map(fn ($usuario) => [
                'organizacion_id' => $id, 'user_id' => $usuario->id, 'rol' => 'administrador',
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizacion_miembros');
        Schema::dropIfExists('organizaciones');
    }
};
