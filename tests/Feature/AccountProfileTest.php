<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// La edición del perfil modifica solo la identidad de la sesión y nunca concede permisos.
class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication_and_displays_only_the_current_user(): void
    {
        $this->get('/mi-perfil')->assertRedirect('/ingresar');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/mi-perfil')->assertInertia(fn (Assert $page) => $page
            ->component('AccountProfile')->where('profile.email', $user->email)->missing('profile.password'));
        $this->patch('/mi-perfil', ['name' => 'Nombre actualizado', 'email' => $user->email, 'is_admin' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame('Nombre actualizado', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_email_change_requires_the_current_password_and_a_unique_address(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $data = ['name' => $user->name, 'email' => 'nuevo@example.test'];
        $this->actingAs($user)->patch('/mi-perfil', $data)->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->patch('/mi-perfil', [...$data, 'email' => $other->email, 'current_password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->patch('/mi-perfil', [...$data, 'email' => 'NUEVO@example.test', 'current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('nuevo@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
