<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

// La administración se otorga deliberadamente a una cuenta existente desde la consola local.
Artisan::command('synaux:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('La cuenta no existe. Registrala primero desde la aplicación.');

        return 1;
    }
    $user->is_admin = true;
    $user->save();
    $this->info('La cuenta ahora puede acceder a Gestión y Administración.');

    return 0;
})->purpose('Habilita la gestión de Synaux para una cuenta registrada');
