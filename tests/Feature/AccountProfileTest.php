<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\SynauxCatalogSeeder;
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
            ->component('PerfilCuenta')->where('profile.email', $user->email)->missing('profile.password'));
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
    // El contacto se ofrece en pedidos nuevos y no se propaga a las instantáneas existentes.
    public function test_contact_details_prefill_new_requests_without_changing_existing_requests(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $user = User::factory()->create();
        $data = ['name' => 'Cliente de prueba', 'email' => $user->email, 'company' => 'Estudio Uno', 'phone' => '1122334455', 'address' => 'Dirección de prueba'];
        $this->actingAs($user)->patch('/mi-perfil', [...$data, 'issuer_name' => 'No autorizado'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->issuer_name);
        $this->get('/solicitudes/nueva')->assertInertia(fn (Assert $page) => $page
            ->where('accountContact.company', 'Estudio Uno')->where('accountContact.phone', $data['phone']));
        $this->post('/acciones/solicitud', [
            'service_id' => DB::table('services')->value('id'),
            'title' => 'Proyecto del estudio', 'description' => 'Necesitamos presentar nuestros servicios.',
            'visibility' => 'private', 'publish' => false,
        ])->assertSessionHasNoErrors();
        $request = DB::table('project_requests')->first();
        $this->assertSame('Estudio Uno', json_decode($request->contact_snapshot, true)['company']);
        $this->patch('/mi-perfil', [...$data, 'company' => 'Estudio Dos', 'phone' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->phone);
        $this->get('/solicitudes/nueva')->assertInertia(fn (Assert $page) => $page->where('accountContact.company', 'Estudio Dos'));
        $this->get('/solicitudes/'.$request->id)->assertInertia(fn (Assert $page) => $page
            ->where('selected.contact.company', 'Estudio Uno')->where('selected.contact.phone', $data['phone']));
        $this->patch('/mi-perfil/emision', ['name' => 'No autorizado', 'email' => 'otro@example.test'])->assertForbidden();
    }

    // El cambio de contraseña exige identidad, confirmación y una clave diferente; no altera otra cuenta.
    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherHash = $other->password;
        $this->actingAs($user)->put('/mi-perfil/clave', [
            'current_password' => 'incorrecta', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertSessionHasErrors('current_password');
        $this->put('/mi-perfil/clave', [
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'Diferente123!',
        ])->assertSessionHasErrors('password');
        $this->put('/mi-perfil/clave', [
            'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
        $this->put('/mi-perfil/clave', [
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NuevaClave123!', $user->fresh()->password));
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->get('/mi-perfil')->assertInertia(fn (Assert $page) => $page->missing('profile.password')->missing('profile.current_password'));
        $this->post('/salir');
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'NuevaClave123!'])->assertRedirect('/cliente');
    }

    // El administrador prepara su identidad de emisión desde Mi perfil y conserva cada documento enviado.
    public function test_admin_issuer_details_are_snapshotted_without_publishing_a_professional_profile(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $manager = User::factory()->create();
        $manager->forceFill(['is_admin' => true])->save();
        $client = User::factory()->create();
        $issuer = ['name' => 'Synaux Estudio', 'email' => 'presupuestos@example.test', 'phone' => '1100000000', 'address' => 'Dirección del estudio'];
        $this->actingAs($manager)->patch('/mi-perfil/emision', $issuer)->assertSessionHasNoErrors();
        $this->assertSame($manager->email, $manager->fresh()->email);
        $this->assertDatabaseHas('professional_profiles', ['user_id' => $manager->id, 'brand_name' => $issuer['name'], 'active' => 0]);
        $this->get('/mi-perfil')->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_admin', true)->where('issuer.email', $issuer['email']));
        $requestId = DB::table('project_requests')->insertGetId([
            'user_id' => $client->id, 'service_id' => DB::table('services')->value('id'),
            'title' => 'Proyecto de prueba', 'description' => 'Un proyecto para verificar la emisión.',
            'visibility' => 'private', 'status' => 'open',
        ]);
        $quote = ['request_id' => $requestId, 'title' => 'Presupuesto de prueba', 'currency_code' => 'ARS', 'tax_rate' => 0,
            'valid_until' => today()->addDays(14)->toDateString(), 'estimated_delivery_days' => 30,
            'terms' => 'Entrega y revisiones acordadas.', 'items' => [['description' => 'Servicio digital', 'quantity' => 1, 'unit_price' => 1000]], 'send' => true];
        $this->post('/acciones/presupuesto', $quote)->assertSessionHasNoErrors();
        $first = DB::table('budget_versions')->first();
        $this->assertSame($issuer['phone'], json_decode($first->issuer_snapshot, true)['phone']);
        $this->patch('/mi-perfil/emision', [...$issuer, 'name' => 'Synaux Nuevo', 'phone' => '1199999999'])->assertSessionHasNoErrors();
        $this->post('/acciones/presupuesto', [...$quote, 'budget_id' => $first->budget_id])->assertSessionHasNoErrors();
        $second = DB::table('budget_versions')->orderByDesc('id')->first();
        $this->assertSame('Synaux Nuevo', json_decode($second->issuer_snapshot, true)['name']);
        $this->actingAs($client)->get('/presupuestos/'.$first->id)->assertInertia(fn (Assert $page) => $page
            ->where('selected.issuer.name', $issuer['name'])->where('selected.issuer.phone', $issuer['phone']));
        $this->get('/presupuestos/'.$first->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

}
