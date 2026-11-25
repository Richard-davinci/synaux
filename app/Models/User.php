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
    ];

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

    // La identidad de emisión administrativa se separa del correo usado para ingresar.
    public function issuerDetails(?string $brand = null): array
    {
        return [
            'name' => ($this->is_admin ? $this->issuer_name : null) ?: ($brand ?: 'synaux'),
            'contact' => $this->name,
            'email' => ($this->is_admin ? $this->issuer_email : null) ?: $this->email,
            'phone' => $this->is_admin ? $this->issuer_phone : null,
            'address' => $this->is_admin ? $this->issuer_address : null,
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
            'profile_details' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
