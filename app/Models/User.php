<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_details',
        'issuer_cuit',
        'activacion_hash',
    ];

    // Cualquier membresía de equipo mantiene fijo el correo de esa cuenta.
    public function perteneceAEquipo(): bool
    {
        return DB::table('organizacion_miembros')->where('user_id', $this->id)->exists();
    }

    // Solo la membresía del espacio inicial concede acceso a sus datos compartidos.
    public function rolEnSynaux(): ?string
    {
        return DB::table('organizacion_miembros as miembro')
            ->join('organizaciones as organizacion', 'organizacion.id', '=', 'miembro.organizacion_id')
            ->where('miembro.user_id', $this->id)->where('organizacion.slug', 'synaux')->value('miembro.rol');
    }

    public function puedeAdministrarSynaux(): bool
    {
        return $this->is_admin || $this->rolEnSynaux() === 'administrador';
    }

    public function puedeGestionarSynaux(): bool
    {
        return $this->is_admin || in_array($this->rolEnSynaux(), ['administrador', 'supervisor'], true);
    }

    // El panel de asignaciones requiere una cuenta activada del equipo de synaux.
    public function esProfesionalInterno(): bool
    {
        return !$this->activacion_pendiente && $this->rolEnSynaux() === 'profesional';
    }

    // La cuenta interna usa su área habilitada y queda fuera del recorrido de cliente.
    public function esCuentaInterna(): bool
    {
        return $this->is_admin || $this->perteneceAEquipo();
    }

    public function panelPrincipal(): string
    {
        if ($this->puedeGestionarSynaux()) return '/gestion';
        if (!$this->esCuentaInterna()) return '/cliente';

        return $this->esProfesionalInterno() ? '/profesional/proyectos' : '/mi-perfil';
    }

    // El contacto propio se reutiliza al iniciar un pedido, sin modificar pedidos ya guardados.
    public function contactDetails(): array
    {
        $contact = $this->only('name', 'email', 'phone', 'company', 'address');
        $address = $this->profile_details['billing_address'] ?? [];
        $join = fn(array $parts, string $separator) => implode($separator, array_filter($parts, fn($part) => filled($part)));
        $formatted = $join([
            $join([$address['street'] ?? null, $address['number'] ?? null], ' '),
            $address['unit'] ?? null,
            $join([$address['postal_code'] ?? null, $address['city'] ?? null], ' '),
            $address['region'] ?? null,
            $address['country'] ?? null,
        ], ', ');

        return [...$contact, 'address' => $formatted !== '' ? $formatted : ($contact['address'] ?? null),
            'cuit' => $this->profile_details['cuit'] ?? null];
    }

    // Los presupuestos de Gestión toman el emisor del espacio de synaux. Cada versión guarda sus propios datos.
    public function issuerDetails(?string $brand = null): array
    {
        $organizacion = $this->puedeGestionarSynaux() ? Organizacion::principal() : null;

        return [
            'name' => $organizacion?->razon_social ?: ($brand ?: 'synaux'),
            'contact' => $this->name,
            'email' => $organizacion?->correo ?: $this->email,
            'phone' => $organizacion?->telefono,
            'address' => $organizacion?->domicilio,
            'cuit' => $organizacion?->cuit,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // El rol administrativo se interpreta como booleano y no forma parte de fillable.
            'is_admin' => 'boolean',
            'activacion_pendiente' => 'boolean',
            'profile_details' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
