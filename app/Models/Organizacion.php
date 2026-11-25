<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organizacion extends Model
{
    protected $table = 'organizaciones';

    // En la primera etapa, el área de Gestión opera en el espacio inicial de synaux.
    public static function principal(): self
    {
        return self::query()->where('slug', 'synaux')->firstOrFail();
    }

    // Una cuenta puede pertenecer a varios espacios con un rol distinto en cada uno.
    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizacion_miembros', 'organizacion_id', 'user_id')
            ->withPivot('rol');
    }
}
