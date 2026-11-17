<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_enters_management_even_after_visiting_the_client_summary(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->withSession(['url.intended' => url('/cliente'), 'pending_estimate' => ['key' => 'calculo-pendiente']])
            ->post('/ingresar', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/gestion')->assertSessionHas('pending_estimate');
    }

    public function test_default_destination_matches_the_authenticated_role(): void
    {
        $client = User::factory()->create();
        $this->post('/ingresar', ['email' => $client->email, 'password' => 'password'])->assertRedirect('/cliente');
        $this->post('/salir');
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->post('/ingresar', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/gestion');
    }

    public function test_admin_can_resume_an_explicit_management_destination(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->withSession(['url.intended' => url('/admin/tarifario')])
            ->post('/ingresar', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/tarifario');
    }
}
