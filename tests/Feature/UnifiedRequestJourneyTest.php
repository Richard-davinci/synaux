<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// El recorrido público guarda solo un borrador de sesión; la creación de registros requiere una cuenta.
class UnifiedRequestJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['synaux.stage_two_enabled' => false]);
        $this->seed([SynauxCatalogSeeder::class, SynauxInstitutionalTariffSeeder::class]);
    }

    private function project(string $slug = 'sitio-institucional'): array
    {
        $service = DB::table('services')->where('slug', $slug)->value('id');
        $answers = [];
        if ($slug === 'sitio-institucional') {
            $question = DB::table('estimator_questions')->where('service_id', $service)->where('active', 1)->first();
            $answers[$question->id] = DB::table('estimator_options')->where('question_id', $question->id)->where('label', 'PyME')->value('id');
        }
        return ['service_id' => $service, 'answers' => $answers, 'title' => 'Sitio para mi organización',
            'description' => 'Queremos presentar nuestros servicios y recibir consultas.',
            'skills' => '', 'budget_reference' => '', 'desired_deadline' => 'Durante el próximo mes',
            'additional_services' => [['service_id' => DB::table('services')->where('slug', 'identidad-visual')->value('id'), 'quantity' => 1, 'notes' => 'Necesitamos una guía de marca.']],
            'destination' => 'login'];
    }

    // El editor recibe el precio unitario histórico de cada servicio, aun con cantidades distintas.
    public function test_budget_editor_gets_requested_prices_and_recovers_legacy_additional_references(): void
    {
        $this->seed(\Database\Seeders\SynauxLandingTariffSeeder::class);
        $client = User::factory()->create();
        $manager = User::factory()->create(['is_admin' => true]);
        $data = $this->project();
        $extra = DB::table('services')->where('slug', 'landing-page')->value('id');
        $data['additional_services'][] = ['service_id' => $extra, 'quantity' => 2];
        $this->actingAs($client)->post('/acciones/solicitud', [
            ...$data, 'visibility' => 'private', 'publish' => true,
        ])->assertSessionHasNoErrors();
        $record = DB::table('project_requests')->where('user_id', $client->id)->first();
        $extras = json_decode($record->requested_services, true);
        $this->assertNull($extras[0]['pricing_reference']);
        $this->assertEquals(509160, $extras[1]['pricing_reference']['min']);

        $url = '/editor/presupuesto?solicitud='.$record->id;
        $this->actingAs($manager)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('budgetRequests.0.pricing_reference.min', 1352480)
            ->where('budgetRequests.0.pricing_reference.currency', 'ARS')
            ->where('budgetRequests.0.additional_services.1.quantity', 2)
            ->where('budgetRequests.0.additional_services.1.pricing_reference.min', 509160));

        unset($extras[1]['pricing_reference']);
        DB::table('project_requests')->where('id', $record->id)->update(['requested_services' => json_encode($extras)]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('budgetRequests.0.additional_services.1.pricing_reference.min', 509160)
            ->where('budgetRequests.0.additional_services.1.pricing_reference.recovered', true));
    }

    // El rol de Gestión conserva su panel y no puede crear pedidos ni retomar un borrador de cliente.
    public function test_admin_uses_management_without_submitting_a_client_request(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $data = $this->project();
        $this->post('/solicitudes/preparar', [...$data, 'destination' => 'review'])
            ->assertRedirect('/solicitudes/nueva?continuar=1');
        $this->post('/ingresar', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/gestion');
        foreach (['/cliente', '/cliente/solicitudes', '/estimador', '/solicitudes/nueva'] as $path) {
            $this->get($path)->assertRedirect('/gestion');
        }
        foreach (['/solicitudes/preparar', '/estimador/calcular', '/acciones/guardar-estimacion', '/acciones/solicitud'] as $path) {
            $this->post($path, [...$data, 'visibility' => 'private', 'publish' => true])->assertForbidden();
        }
        $this->get('/gestion')->assertInertia(fn (Assert $page) => $page->where('area', 'gestion'));
        $this->get('/editor/presupuesto')->assertOk();
        $this->assertDatabaseCount('project_requests', 0);
        $this->assertDatabaseCount('estimates', 0);
    }

    public function test_public_entry_points_share_one_form_without_exposing_private_records(): void
    {
        foreach (['/estimador', '/solicitudes/nueva'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('screen', 'publicar')->where('area', 'public')->where('auth', null)
                ->has('requestQuestions')->missing('requests')->missing('estimates')->missing('quotes'));
        }
        $this->get('/solicitudes/nueva?estimacion=5')->assertRedirect('/ingresar');
        $this->post('/acciones/solicitud', $this->project())->assertRedirect('/ingresar');
        $this->assertDatabaseCount('project_requests', 0);
    }

    public function test_guest_project_survives_login_and_only_creates_records_after_confirmation(): void
    {
        $client = User::factory()->create();
        $client->phone = '1122334455';
        $client->company = 'Organización de prueba';
        $client->save();
        $data = $this->project();
        $this->get('/solicitudes/nueva');
        $this->post('/solicitudes/preparar', [...$data, 'destination' => 'review'])
            ->assertRedirect('/solicitudes/nueva?continuar=1')->assertSessionHasNoErrors();
        $preparedKey = session('pending_request.calculation.key');
        $this->get('/solicitudes/nueva?continuar=1')->assertInertia(fn (Assert $page) => $page
            ->where('requestStep', 3)->where('requestDraft.title', $data['title']));
        $this->post('/solicitudes/preparar', [...$data, 'price_min' => 1, 'estimate_id' => 999,
            'contact' => ['name' => 'No guardar', 'email' => 'no-guardar@example.test'],
        ])->assertRedirect('/ingresar')->assertSessionHasNoErrors()
            ->assertSessionHas('pending_request.calculation.min', 1352480)
            ->assertSessionHas('pending_request.calculation.key', $preparedKey)
            ->assertSessionMissing('pending_request.form.contact')->assertSessionMissing('pending_request.form.estimate_id');
        $this->assertDatabaseCount('project_requests', 0);
        $this->assertDatabaseCount('estimates', 0);
        $this->post('/ingresar', ['email' => $client->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        $this->assertNotNull(session('pending_request'));
        $this->post('/ingresar', ['email' => $client->email, 'password' => 'password'])->assertRedirect('/solicitudes/nueva?continuar=1');
        $this->get('/solicitudes/nueva?continuar=1')->assertInertia(fn (Assert $page) => $page
            ->where('requestStep', 3)->where('requestDraft.title', $data['title'])
            ->where('accountContact.phone', $client->phone)->where('accountContact.email', $client->email)
            ->where('requestDraft.additional_services.0.notes', $data['additional_services'][0]['notes']));
        $this->post('/acciones/solicitud', [...$data, 'visibility' => 'private', 'publish' => true, 'contact' => $client->contactDetails()])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('project_requests', 1);
        $this->assertDatabaseCount('estimates', 1);
        $estimate = DB::table('estimates')->first();
        $this->assertEquals(1352480, $estimate->price_min);
        $this->assertDatabaseHas('project_requests', ['estimate_id' => $estimate->id, 'status' => 'open', 'user_id' => $client->id]);
        $this->assertNull(session('pending_request'));
    }

    public function test_registration_resumes_a_manual_project_without_asking_the_description_twice(): void
    {
        $data = $this->project('landing-page');
        $this->post('/solicitudes/preparar', [...$data, 'destination' => 'register'])
            ->assertRedirect('/crear-cuenta')->assertSessionHasNoErrors()
            ->assertSessionHas('pending_request.calculation.mode', 'manual');
        $this->post('/crear-cuenta', ['name' => 'Cuenta nueva', 'email' => 'nueva@example.test',
            'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!', 'is_admin' => true,
        ])->assertRedirect('/solicitudes/nueva?continuar=1');
        $this->get('/solicitudes/nueva')->assertInertia(fn (Assert $page) => $page
            ->where('requestStep', 3)->where('requestDraft.description', $data['description'])
            ->where('accountContact.name', 'Cuenta nueva'));
        $this->post('/acciones/solicitud', [...$data, 'visibility' => 'private', 'publish' => true,
            'contact' => ['name' => 'Cuenta nueva', 'email' => 'nueva@example.test'],
        ])->assertSessionHasNoErrors();
        $estimate = DB::table('estimates')->first();
        $this->assertSame('manual', $estimate->status);
        $this->assertNull($estimate->price_min);
        $this->assertSame($data['description'], json_decode($estimate->answers_snapshot, true)[0]['value']);
        $this->assertDatabaseHas('users', ['email' => 'nueva@example.test', 'is_admin' => 0]);
    }

    public function test_invalid_answers_do_not_replace_a_prepared_project_and_expired_drafts_are_discarded(): void
    {
        $data = $this->project();
        $this->post('/solicitudes/preparar', $data)->assertSessionHasNoErrors();
        $key = session('pending_request.calculation.key');
        $question = array_key_first($data['answers']);
        $this->from('/solicitudes/nueva')->post('/solicitudes/preparar', [...$data, 'answers' => [$question => 999999]])
            ->assertSessionHasErrors('answers.'.$question);
        $this->assertSame($key, session('pending_request.calculation.key'));
        $this->travel(3)->hours();
        $this->get('/solicitudes/nueva')->assertInertia(fn (Assert $page) => $page
            ->where('requestDraft', null)->where('requestStep', 1));
        $this->assertNull(session('pending_request'));
        $this->assertDatabaseCount('project_requests', 0);
        $this->travelBack();
    }
}
