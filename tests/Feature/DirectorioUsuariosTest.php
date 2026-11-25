<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get('/admin/usuarios')->assertRedirect('/ingresar');
        $this->actingAs($client)->get('/admin/usuarios')->assertForbidden()
            ->assertInertia(fn(Assert $page) => $page->missing('users'));
        $this->get('/cliente')->assertInertia(fn(Assert $page) => $page->missing('users'));
        $this->get('/admin/usuarios/' . $client->id)->assertForbidden()
            ->assertInertia(fn(Assert $page) => $page->missing('selectedUser')->missing('clientProjects'));

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('EspacioTrabajo')
                ->where('section', 'usuarios')->has('users.data', 2)
                ->has('users.data.0', fn(Assert $row) => $row
                    ->where('id', $admin->id)->where('name', $admin->name)->where('email', $admin->email)
                    ->where('is_admin', true)->has('created_at')->where('rol_organizacion', null))
                ->where('users.data.1.is_admin', false));
    }

    // El filtro por nombre o correo conserva la página y solo entrega una cantidad acotada de cuentas.
    public function test_directory_searches_and_paginates_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $first = User::factory()->create(['name' => 'Estudio Norte', 'email' => 'equipo@example.test']);
        User::factory()->count(20)->create(['name' => 'Estudio asociado']);
        $this->actingAs($admin);

        $this->get('/admin/usuarios?buscar=ESTUDIO')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('userSearch', 'ESTUDIO')
                ->has('users.data', 20)->where('users.total', 21)
                ->where('users.next_page_url', fn(string $url) => str_contains($url, 'buscar=ESTUDIO')));

        $this->get('/admin/usuarios?buscar=ESTUDIO&page=2')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $first->id));

        $this->get('/admin/usuarios?buscar=equipo%40example.test')->assertOk()
            ->assertInertia(fn(Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $first->id));
    }

    // Un rol del equipo nunca convierte a su titular en administrador de la plataforma.
    public function test_administrator_can_create_and_assign_team_accounts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $cliente = User::factory()->create();
        $alta = ['modo' => 'nueva', 'email' => 'equipo@example.test', 'name' => 'Profesional de prueba',
            'password' => 'ClaveTemporal123!', 'rol' => 'supervisor'];

        $this->actingAs($cliente)->post('/admin/usuarios/equipo', $alta)->assertForbidden();
        $this->actingAs($admin)->post('/admin/usuarios/equipo', $alta)->assertSessionHasNoErrors();
        $usuario = User::where('email', $alta['email'])->firstOrFail();
        $this->assertFalse($usuario->is_admin);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($alta['password'], $usuario->password));
        $this->post('/admin/usuarios/equipo', $alta)->assertSessionHasErrors('email');
        $this->post('/admin/usuarios/equipo', ['modo' => 'existente', 'email' => $usuario->email, 'rol' => 'profesional'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('organizacion_miembros', ['user_id' => $usuario->id, 'rol' => 'profesional']);
        $this->get('/admin/usuarios?buscar=equipo%40example.test')->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.rol_organizacion', 'profesional'));
        $this->actingAs($usuario)->get('/admin/usuarios')->assertForbidden();
    }
}
