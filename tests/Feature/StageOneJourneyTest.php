<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Recorre la primera etapa con sesiones, permisos y formularios reales en SQLite en memoria.
class StageOneJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_one_journey_from_guest_estimate_to_completed_project(): void
    {
        config(['synaux.stage_two_enabled' => false]);
        $this->seed([SynauxCatalogSeeder::class, SynauxInstitutionalTariffSeeder::class]);
        $client = User::factory()->create();
        $manager = User::factory()->create();
        $manager->forceFill(['is_admin' => true])->save();
        DB::table('professional_profiles')->insert([
            'user_id' => $manager->id, 'brand_name' => 'Synaux', 'headline' => 'Servicios digitales',
            'bio' => 'Equipo de Synaux.', 'availability_status' => 'available', 'active' => 1,
        ]);
        $service = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $extra = DB::table('services')->where('slug', 'identidad-visual')->value('id');
        $question = DB::table('estimator_questions')->where('service_id', $service)->where('active', 1)->value('id');
        $option = DB::table('estimator_options')->where('question_id', $question)->where('label', 'PyME')->value('id');

        // El cálculo público se recupera después del ingreso sin habilitar el marketplace.
        $this->get('/plataforma')->assertInertia(fn (Assert $page) => $page->where('stageTwo', false));
        $this->get('/servicios/sitio-institucional?cliente=Empresa')->assertInertia(fn (Assert $page) => $page
            ->where('initialClientType', 'Empresa'));
        $this->get('/servicios/sitio-institucional?cliente=Inventado')->assertInertia(fn (Assert $page) => $page
            ->where('initialClientType', null));
        $this->post('/estimador/calcular', ['service' => 'sitio-institucional', 'answers' => [$question => $option]])
            ->assertSessionHasNoErrors()->assertSessionHas('pending_estimate', fn ($estimate) => $estimate['min'] === 1352480);
        $this->post('/ingresar', ['email' => $client->email, 'password' => 'password'])->assertRedirect('/estimador?paso=4');
        $this->post('/acciones/guardar-estimacion', ['to_request' => true])->assertSessionHasNoErrors();
        $estimateId = DB::table('estimates')->where('user_id', $client->id)->value('id');
        $this->get('/solicitudes/nueva?estimacion='.$estimateId)->assertInertia(fn (Assert $page) => $page
            ->where('selectedEstimate.price_min', 1352480)->where('selectedEstimate.answers.0.label', 'PyME'));
        $this->get('/oportunidades')->assertNotFound();

        $request = [
            'estimate_id' => $estimateId, 'service_id' => $service, 'title' => 'Sitio institucional y marca',
            'description' => 'Necesitamos renovar el sitio y definir la identidad del estudio.',
            'visibility' => 'private', 'publish' => false,
            'contact' => ['name' => 'Contacto del estudio', 'email' => 'contacto@example.test', 'company' => 'Estudio de prueba'],
            'additional_services' => [['service_id' => $extra, 'quantity' => 1, 'notes' => 'Logotipo y guía de marca.']],
        ];
        $this->post('/acciones/solicitud', $request)->assertSessionHasNoErrors();
        $requestId = DB::table('project_requests')->value('id');
        $this->post('/acciones/solicitud', [...$request, 'id' => $requestId, 'publish' => true])->assertRedirect('/solicitudes/'.$requestId);
        $this->assertDatabaseHas('project_requests', ['id' => $requestId, 'status' => 'open', 'visibility' => 'private', 'published_at' => null]);

        // Gestión recibe el pedido y prepara un borrador que el cliente todavía no puede consultar.
        $this->post('/salir')->assertRedirect('/plataforma');
        $this->post('/ingresar', ['email' => $manager->email, 'password' => 'password'])->assertRedirect('/gestion');
        $this->get('/gestion/solicitudes')->assertInertia(fn (Assert $page) => $page->where('requests.0.id', $requestId));
        $this->get('/editor/presupuesto?solicitud='.$requestId)->assertInertia(fn (Assert $page) => $page
            ->where('budgetRequests.0.contact.email', $request['contact']['email'])
            ->where('budgetRequests.0.additional_services.0.service_id', $extra));
        $quote = [
            'request_id' => $requestId, 'title' => $request['title'], 'currency_code' => 'ARS',
            'tax_rate' => 21, 'valid_until' => today()->addDays(14)->toDateString(),
            'estimated_delivery_days' => 30, 'terms' => 'Dos revisiones. Anticipo del 50% e inicio a coordinar.',
            'items' => [
                ['description' => 'Sitio institucional', 'quantity' => 1, 'unit_price' => 1352480],
                ['description' => 'Identidad visual', 'quantity' => 1, 'unit_price' => 200000],
            ], 'send' => false,
        ];
        // El borrador permite excluir IVA; al incluirlo en la revisión el documento conserva subtotal, tasa y total.
        $this->post('/acciones/presupuesto', [...$quote, 'tax_rate' => 0])->assertSessionHasNoErrors();
        $draft = DB::table('budget_versions')->first();
        $this->get('/presupuestos/'.$draft->id)->assertInertia(fn (Assert $page) => $page
            ->where('selected.tax_rate', 0)->where('selected.subtotal', 1552480)->where('selected.total', 1552480));
        $this->actingAs($client)->get('/presupuestos/'.$draft->id)->assertNotFound();
        $this->actingAs($manager)->post('/acciones/presupuesto', [...$quote, 'budget_id' => $draft->budget_id, 'send' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('budget_versions', 1);

        // La aceptación de la versión enviada crea un solo proyecto y conserva el PDF acordado.
        $this->actingAs($client)->get('/presupuestos/'.$draft->id)->assertInertia(fn (Assert $page) => $page
            ->where('selected.current', true)->where('selected.status', 'sent')->where('selected.tax_rate', 21)
            ->where('selected.subtotal', 1552480)->where('selected.total', 1878500.8));
        $this->get('/presupuestos/'.$draft->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $decision = ['id' => $draft->id, 'decision' => 'accepted', 'confirmed' => true];
        $this->post('/acciones/decidir-presupuesto', $decision)->assertRedirect();
        $projectId = DB::table('projects')->value('id');
        $this->post('/acciones/decidir-presupuesto', $decision)->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('projects', 1);
        $this->get('/proyectos/'.$projectId)->assertInertia(fn (Assert $page) => $page
            ->where('selected.status', 'pending')->where('selected.progress', 0));
        $this->get('/gestion')->assertForbidden();

        $update = ['id' => $projectId, 'status' => 'in_progress', 'progress' => 60,
            'notes' => 'Diseño aprobado y desarrollo en curso.', 'next_steps' => 'El cliente revisará los contenidos.'];
        $this->post('/acciones/proyecto', $update)->assertForbidden();
        $this->actingAs($manager)->post('/acciones/proyecto', $update)->assertSessionHasNoErrors();
        $this->post('/acciones/proyecto', [...$update, 'status' => 'completed', 'progress' => 100,
            'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
            'notes' => 'Entrega final realizada.', 'next_steps' => '',
        ])->assertSessionHasNoErrors();
        $this->actingAs($client)->get('/proyectos/'.$projectId)->assertInertia(fn (Assert $page) => $page
            ->where('selected.status', 'completed')->where('selected.progress', 100)->has('selected.updates', 2)
            ->where('selected.updates.1.notes', $update['notes']));
        $this->get('/profesional')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/proyectos/'.$projectId)->assertNotFound();
        $this->get('/presupuestos/'.$draft->id.'/pdf')->assertNotFound();
    }
}
