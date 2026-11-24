<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

// Autenticación real con sesiones de Laravel; ningún control visual concede acceso administrativo.
class AuthController extends Controller
{
    // Valida y crea una cuenta sin privilegios, inicia la sesión y conserva el cálculo pendiente.
    public function register(Request $request)
    {
        // La validación de unicidad usa el mismo correo normalizado que se guarda en la cuenta.
        $request->merge(['email' => Str::lower((string)$request->input('email'))]);
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:254|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)]]);
        $user = User::create(['name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => Hash::make($data['password'])]);
        Auth::login($user);
        $request->session()->regenerate();

        if (app(\App\Services\Solicitudes\RecorridoSolicitud::class)->pending($request)) {
            $request->session()->forget('url.intended');
            return redirect('/solicitudes/nueva?continuar=1');
        }

        return redirect($request->session()->has('pending_estimate') ? '/estimador?paso=4' : '/cliente');
    }

    // Valida las credenciales, limita intentos fallidos y regenera la sesión después del acceso.
    public function login(Request $request)
    {
        $request->merge(['email' => Str::lower((string)$request->input('email'))]);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = Str::lower($data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Esperá un minuto antes de volver a intentar.']);
        }
        if (!Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        // El borrador previo al ingreso solo se puede retomar con una cuenta de cliente.
        $admin = $request->user()->is_admin;
        if (!$admin && app(\App\Services\Solicitudes\RecorridoSolicitud::class)->pending($request)) {
            $request->session()->forget('url.intended');
            return redirect('/solicitudes/nueva?continuar=1');
        }

        // Gestión es el inicio del administrador. Un acceso previo al resumen cliente no reemplaza ese destino.
        $intended = $request->session()->get('url.intended');
        if ($admin && is_string($intended) && parse_url($intended, PHP_URL_PATH) === '/cliente') {
            $request->session()->forget('url.intended');
        }
        $destination = $admin ? '/gestion'
            : ($request->session()->has('pending_estimate') ? '/estimador?paso=4' : '/cliente');

        return redirect()->intended($destination);
    }

    // Cierra la sesión e invalida sus datos y token antes de volver al catálogo público.
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/plataforma');
    }

    // Solicita la recuperación mediante el broker de Laravel sin revelar si existe la cuenta.
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        Password::sendResetLink($data);

        // La misma respuesta evita revelar si el correo está registrado.
        return back()->with('notice', 'Si existe una cuenta con ese correo, se generarán las instrucciones de recuperación.');
    }

    // Comprueba el token de recuperación antes de actualizar la contraseña y el token de recuerdo.
    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o venció. Solicitá uno nuevo.']);
        }

        return redirect('/ingresar')->with('notice', 'La contraseña se actualizó. Ya podés ingresar.');
    }
}
