<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SynauxAccountController extends Controller
{
    // Expone únicamente los datos personales de la cuenta autenticada.
    public function edit(Request $request)
    {
        return Inertia::render('AccountProfile', [
            'profile' => $request->user()->only('name', 'email'),
            'area' => $request->user()->is_admin ? 'gestion' : 'cliente',
        ]);
    }

    // Cambiar el correo exige la contraseña actual. Los permisos se administran por separado.
    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $emailChanged = $request->input('email') !== $user->email;
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => $emailChanged ? ['required', 'current_password:web'] : ['nullable'],
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        return back()->with('notice', 'Tu perfil se actualizó.');
    }
}
