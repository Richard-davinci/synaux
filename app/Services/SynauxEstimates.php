<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Guarda el resultado de sesión con una clave única para que los reintentos recuperen la misma estimación.
class SynauxEstimates
{
    public function save(int $userId, array $pending): int
    {
        // insertOrIgnore deja que el índice único de request_key resuelva dos inserciones simultáneas.
        DB::table('estimates')->insertOrIgnore([
            'user_id' => $userId,
            'service_id' => $pending['service']['database_id'],
            'tariff_version_id' => $pending['tariff_id'],
            'request_key' => $pending['key'],
            'summary' => $pending['service']['name'],
            'status' => $pending['mode'] === 'automatic' ? 'calculated' : 'manual',
            'base_min_snapshot' => $pending['base_min'],
            'base_max_snapshot' => $pending['base_max'],
            'price_min' => $pending['min'],
            'price_max' => $pending['max'],
            'currency_snapshot' => $pending['currency'],
            'answers_snapshot' => json_encode($pending['answers']),
            'items_snapshot' => json_encode($pending['items']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // La clave nunca permite recuperar un registro perteneciente a otra cuenta.
        $estimate = DB::table('estimates')->where('request_key', $pending['key'])->firstOrFail();
        abort_unless($estimate->user_id === $userId, 403);

        // Guardar explícitamente el mismo cálculo recupera su registro inmutable si se había quitado de la lista.
        if ($estimate->deleted_at) DB::table('estimates')->where('id', $estimate->id)->update(['deleted_at' => null, 'updated_at' => now()]);

        return $estimate->id;
    }
}
