<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountController extends Controller
{
    // Solo expone los datos de la cuenta autenticada; el rol se muestra pero no se recibe del formulario.
    public function edit(Request $request)
    {
        $user = $request->user();
        $panel = $user->panelPrincipal();
        return Inertia::render('PerfilCuenta', [
            'profile' => [...$user->only('name', 'email', 'phone', 'company', 'address'), 'details' => $user->profile_details,
                'correo_fijo' => $user->perteneceAEquipo(), 'is_admin' => (bool)$user->is_admin, 'created_at' => $user->created_at?->toDateString()],
            'area' => match ($panel) {
                '/mi-perfil' => 'public', '/profesional/proyectos' => 'profesional', default => trim($panel, '/'),
            },
        ]);
    }

    // Cambiar el correo exige la contraseña actual. El contacto se usa solamente en pedidos nuevos.
    public function update(Request $request)
    {
        // Una tarjeta actualiza únicamente sus campos, bajo bloqueo para conservar el resto del perfil.
        $notice = DB::transaction(function () use ($request) {
            $account = $request->user();
            $user = $account->newQuery()->lockForUpdate()->findOrFail($account->id);
            if (is_string($request->input('email'))) {
                $request->merge(['email' => Str::lower(trim($request->input('email')))]);
            }
            $emailChanged = $request->has('email') && $request->input('email') !== $user->email;
            if ($emailChanged && $user->perteneceAEquipo()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'El correo de una cuenta del equipo es fijo.']);
            }
            $data = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => ['sometimes', 'required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
                'phone' => 'sometimes|nullable|string|max:50',
                'company' => 'sometimes|nullable|string|max:255',
                'address' => 'sometimes|nullable|string|max:500',
                'details' => 'sometimes|array:position,website,location,billing_address',
                'details.position' => 'nullable|string|max:120',
                'details.website' => 'nullable|url:http,https|max:255',
                'details.location' => 'sometimes|array:country,region,city',
                'details.location.country' => 'nullable|string|max:80',
                'details.location.region' => 'nullable|string|max:80',
                'details.location.city' => 'nullable|string|max:80',
                'details.billing_address' => 'sometimes|array:street,number,unit,postal_code,country,region,city',
                'details.billing_address.street' => 'nullable|string|max:150',
                'details.billing_address.number' => 'nullable|string|max:20',
                'details.billing_address.unit' => 'nullable|string|max:40',
                'details.billing_address.postal_code' => 'nullable|string|max:16',
                'details.billing_address.country' => 'nullable|string|max:80',
                'details.billing_address.region' => 'nullable|string|max:80',
                'details.billing_address.city' => 'nullable|string|max:80',
                'current_password' => $emailChanged ? ['required', 'current_password:web'] : ['nullable'],
            ]);
            foreach (['name', 'email', 'phone', 'company', 'address'] as $field) {
                if (array_key_exists($field, $data)) $user->$field = $data[$field];
            }
            // Los formularios anteriores pueden seguir actualizando el contacto sin tocar los datos ampliados.
            if (array_key_exists('details', $data)) {
                $user->profile_details = array_replace_recursive($user->profile_details ?? [], $data['details']);
            }
            if ($emailChanged) $user->email_verified_at = null;
            $user->save();

            return match (true) {
                isset($data['details']['billing_address']) => 'Tu domicilio de facturación se actualizó.',
                isset($data['details']['location']) => 'Tu ubicación se actualizó.',
                array_key_exists('company', $data) => 'Tus datos de organización se actualizaron.',
                default => 'Tus datos personales y de contacto se actualizaron.',
            };
        });

        // La cuenta de la sesión conserva la misma versión que se guardó.
        $request->user()->refresh();

        return back()->with('notice', $notice);
    }

    // El cambio de clave requiere la clave actual y confirmación; nunca devuelve contraseñas al navegador.
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);
        $user = $request->user();
        $user->password = $data['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();
        $request->session()->regenerate();

        return back()->with('notice', 'Tu contraseña se actualizó.');
    }

}
