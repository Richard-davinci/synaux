<?php

namespace Tests\Feature;

use App\Models\Organizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectorioUsuariosTest extends TestCase
{
    use RefreshDatabase;

    // El listado solo se entrega a cuentas de administración, sin consultar cuentas en las páginas públicas.
    public function test_only_an_administrator_can_view_the_user_directory(): void
    {
        $client = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $organizacion = Organizacion::principal();
        $organizacion->miembros()->attach($admin->id, ['rol' => 'administrador']);
        $organizacion->clientes()->attach($client->id);
        $this->get('/admin/usuarios')->assertRedirect('/ingresar');
        $this->actingAs($client)->get('/admin/usuarios')->assertForbidden()
            ->assertInertia(fn(Assert $page) => $page->missing('users'));
        $this->get('/cliente')->assertInertia(fn(Assert $page) => $page->missing('users'));
        $this->get('/gestion/clientes/' . $client->id)->assertForbidden()
            ->assertInertia(fn(Assert $page) => $page->missing('selectedUser')->missing('clientProjects'));

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('EspacioTrabajo')
                ->where('section', 'usuarios')->has('users.data', 1)
                ->has('users.data.0', fn(Assert $row) => $row
                    ->where('id', $admin->id)->where('name', $admin->name)->where('email', $admin->email)
                    ->where('is_admin', true)->has('created_at')->where('rol_organizacion', 'administrador')->where('activacion_pendiente', false)));
        $this->get('/gestion/clientes')->assertInertia(fn(Assert $page) => $page
            ->where('section', 'clientes')->has('users.data', 1)->where('users.data.0.id', $client->id));
    }

    // El filtro por nombre o correo conserva la página y solo entrega una cantidad acotada de cuentas.
    public function test_directory_searches_and_paginates_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $first = User::factory()->create(['name' => 'Estudio Norte', 'email' => 'equipo@example.test']);
        $otros = User::factory()->count(20)->create(['name' => 'Estudio asociado']);
        Organizacion::principal()->miembros()->attach($otros->pluck('id')->push($first->id), ['rol' => 'profesional']);
        $this->actingAs($admin);

        $this->get('/admin/usuarios?buscar=ESTUDIO')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('userSearch', 'ESTUDIO')
                ->has('users.data', 20)->where('users.total', 21)
                ->where('users.next_page_url', fn(string $url) => str_contains($url, 'buscar=ESTUDIO')));

        $this->get('/admin/usuarios?buscar=ESTUDIO&page=2')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $first->id));

        // El tamaño elegido permanece en las páginas siguientes y Todos incluye los resultados filtrados.
        $this->get('/admin/usuarios?buscar=ESTUDIO&por_pagina=5')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('userPageSize', '5')
                ->has('users.data', 5)->where('users.total', 21)
                ->where('users.next_page_url', fn(string $url) => str_contains($url, 'por_pagina=5')
                    && str_contains($url, 'buscar=ESTUDIO')));
        $this->get('/admin/usuarios?buscar=ESTUDIO&por_pagina=todos')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('userPageSize', 'todos')
                ->has('users.data', 21)->where('users.last_page', 1));

        $clientes = User::factory()->count(6)->create();
        Organizacion::principal()->clientes()->attach($clientes->pluck('id'));
        $this->get('/gestion/clientes?por_pagina=5')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('userPageSize', '5')
                ->has('users.data', 5)->where('users.total', 6));
        $this->get('/gestion/clientes?por_pagina=5&page=2')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('users.data', 1));
        $this->get('/gestion/clientes?por_pagina=todos')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('users.data', 6)
                ->where('users.last_page', 1));
        $this->get('/gestion/clientes/' . $clientes->first()->id . '?por_pagina=5&pagina_usuarios=2')
            ->assertInertia(fn(Assert $page) => $page
                ->where('userDirectoryUrl', '/gestion/clientes?por_pagina=5&page=2'));
        $this->get('/admin/usuarios?buscar=ESTUDIO&por_pagina=999999')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('userPageSize', '20')
                ->has('users.data', 20));

        $this->get('/admin/usuarios?buscar=equipo%40example.test')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $first->id));
    }

    // Un rol del equipo nunca convierte a su titular en administrador de la plataforma.
    public function test_administrator_can_create_and_assign_team_accounts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $cliente = User::factory()->create(['email' => 'cliente@example.test']);
        Organizacion::principal()->clientes()->attach($cliente->id);
        $alta = ['email' => 'equipo@example.test', 'name' => 'Profesional de prueba',
            'rol' => 'supervisor'];

        $this->actingAs($cliente)->post('/admin/usuarios/equipo', $alta)->assertForbidden();
        $this->actingAs($admin)->post('/admin/usuarios/equipo', $alta)->assertSessionHasErrors('email');
        $this->patch('/admin/organizacion/dominio-equipo', ['dominio_correo_equipo' => 'Example.test'])->assertSessionHasNoErrors();
        $this->post('/admin/usuarios/equipo', [...$alta, 'email' => 'persona@ajeno.test'])->assertSessionHasErrors('email');
        $this->post('/admin/usuarios/equipo', [...$alta, 'email' => $cliente->email])->assertSessionHasErrors('email');
        $this->patch('/admin/usuarios/' . $cliente->id . '/rol', ['rol' => 'supervisor'])->assertNotFound();
        $this->post('/admin/usuarios/equipo', $alta)->assertSessionHasNoErrors();
        $usuario = User::where('email', $alta['email'])->firstOrFail();
        $this->assertFalse($usuario->is_admin);
        $this->assertTrue($usuario->activacion_pendiente);
        $this->post('/admin/usuarios/equipo', $alta)->assertSessionHasErrors('email');
        $this->patch('/admin/usuarios/' . $usuario->id . '/rol', ['email' => 'otro@example.test', 'rol' => 'profesional'])->assertSessionHasErrors('email');
        $this->patch('/admin/usuarios/' . $usuario->id . '/rol', ['rol' => 'profesional'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('organizacion_miembros', ['user_id' => $usuario->id, 'rol' => 'profesional']);
        $this->get('/admin/usuarios?buscar=equipo%40example.test')->assertInertia(fn(Assert $page) => $page
            ->where('users.data.0.rol_organizacion', 'profesional')->where('users.data.0.activacion_pendiente', true));
        // Únicamente Administración puede compartir, y cada enlace nuevo reemplaza al anterior.
        $ruta = '/admin/usuarios/' . $usuario->id . '/activacion';
        $this->actingAs($cliente)->postJson($ruta)->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/usuarios/' . $cliente->id . '/activacion')->assertNotFound();
        $anterior = parse_url($this->postJson($ruta)->assertOk()->json('enlace'), PHP_URL_PATH);
        $vencido = parse_url($this->postJson($ruta)->assertOk()->json('enlace'), PHP_URL_PATH);
        $usuario->forceFill(['activacion_vence' => now()->subMinute()])->save();
        $this->post('/salir');
        $this->get($vencido)->assertInertia(fn(Assert $page) => $page->where('vigente', false));
        $this->actingAs($admin);
        $vigente = parse_url($this->postJson($ruta)->assertOk()->json('enlace'), PHP_URL_PATH);
        $this->assertNotSame($anterior, $vigente);
        $this->assertNotSame(basename($vigente), $usuario->fresh()->activacion_hash);

        $this->post('/salir');
        // Una cuenta pendiente necesita el enlace incluso si alguien conoce sus credenciales anteriores.
        $clave = 'ClaveElegida123!';
        $usuario->forceFill(['password' => $clave])->save();
        $this->post('/ingresar', ['email' => $usuario->email, 'password' => $clave])->assertSessionHasErrors('email');
        $this->get($anterior)->assertInertia(fn(Assert $page) => $page->where('vigente', false));
        $this->get($vigente)->assertInertia(fn(Assert $page) => $page->where('vigente', true));
        $this->post($vigente, ['password' => $clave, 'password_confirmation' => 'distinta'])->assertSessionHasErrors('password');
        $this->post($vigente, ['password' => $clave, 'password_confirmation' => $clave])->assertRedirect('/ingresar');
        $this->get($vigente)->assertInertia(fn(Assert $page) => $page->where('vigente', false));
        $this->post('/ingresar', ['email' => $usuario->email, 'password' => $clave])->assertRedirect('/profesional/proyectos');
        $this->get('/admin/usuarios')->assertForbidden();
        $this->patch('/mi-perfil', ['email' => 'otro@example.test', 'current_password' => $clave])->assertSessionHasErrors('email');
        $this->assertSame($alta['email'], $usuario->fresh()->email);
        $this->actingAs($admin)->delete('/admin/usuarios/' . $usuario->id . '/equipo')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('organizacion_miembros', ['user_id' => $usuario->id]);
        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'email' => $alta['email']]);
    }

    // Una membresía usa el espacio que la emitió y nunca habilita borradores privados de otros usuarios.
    public function test_synaux_team_role_controls_the_login_and_workspace_permissions(): void
    {
        config(['synaux.stage_two_enabled' => false]);
        $this->seed(\Database\Seeders\SynauxCatalogSeeder::class);
        $organizacion = \App\Models\Organizacion::principal();
        $gestora = User::factory()->create();
        $cliente = User::factory()->create();
        $organizacion->miembros()->attach($gestora->id, ['rol' => 'administrador']);
        $organizacion->clientes()->attach($cliente->id);
        $solicitud = DB::table('project_requests')->insertGetId([
            'user_id' => $cliente->id, 'service_id' => DB::table('services')->value('id'),
            'title' => 'Solicitud enviada a synaux', 'description' => 'Pedido que debe compartir el equipo.', 'status' => 'open',
        ]);
        $borrador = DB::table('project_requests')->insertGetId([
            'user_id' => $cliente->id, 'title' => 'Borrador privado', 'description' => 'Sigue siendo privado.',
        ]);

        $this->withSession(['url.intended' => '/cliente/solicitudes'])
            ->post('/ingresar', ['email' => $gestora->email, 'password' => 'password'])->assertRedirect('/gestion');
        $this->get('/gestion/solicitudes')->assertInertia(fn(Assert $page) => $page
            ->where('auth.is_admin', false)->where('auth.puede_gestionar', true)->where('auth.puede_administrar', true)
            ->where('auth.panel_principal', '/gestion')->has('requests', 1)->where('requests.0.id', $solicitud));
        $this->get("/solicitudes/$solicitud")->assertInertia(fn(Assert $page) => $page
            ->where('area', 'gestion')->where('selected.id', $solicitud));
        $this->get("/solicitudes/$borrador")->assertNotFound();
        $this->get('/admin/servicios')->assertOk();
        $this->get('/admin/usuarios')->assertOk();
        $this->get('/gestion/clientes')->assertOk();
        $this->post('/admin/guardar/categorias', [
            'name' => 'Prueba de permisos', 'slug' => 'prueba-permisos',
            'description' => 'Categoría creada por una administradora del equipo.', 'active' => false,
        ])->assertSessionHasNoErrors();
        $this->get('/cliente')->assertForbidden();

        $organizacion->miembros()->updateExistingPivot($gestora->id, ['rol' => 'supervisor']);
        $this->get('/gestion/solicitudes')->assertInertia(fn(Assert $page) => $page
            ->where('auth.rol_organizacion', 'supervisor')->where('auth.puede_administrar', false)
            ->has('requests', 1)->where('requests.0.id', $solicitud));
        $this->get('/gestion/clientes')->assertOk();
        $this->get('/admin/servicios')->assertForbidden();
        $this->patch('/admin/organizacion/dominio-equipo', ['dominio_correo_equipo' => 'example.com'])->assertForbidden();

        $organizacion->miembros()->updateExistingPivot($gestora->id, ['rol' => 'profesional']);
        $this->get('/gestion')->assertForbidden();
        $this->get('/cliente')->assertForbidden();
        $this->get("/solicitudes/$solicitud")->assertNotFound();
        $this->get('/mi-perfil')->assertInertia(fn(Assert $page) => $page->where('auth.panel_principal', '/profesional/proyectos'));
        config(['synaux.stage_two_enabled' => true]);
        $this->get('/mi-perfil')->assertInertia(fn(Assert $page) => $page->where('auth.panel_principal', '/profesional/proyectos'));
        $this->get('/profesional')->assertOk();

        $organizacion->miembros()->detach($gestora->id);
        $otra = DB::table('organizaciones')->insertGetId(['slug' => 'otra', 'nombre' => 'Otra organización', 'tipo' => 'empresa']);
        DB::table('organizacion_miembros')->insert(['organizacion_id' => $otra, 'user_id' => $gestora->id, 'rol' => 'administrador']);
        $this->get('/gestion')->assertForbidden();
        $this->get('/admin/servicios')->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $gestora->id, 'is_admin' => false]);
    }

}
