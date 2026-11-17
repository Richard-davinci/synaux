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

// Importa únicamente la ficha vinculada a Sitio institucional. Las fallas conservan la última versión válida.
Artisan::command('synaux:tarifario', function (\App\Services\SynauxTariffImport $import) {
    try {
        $result = $import->sync();
        $this->info(($result['created'] ? 'Nueva versión importada. ' : 'Sin cambios. ').'Referencia PyME: ARS '.$result['pyme']);
        return 0;
    } catch (\Throwable $exception) {
        report($exception);
        $this->error('Importación cancelada: '.$exception->getMessage());
        return 1;
    }
})->purpose('Actualiza Sitio institucional desde Tarifario.ar');

// Laravel ejecuta esta tarea cuando el planificador está activo en el servidor.
\Illuminate\Support\Facades\Schedule::command('synaux:tarifario')
    ->dailyAt('08:00')->timezone('America/Argentina/Buenos_Aires')->withoutOverlapping(10);