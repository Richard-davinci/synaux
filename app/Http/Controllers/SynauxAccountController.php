<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SynauxAccountController extends Controller
{
    // Solo expone los datos de la cuenta autenticada; el rol se muestra pero no se recibe del formulario.
    public function edit(Request $request)
    {
        $user = $request->user();
        $brand = DB::table('professional_profiles')->where('user_id', $user->id)->value('brand_name');
        return Inertia::render('AccountProfile', [
            'profile' => [...$user->contactDetails(), 'is_admin' => (bool) $user->is_admin, 'created_at' => $user->created_at?->toDateString()],
            'issuer' => $user->is_admin ? $user->issuerDetails($brand) : null,
            'area' => $user->is_admin ? 'gestion' : 'cliente',
        ]);
    }

    // Cambiar el correo exige la contraseña actual. El contacto se usa solamente en pedidos nuevos.
    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $emailChanged = $request->input('email') !== $user->email;
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:50',
            'company' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'current_password' => $emailChanged ? ['required', 'current_password:web'] : ['nullable'],
        ]);
        foreach (['name', 'email', 'phone', 'company', 'address'] as $field) {
            if (array_key_exists($field, $data)) $user->$field = $data[$field];
        }
        if ($emailChanged) $user->email_verified_at = null;
        $user->save();

        return back()->with('notice', 'Tus datos personales y de contacto se actualizaron.');
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

    // Administración guarda la identidad del emisor sin pasar por el perfil público de la segunda etapa.
    public function updateIssuer(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|max:254',
            'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:500',
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            foreach (['name', 'email', 'phone', 'address'] as $field) $user->{'issuer_'.$field} = $data[$field] ?? null;
            $user->save();
            // La relación de emisión del modelo usa este registro interno, sin publicar un perfil profesional.
            $profile = DB::table('professional_profiles')->where('user_id', $user->id)->first();
            if ($profile) {
                DB::table('professional_profiles')->where('id', $profile->id)->update(['brand_name' => $data['name'], 'updated_at' => now()]);
            } else {
                DB::table('professional_profiles')->insert([
                    'user_id' => $user->id, 'brand_name' => $data['name'], 'headline' => 'Gestión de proyectos digitales',
                    'bio' => 'Perfil interno de emisión de presupuestos.', 'availability_status' => 'unavailable', 'active' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return back()->with('notice', 'Los datos de emisión se actualizaron. Se usarán en los próximos presupuestos.');
    }
}
