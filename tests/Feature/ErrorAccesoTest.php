<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorAccesoTest extends TestCase
{
    use RefreshDatabase;

    // El visitante recibe una pantalla de ingreso y recupera el destino al autenticarse con una cuenta autorizada.
    public function test_guest_sees_sign_in_screen_and_resumes_the_protected_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->get('/ingresar')->assertInertia(fn(Assert $page) => $page
            ->where('screen', 'ingreso')->where('loginRequired', false));

        $this->get('/admin/servicios?buscar=web')->assertRedirect('/ingresar')
            ->assertSessionHas('url.intended', url('/admin/servicios?buscar=web'));

        $this->get('/ingresar')->assertInertia(fn(Assert $page) => $page
            ->where('screen', 'ingreso')->where('loginRequired', true)->missing('administration'));

        $this->post('/ingresar', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/servicios?buscar=web');
    }

    // El acceso inválido recomienda el panel de la sesión sin cargar datos del área restringida.
    public function test_forbidden_page_points_to_the_account_workspace(): void
    {
        $client = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($client)->get('/admin/servicios')->assertForbidden()
            ->assertInertia(fn(Assert $page) => $page->component('ErrorAcceso')
                ->where('status', 403)->where('accountArea', 'client')->missing('administration'));

        $this->actingAs($admin)->get('/cliente/presupuestos')->assertForbidden()
            ->assertInertia(fn(Assert $page) => $page->component('ErrorAcceso')
                ->where('status', 403)->where('accountArea', 'management')->missing('quotes'));
    }

    // Los enlaces inexistentes funcionan con Inertia, y JSON conserva el formato del framework.
    public function test_missing_page_supports_inertia_navigation_and_leaves_json_requests_alone(): void
    {
        $this->get('/ruta-inexistente', ['X-Inertia' => 'true'])->assertNotFound()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'ErrorAcceso')->assertJsonPath('props.status', 404);

        $this->getJson('/admin/servicios')->assertUnauthorized()
            ->assertJsonStructure(['message'])->assertJsonMissingPath('component');
    }
}
