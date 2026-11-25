<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ActivacionCuentaController extends Controller
{
    // La página valida el enlace sin consumirlo, incluso si una app de mensajería carga su vista previa.
    public function edit(Request $request, string $token)
    {
        return Inertia::render('ActivarCuenta', [
            'token' => $token,
            'vigente' => $this->cuentaPorToken($token)->exists(),
        ])->toResponse($request)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    // El cambio y el consumo ocurren en una sola actualización para impedir usos simultáneos.
    public function update(Request $request, string $token)
    {
        $cuenta = $this->cuentaPorToken($token);
        if (!$cuenta->exists()) {
            return redirect()->route('cuenta.activar', $token);
        }

        $data = $request->validate([
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(8), function ($attribute, $value, $fail) {
                if (strlen($value) > 72) $fail('La contraseña supera el largo permitido. Elegí una más corta.');
            }],
        ]);

        $actualizadas = $this->cuentaPorToken($token)->update([
            'password' => Hash::make($data['password']),
            'activacion_pendiente' => false,
            'activacion_hash' => null,
            'activacion_vence' => null,
            'remember_token' => null,
        ]);

        if (!$actualizadas) {
            return redirect()->route('cuenta.activar', $token);
        }

        return redirect('/ingresar')->with('notice', 'Activaste tu cuenta. Ya podés ingresar con tu nueva contraseña.');
    }

    // Solo se guarda un hash del enlace. El código original se entrega al administrador que lo comparte.
    private function cuentaPorToken(string $token): Builder
    {
        return User::query()->where('activacion_hash', hash('sha256', $token))
            ->where('activacion_pendiente', true)->where('activacion_vence', '>', now());
    }
}
