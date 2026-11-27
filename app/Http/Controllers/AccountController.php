<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'foto_perfil' => $user->foto_perfil ? '/mi-perfil/foto?v=' . $user->updated_at?->format('Uu') : null,
                'correo_fijo' => $user->perteneceAEquipo(), 'is_admin' => (bool)$user->is_admin, 'created_at' => $user->created_at?->toDateString()],
            'area' => match ($panel) {
                '/mi-perfil' => 'public',
                '/profesional/proyectos' => 'profesional',
                default => trim($panel, '/'),
            },
        ]);
    }

    // Cambiar el correo exige la contraseña actual. El contacto se usa solamente en pedidos nuevos.
    public function update(Request $request)
    {
        $fotoAnterior = null;
        // Una tarjeta actualiza únicamente sus campos, bajo bloqueo para conservar el resto del perfil.
        $notice = DB::transaction(function () use ($request, &$fotoAnterior) {
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
                'foto_perfil_archivo' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'quitar_foto_perfil' => 'sometimes|boolean',
                'details' => 'sometimes|array:position,website,cuit,location,billing_address',
                'details.position' => 'nullable|string|max:120',
                'details.website' => 'nullable|url:http,https|max:255',
                'details.cuit' => ['nullable', 'string', 'regex:/^(?:[0-9]{11}|[0-9]{2}-[0-9]{8}-[0-9])$/'],
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
            ], [
                'details.cuit.regex' => 'Ingresá 11 dígitos con el formato XX-XXXXXXXX-X.',
                'foto_perfil_archivo.image' => 'El archivo debe ser una imagen válida.',
                'foto_perfil_archivo.mimes' => 'La foto debe estar en formato JPG, PNG o WebP.',
                'foto_perfil_archivo.max' => 'La foto no puede superar los 2 MB.',
            ]);
            foreach (['name', 'email', 'phone', 'company', 'address'] as $field) {
                if (array_key_exists($field, $data)) $user->$field = $data[$field];
            }
            // Los formularios anteriores pueden seguir actualizando el contacto sin tocar los datos ampliados.
            if (array_key_exists('details', $data)) {
                $user->profile_details = array_replace_recursive($user->profile_details ?? [], $data['details']);
            }
            if ($request->hasFile('foto_perfil_archivo') || $request->boolean('quitar_foto_perfil')) {
                $fotoAnterior = $user->foto_perfil;
                $user->foto_perfil = $request->hasFile('foto_perfil_archivo')
                    ? $request->file('foto_perfil_archivo')->store('fotos-perfil', 'local') : null;
                if ($request->hasFile('foto_perfil_archivo') && !$user->foto_perfil) {
                    throw new \RuntimeException('No se pudo guardar la foto de perfil.');
                }
            }
            if ($emailChanged) $user->email_verified_at = null;
            $user->save();

            return match (true) {
                $request->hasFile('foto_perfil_archivo') || $request->boolean('quitar_foto_perfil') => 'Tu foto de perfil se actualizó.',
                isset($data['details']['billing_address']) => 'Tu domicilio de facturación se actualizó.',
                isset($data['details']['location']) => 'Tu ubicación se actualizó.',
                array_key_exists('company', $data) => 'Tus datos de organización se actualizaron.',
                default => 'Tus datos personales y de contacto se actualizaron.',
            };
        });

        // La cuenta de la sesión conserva la misma versión que se guardó.
        $request->user()->refresh();
        if ($fotoAnterior && $request->user()->foto_perfil !== $fotoAnterior) {
            Storage::disk('local')->delete($fotoAnterior);
        }

        return back()->with('notice', $notice);
    }

    // La foto se entrega solo a la cuenta autenticada y nunca se publica como archivo abierto.
    public function foto(Request $request)
    {
        $ruta = $request->user()->foto_perfil;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
