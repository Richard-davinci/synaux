<?php

use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// La administración se otorga deliberadamente a una cuenta existente desde la consola local.
Artisan::command('synaux:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (!$user) {
        $this->error('La cuenta no existe. Registrala primero desde la aplicación.');

        return 1;
    }
    // La habilitación global también incorpora al usuario como administrador en el equipo de synaux.
    DB::transaction(function () use ($user) {
        $user->is_admin = true;
        $user->save();
        Organizacion::principal()->miembros()->syncWithoutDetaching([$user->id => ['rol' => 'administrador']]);
    });
    $this->info('La cuenta ahora puede acceder a Gestión y Administración.');

    return 0;
})->purpose('Habilita la gestión de Synaux para una cuenta registrada');
