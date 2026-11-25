<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Organizacion extends Model
{
    protected $table = 'organizaciones';

    // En la primera etapa, el área de Gestión opera en el espacio inicial de synaux.
    public static function principal(): self
    {
        return self::query()->where('slug', 'synaux')->firstOrFail();
    }

    // La cartera relaciona las cuentas atendidas por este espacio, independientemente del equipo.
    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizacion_clientes', 'organizacion_id', 'user_id')
            ->where('users.is_admin', false)
            ->whereNotExists(DB::table('organizacion_miembros')->where('organizacion_id', $this->id)
                ->whereColumn('user_id', 'users.id'));
    }

    // Una cuenta puede pertenecer a varios espacios con un rol distinto en cada uno.
    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizacion_miembros', 'organizacion_id', 'user_id')
            ->withPivot('rol');
    }
}
