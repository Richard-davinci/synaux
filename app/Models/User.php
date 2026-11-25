<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        return \Illuminate\Support\Facades\DB::table('organizacion_miembros')->where('user_id', $this->id)->exists();
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

        return [...$contact, 'address' => $formatted !== '' ? $formatted : ($contact['address'] ?? null)];
    }

    // Los presupuestos de Gestión toman el emisor del espacio de synaux. Cada versión guarda sus propios datos.
    public function issuerDetails(?string $brand = null): array
    {
        $organizacion = $this->is_admin ? Organizacion::principal() : null;

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
