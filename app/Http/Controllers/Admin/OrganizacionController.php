<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrganizacionController extends Controller
{
    // En la primera etapa, Administración edita la ficha del espacio inicial de synaux.
    public function edit()
    {
        $organizacion = Organizacion::principal();

        return Inertia::render('OrganizacionAdministracion', [
            'area' => 'gestion',
            'organizacion' => $organizacion->only('nombre', 'tipo', 'razon_social', 'cuit', 'correo', 'telefono', 'domicilio', 'dominio_correo_equipo'),
            'equipo' => $organizacion->miembros()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn($usuario) => ['id' => $usuario->id, 'nombre' => $usuario->name, 'rol' => $usuario->pivot->rol]),
        ]);
    }

    // Guarda los datos compartidos. Las versiones emitidas siguen leyendo su propia instantánea.
    public function update(Request $request)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|max:254',
            'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:500',
            'cuit' => ['sometimes', 'nullable', 'string', 'regex:/\A(?:[0-9]{11}|[0-9]{2}-[0-9]{8}-[0-9])\z/'],
        ], [
            'cuit.regex' => 'Ingresá un CUIT de 11 dígitos, con o sin guiones.',
        ]);

        $organizacion = Organizacion::principal();
        $organizacion->razon_social = $data['name'];
        $organizacion->correo = Str::lower($data['email']);
        $organizacion->telefono = $data['phone'] ?? null;
        $organizacion->domicilio = $data['address'] ?? null;
        if (array_key_exists('cuit', $data)) {
            $digitos = str_replace('-', '', $data['cuit'] ?? '');
            $organizacion->cuit = $digitos === '' ? null : substr($digitos, 0, 2) . '-' . substr($digitos, 2, 8) . '-' . substr($digitos, 10);
        }
        $organizacion->save();

        return back()->with('notice', 'Actualizamos los datos de la organización.');
    }

    // El dominio se puede configurar sin modificar los datos fiscales del emisor.
    public function actualizarDominio(Request $request)
    {
        $data = $request->validate([
            'dominio_correo_equipo' => ['required', 'string', 'max:253',
                'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/i'],
        ], ['dominio_correo_equipo.regex' => 'Ingresá solo el dominio, sin @ ni https://.']);

        $organizacion = Organizacion::principal();
        $organizacion->dominio_correo_equipo = Str::lower($data['dominio_correo_equipo']);
        $organizacion->save();

        return back()->with('notice', 'Actualizamos el dominio de correo del equipo.');
    }
}
