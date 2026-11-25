<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Comprueba recorridos completos y límites de acceso en una base SQLite en memoria, nunca sobre datos locales.
class SynauxFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Esta suite verifica también los recorridos futuros del marketplace.
        config(['synaux.stage_two_enabled' => true]);
        $this->seed(SynauxCatalogSeeder::class);
    }

    private function account(bool $admin = false): User
    {
        $u = User::factory()->create();
        if ($admin) {
            $u->is_admin = true;
            $u->save();
        }

        return $u;
    }

    private function projectRequest(User $owner, string $visibility = 'private'): int
    {
        return DB::table('project_requests')->insertGetId(['user_id' => $owner->id, 'service_id' => DB::table('services')->value('id'), 'title' => 'Proyecto de prueba', 'description' => 'Necesitamos un sitio para nuestra organización.', 'visibility' => $visibility, 'status' => 'open', 'published_at' => $visibility === 'public' ? now() : null]);
    }

    private function profile(User $owner): int
    {
        return DB::table('professional_profiles')->insertGetId(['user_id' => $owner->id, 'brand_name' => 'Estudio de prueba', 'headline' => 'Desarrollo web', 'bio' => 'Diseñamos experiencias digitales.', 'availability_status' => 'available', 'active' => 1]);
    }

    private function quoteData(int $id): array
    {
        return ['request_id' => $id, 'title' => 'Presupuesto de prueba', 'currency_code' => 'ARS', 'tax_rate' => 21, 'valid_until' => today()->addDays(14)->toDateString(), 'estimated_delivery_days' => 30, 'terms' => 'Dos revisiones y entrega acordada.', 'items' => [['description' => 'Diseño y desarrollo', 'quantity' => 2, 'unit_price' => 100000]], 'send' => true];
    }

    // Un registro público jamás puede concederse administración aunque manipule los campos enviados.
    // El dueño puede publicar su borrador y otra cuenta no puede modificarlo.
    public function test_only_the_owner_can_publish_a_saved_request_draft(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $data = [
            'service_id' => DB::table('services')->value('id'),
            'title' => 'Sitio para mi organización',
            'description' => 'Necesito presentar los servicios de mi organización.',
            'visibility' => 'public',
            'skills' => 'Vue, Laravel',
            'publish' => false,
        ];

        $this->actingAs($owner)->post('/acciones/solicitud', $data)->assertRedirect();
        $id = DB::table('project_requests')->where('user_id', $owner->id)->value('id');
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'status' => 'draft']);

        $publication = [...$data, 'id' => $id, 'publish' => true];
        $this->actingAs($other)->post('/acciones/solicitud', $publication)->assertForbidden();
        $this->actingAs($owner)->post('/acciones/solicitud', $publication)->assertRedirect('/solicitudes/' . $id);
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'status' => 'open']);
    }


    public function test_registration_cannot_grant_management_access(): void
    {
        $this->post('/crear-cuenta', ['name' => 'Cliente Prueba', 'email' => 'cliente@example.test', 'password' => 'ClaveLarga123!', 'password_confirmation' => 'ClaveLarga123!', 'is_admin' => true])->assertRedirect('/cliente');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'cliente@example.test', 'is_admin' => 0]);
        $this->get('/admin/servicios')->assertForbidden();
        $this->get('/gestion')->assertForbidden();
        $this->post('/admin/guardar/componentes', [])->assertForbidden();
    }

    public function test_private_records_and_drafts_are_not_visible_to_another_account(): void
    {
        $owner = $this->account();
        $stranger = $this->account();
        $id = $this->projectRequest($owner);
        $this->get('/solicitudes/' . $id)->assertRedirect('/ingresar');
        $this->actingAs($stranger)->get('/solicitudes/' . $id)->assertNotFound();
        $this->get('/oportunidades/' . $id)->assertNotFound();
        $this->post('/acciones/estado-solicitud', ['id' => $id, 'status' => 'closed'])->assertForbidden();
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'status' => 'open']);
        $this->actingAs($owner)->get('/solicitudes/' . $id)->assertOk();
    }

    // Sin tarifario completo no se inventa un importe cero; el resultado requiere revisión profesional.
    public function test_estimate_is_preserved_through_registration_and_saved_once(): void
    {
        $answers = DB::table('estimator_questions')->where('service_id', DB::table('services')->where('slug', 'sitio-institucional')->value('id'))->get()->mapWithKeys(fn($q) => [$q->id => (string)DB::table('estimator_options')->where('question_id', $q->id)->value('id')])->all();
        $this->post('/estimador/calcular', ['service' => 'sitio-institucional', 'answers' => $answers])->assertRedirect()->assertSessionHas('pending_estimate', fn($p) => $p['mode'] === 'manual' && array_key_exists('min', $p) && $p['min'] === null);
        $this->post('/crear-cuenta', ['name' => 'Nuevo Cliente', 'email' => 'nuevo@example.test', 'password' => 'ClaveLarga123!', 'password_confirmation' => 'ClaveLarga123!'])->assertRedirect('/estimador?paso=4');
        $response = $this->post('/acciones/guardar-estimacion', ['to_request' => true]);
        $id = DB::table('estimates')->value('id');
        $response->assertRedirect('/solicitudes/nueva?estimacion=' . $id);
        $this->post('/acciones/guardar-estimacion', ['to_request' => true])->assertRedirect('/solicitudes/nueva?estimacion=' . $id);
        $this->assertDatabaseCount('estimates', 1);
        $this->assertDatabaseHas('estimates', ['status' => 'manual', 'price_min' => null, 'price_max' => null]);
        $this->get('/solicitudes/nueva?estimacion=' . $id)->assertInertia(fn(Assert $p) => $p->component('EspacioTrabajo')->where('selectedEstimate.id', $id));
    }

    // Entrar en una cuenta existente recupera el resultado y las respuestas del servicio original.
    public function test_login_resumes_the_estimate_for_the_original_service(): void
    {
        $user = User::factory()->create();
        $service = DB::table('services')->where('slug', 'tienda-online')->value('id');
        $question = DB::table('estimator_questions')->where('service_id', $service)->value('id');
        $answer = 'Necesito vender un catálogo de productos.';
        $this->post('/estimador/calcular', ['service' => 'tienda-online', 'answers' => [$question => $answer]])
            ->assertRedirect('/estimador?paso=4&servicio=tienda-online');

        $this->post('/ingresar', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/estimador?paso=4');

        $this->get('/estimador?paso=4')->assertInertia(fn(Assert $page) => $page
            ->where('step', 4)
            ->where('selected.id', 'tienda-online')
            ->where('pending.answers.0.value', $answer)
        );

        $this->get('/estimador?paso=4&servicio=identidad-visual')->assertInertia(fn(Assert $page) => $page
            ->where('step', 1)
            ->where('selected.id', 'identidad-visual')
            ->where('pending', null)
        );
    }


    // Recorre el MVP privado con los formularios reales, desde el estimador hasta el proyecto.
    public function test_private_estimate_becomes_a_project_after_management_quotes_it(): void
    {
        $client = $this->account();
        $manager = $this->account(admin: true);
        $this->profile($manager);
        $service = DB::table('services')->where('slug', 'landing-page')->value('id');
        $question = DB::table('estimator_questions')->where('service_id', $service)->value('id');

        $this->actingAs($client)->post('/estimador/calcular', [
            'service' => 'landing-page',
            'answers' => [$question => 'Necesitamos presentar nuestro próximo lanzamiento.'],
        ])->assertRedirect('/estimador?paso=4&servicio=landing-page');

        $this->post('/acciones/guardar-estimacion')->assertRedirect();
        $estimate = DB::table('estimates')->where('user_id', $client->id)->value('id');
        $request = [
            'service_id' => $service,
            'estimate_id' => $estimate,
            'title' => 'Landing para el lanzamiento',
            'description' => 'Necesitamos presentar el producto con un formulario de contacto.',
            'visibility' => 'private',
            'publish' => false,
        ];
        $this->post('/acciones/solicitud', $request)->assertRedirect();
        $id = DB::table('project_requests')->where('estimate_id', $estimate)->value('id');
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'status' => 'draft']);
        $this->post('/acciones/solicitud', [...$request, 'id' => $id, 'publish' => true])
            ->assertRedirect('/solicitudes/' . $id);
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'visibility' => 'private', 'status' => 'open', 'published_at' => null]);

        $this->actingAs($this->account())->get('/solicitudes/' . $id)->assertNotFound();
        $this->actingAs($manager)->get('/solicitudes/' . $id)->assertOk();
        $this->post('/acciones/presupuesto', $this->quoteData($id))->assertSessionHasNoErrors()->assertRedirect();
        $version = DB::table('budget_versions')->value('id');

        $decision = ['id' => $version, 'decision' => 'accepted', 'confirmed' => true];
        $this->actingAs($client)->post('/acciones/decidir-presupuesto', $decision)->assertRedirect();
        $this->post('/acciones/decidir-presupuesto', $decision)->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('projects', 1);
    }


    // La ficha usa el paquete completo por segmento y conserva la versión anterior al volver a cargarla.
    public function test_institutional_reference_matches_all_three_customer_segments(): void
    {
        $this->seed(\Database\Seeders\SynauxInstitutionalTariffSeeder::class);
        $catalog = app(\App\Services\Catalog\ServiceCatalog::class);
        $service = $catalog->service('sitio-institucional');
        $this->assertContains('Hasta 7 secciones en WordPress', $service['includes']);
        $questions = $catalog->questions($service['database_id']);
        $this->assertCount(1, $questions);

        foreach ($questions[0]['options'] as $position => $option) {
            $result = $catalog->calculate('sitio-institucional', [$questions[0]['id'] => $option->id]);
            $expected = [676240, 1352480, 2028720][$position];
            $this->assertSame('automatic', $result['mode']);
            $this->assertSame('ARS', $result['currency']);
            $this->assertEquals($expected, $result['min']);
            $this->assertEquals($expected, $result['max']);
        }

        $this->seed(\Database\Seeders\SynauxInstitutionalTariffSeeder::class);
        $this->seed(SynauxCatalogSeeder::class);
        $this->assertDatabaseCount('tariff_versions', 1);
        $this->assertCount(1, $catalog->questions($service['database_id']));
    }

    // Omitir estimate_id no permite cambiar el servicio de una solicitud basada en una estimación.
    public function test_request_draft_keeps_its_estimate_service_when_the_id_is_omitted(): void
    {
        $owner = $this->account();
        $id = $this->projectRequest($owner);
        $service = DB::table('project_requests')->where('id', $id)->value('service_id');
        $estimate = DB::table('estimates')->insertGetId([
            'user_id' => $owner->id, 'service_id' => $service, 'summary' => 'Referencia original', 'answers_snapshot' => '[]', 'request_key' => (string)\Illuminate\Support\Str::uuid(),
            'status' => 'manual', 'currency_snapshot' => 'ARS',
        ]);
        DB::table('project_requests')->where('id', $id)->update(['status' => 'draft', 'estimate_id' => $estimate]);
        $otherService = DB::table('services')->where('id', '!=', $service)->value('id');
        $data = [
            'id' => $id, 'service_id' => $otherService, 'title' => 'Solicitud modificada',
            'description' => 'Un alcance con descripción suficiente.', 'visibility' => 'private', 'publish' => true,
        ];

        $this->actingAs($owner)->post('/acciones/solicitud', $data)->assertSessionHasErrors('service_id');
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'status' => 'draft', 'service_id' => $service, 'estimate_id' => $estimate]);
        $this->post('/acciones/solicitud', [...$data, 'service_id' => $service])->assertSessionHasNoErrors()->assertRedirect('/solicitudes/' . $id);
    }

    // El servidor usa la categoría del catálogo y rechaza servicios retirados para nuevos pedidos.
    public function test_new_request_uses_the_catalog_category_and_requires_an_active_service(): void
    {
        $owner = $this->account();
        $service = DB::table('services')->where('slug', 'auditoria-usabilidad')->value('id');
        $data = [
            'service_id' => $service, 'category' => 'Desarrollo web', 'title' => 'Auditoría de mi producto',
            'description' => 'Necesitamos revisar el proceso de registro.', 'visibility' => 'private', 'publish' => false,
        ];
        $this->actingAs($owner)->post('/acciones/solicitud', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('project_requests', ['user_id' => $owner->id, 'service_id' => $service, 'category' => 'UX/UI']);
        DB::table('services')->where('id', $service)->update(['active' => 0]);
        $this->post('/acciones/solicitud', $data)->assertSessionHasErrors('service_id');
        $this->assertDatabaseCount('project_requests', 1);
    }

    // Las áreas de Gestión y Administración aplican el mismo permiso en lectura y escritura.
    public function test_management_routes_require_the_persisted_management_permission(): void
    {
        $this->get('/gestion')->assertRedirect('/ingresar');
        $this->actingAs($this->account())->get('/gestion')->assertForbidden();
        $this->get('/admin/tarifario')->assertForbidden();
        $this->post('/admin/guardar/servicios', [])->assertForbidden();

        $this->actingAs($this->account(true))->get('/gestion')->assertOk();
        $this->get('/admin/tarifario')->assertOk();
    }

    // El pedido conserva contacto y adicionales privados; cada presupuesto mantiene la identidad de su versión.
    public function test_request_contact_and_additional_services_flow_into_a_private_budget_pdf(): void
    {
        $client = $this->account();
        $manager = $this->account(true);
        $this->profile($manager);
        $service = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $extra = DB::table('services')->where('slug', 'identidad-visual')->value('id');
        $contact = ['name' => 'Contacto del proyecto', 'email' => 'proyecto@example.test', 'company' => 'Estudio de prueba', 'phone' => '1122334455', 'address' => 'Dirección de prueba'];
        $this->actingAs($client)->post('/acciones/solicitud', [
            'service_id' => $service, 'title' => 'Sitio y marca para el estudio',
            'description' => 'Queremos presentar nuestros servicios con una identidad nueva.',
            'visibility' => 'private', 'publish' => true, 'contact' => $contact,
            'additional_services' => [['service_id' => $extra, 'quantity' => 1, 'notes' => 'Incluye guía de uso.']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $id = DB::table('project_requests')->where('user_id', $client->id)->value('id');
        $this->get('/solicitudes/' . $id)->assertInertia(fn(Assert $page) => $page
            ->where('selected.contact.email', $contact['email'])
            ->where('selected.additional_services.0.name', 'Identidad visual'));

        $this->actingAs($manager)->get('/editor/presupuesto?solicitud=' . $id)->assertInertia(fn(Assert $page) => $page
            ->where('budgetRequests.0.contact.email', $contact['email']));
        $this->post('/acciones/presupuesto', $this->quoteData($id))->assertSessionHasNoErrors();
        $version = DB::table('budget_versions')->first();
        $this->assertSame($contact['email'], json_decode($version->client_snapshot, true)['email']);
        DB::table('project_requests')->where('id', $id)->update(['contact_snapshot' => json_encode([...$contact, 'email' => 'otro@example.test'])]);
        $this->actingAs($client)->get('/presupuestos/' . $version->id)->assertInertia(fn(Assert $page) => $page
            ->where('selected.client.email', $contact['email']));

        $pdf = $this->get('/presupuestos/' . $version->id . '/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->actingAs($this->account())->get('/presupuestos/' . $version->id . '/pdf')->assertNotFound();
    }

    // Gestión puede emitir y versionar para un contacto externo sin crear una solicitud ni una cuenta.
    public function test_management_can_issue_and_download_a_direct_external_budget(): void
    {
        $manager = $this->account(true);
        $this->profile($manager);
        $client = $this->account();
        $payload = [...$this->quoteData(0), 'request_id' => null, 'direct' => true,
            'client' => ['name' => 'Contacto externo', 'email' => $client->email], 'send' => false];

        $this->actingAs($client)->get('/editor/presupuesto?directo=1')->assertForbidden();
        $this->post('/acciones/presupuesto', $payload)->assertForbidden();
        $this->actingAs($manager)->get('/editor/presupuesto?directo=1')
            ->assertInertia(fn(Assert $page) => $page->where('directBudget', true));
        $this->post('/acciones/presupuesto', [...$payload, 'client' => ['email' => $client->email]])
            ->assertSessionHasErrors('client.name');
        $this->post('/acciones/presupuesto', $payload)->assertSessionHasNoErrors();
        $draft = DB::table('budget_versions')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertDatabaseHas('budgets', ['id' => $draft->budget_id, 'project_request_id' => null, 'client_user_id' => null, 'direct_service_id' => null]);
        $this->get('/editor/presupuesto/' . $draft->id)->assertInertia(fn(Assert $page) => $page
            ->where('directBudget', true)->where('selected.client.name', 'Contacto externo'));
        $this->post('/acciones/presupuesto', [...$payload, 'budget_id' => $draft->budget_id, 'send' => true])
            ->assertSessionHasNoErrors()->assertRedirect('/presupuestos/' . $draft->id);
        $this->get('/presupuestos/' . $draft->id)->assertInertia(fn(Assert $page) => $page
            ->where('selected.external', true)->where('selected.display_status', 'issued')->where('selected.version_number', 1));
        $pdf = $this->get('/presupuestos/' . $draft->id . '/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->post('/acciones/presupuesto', [...$payload, 'budget_id' => $draft->budget_id, 'send' => true,
            'client' => ['name' => 'Contacto actualizado']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('budget_versions', ['budget_id' => $draft->budget_id, 'version_number' => 2, 'status' => 'sent']);
        $this->get('/presupuestos/' . $draft->id)->assertInertia(fn(Assert $page) => $page
            ->where('selected.client.name', 'Contacto externo')->where('selected.current', false));

        $this->actingAs($client)->get('/presupuestos/' . $draft->id . '/pdf')->assertNotFound();
        $this->post('/acciones/decidir-presupuesto', ['id' => $draft->id, 'decision' => 'accepted', 'confirmed' => true])->assertForbidden();
        $this->assertDatabaseCount('project_requests', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('synaux_notifications', 0);
    }

    // Gestión recibe el pedido al enviarlo; el borrador continúa reservado a su dueño.
    public function test_management_cannot_read_a_client_request_draft_before_submission(): void
    {
        $client = $this->account();
        $id = $this->projectRequest($client);
        DB::table('project_requests')->where('id', $id)->update(['status' => 'draft']);
        $this->actingAs($this->account(true))->get('/solicitudes/' . $id)->assertNotFound();
        DB::table('project_requests')->where('id', $id)->update(['status' => 'open']);
        $this->get('/solicitudes/' . $id)->assertOk();
    }

    public function test_estimator_rejects_forged_options(): void
    {
        $qid = DB::table('estimator_questions')->value('id');
        $this->from('/estimador?paso=2')->post('/estimador/calcular', ['service' => 'sitio-institucional', 'answers' => [$qid => 999999]])->assertSessionHasErrors('answers.' . $qid)->assertSessionMissing('pending_estimate');
    }

    // Si el pedido se cierra mientras se edita, el borrador se conserva y el aviso vuelve al formulario.
    public function test_closed_request_returns_a_field_error_without_overwriting_the_budget_draft(): void
    {
        $client = $this->account();
        $manager = $this->account(true);
        $this->profile($manager);
        $requestId = $this->projectRequest($client);
        $this->actingAs($manager)->post('/acciones/presupuesto', [...$this->quoteData($requestId), 'send' => false])
            ->assertSessionHasNoErrors();
        $draft = DB::table('budget_versions')->first();
        DB::table('project_requests')->where('id', $requestId)->update(['status' => 'closed']);
        $this->from('/editor/presupuesto/' . $draft->id)->post('/acciones/presupuesto', [
            ...$this->quoteData($requestId), 'budget_id' => $draft->budget_id,
            'title' => 'Cambios que todavía estoy editando', 'send' => true,
        ])->assertRedirect('/editor/presupuesto/' . $draft->id)->assertSessionHasErrors('request_id')
            ->assertSessionHasInput('title', 'Cambios que todavía estoy editando');
        $this->assertDatabaseHas('budget_versions', ['id' => $draft->id, 'title' => $draft->title, 'status' => 'draft']);
        $this->assertDatabaseCount('budget_versions', 1);
        $this->assertDatabaseCount('projects', 0);
    }

    // El presupuesto enviado se conserva al emitir otra versión, y solo la vigente puede aceptarse.
    public function test_budget_versions_and_acceptance_create_exactly_one_project(): void
    {
        $client = $this->account();
        $issuer = $this->account(true);
        $this->profile($issuer);
        $id = $this->projectRequest($client);
        $this->actingAs($issuer)->post('/acciones/presupuesto', $this->quoteData($id))->assertSessionHasNoErrors()->assertRedirect();
        $v1 = DB::table('budget_versions')->first();
        $this->post('/acciones/presupuesto', [...$this->quoteData($id), 'budget_id' => $v1->budget_id, 'title' => 'Segunda versión'])->assertSessionHasNoErrors()->assertRedirect();
        $v2 = DB::table('budget_versions')->orderByDesc('id')->first();
        $this->assertDatabaseCount('budget_versions', 2);
        $this->assertDatabaseHas('budget_versions', ['id' => $v1->id, 'title' => 'Presupuesto de prueba', 'status' => 'sent']);
        $this->actingAs($client)->post('/acciones/decidir-presupuesto', ['id' => $v1->id, 'decision' => 'accepted', 'confirmed' => true])->assertSessionHasErrors('decision');
        $this->post('/acciones/decidir-presupuesto', ['id' => $v2->id, 'decision' => 'accepted', 'confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->post('/acciones/decidir-presupuesto', ['id' => $v2->id, 'decision' => 'accepted', 'confirmed' => true])->assertRedirect();
        $this->assertDatabaseHas('projects', ['accepted_budget_version_id' => $v2->id]);
        $this->assertDatabaseHas('project_requests', ['id' => $id, 'status' => 'closed']);
        $proyecto = DB::table('projects')->where('accepted_budget_version_id', $v2->id)->value('id');
        $this->get('/proyectos/' . $proyecto)->assertInertia(fn(Assert $page) => $page
            ->where('quotes.0.id', $v2->id)
            ->has('historialPresupuestos', 1)->where('historialPresupuestos.0.id', $v1->id)
            ->missing('historialPresupuestos.0.issuer_snapshot'));
        $this->post('/acciones/decidir-presupuesto', ['id' => $v2->id, 'decision' => 'accepted', 'confirmed' => true])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('projects', 1);
        $this->actingAs($issuer)->get('/proyectos/' . $proyecto)->assertInertia(fn(Assert $page) => $page
            ->where('area', 'gestion')->where('quotes.0.id', $v2->id)
            ->has('historialPresupuestos', 1)->where('historialPresupuestos.0.id', $v1->id));
    }

    // Rechazar conserva el documento, no crea un proyecto y bloquea decisiones posteriores sobre esa versión.
    public function test_budget_rejection_preserves_the_document_without_creating_a_project(): void
    {
        $client = $this->account();
        $issuer = $this->account(true);
        $this->profile($issuer);
        $requestId = $this->projectRequest($client);
        $this->actingAs($issuer)->post('/acciones/presupuesto', $this->quoteData($requestId))->assertSessionHasNoErrors();
        $version = DB::table('budget_versions')->first();
        $this->actingAs($client)->post('/acciones/decidir-presupuesto', [
            'id' => $version->id, 'decision' => 'rejected', 'confirmed' => false,
        ])->assertSessionHasErrors('confirmed');
        $rechazo = ['id' => $version->id, 'decision' => 'rejected', 'confirmed' => true];
        $this->post('/acciones/decidir-presupuesto', $rechazo)->assertSessionHasErrors('motivo_rechazo');
        $this->post('/acciones/decidir-presupuesto', [...$rechazo, 'motivo_rechazo' => '   '])->assertSessionHasErrors('motivo_rechazo');
        $this->post('/acciones/decidir-presupuesto', [...$rechazo, 'motivo_rechazo' => str_repeat('a', 1001)])->assertSessionHasErrors('motivo_rechazo');
        $this->assertDatabaseHas('budget_versions', ['id' => $version->id, 'status' => 'sent', 'motivo_rechazo' => null]);

        $motivo = 'Necesitamos ajustar el alcance y el plazo.';
        $this->post('/acciones/decidir-presupuesto', [...$rechazo, 'motivo_rechazo' => $motivo])->assertRedirect();
        $this->assertDatabaseHas('budget_versions', ['id' => $version->id, 'status' => 'rejected', 'motivo_rechazo' => $motivo]);
        $this->assertDatabaseHas('project_requests', ['id' => $requestId, 'status' => 'open']);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseHas('synaux_notifications', ['user_id' => $issuer->id, 'event_key' => 'presupuesto-rechazado:' . $version->id]);
        $this->get('/presupuestos/' . $version->id)->assertInertia(fn(Assert $page) => $page
            ->where('selected.status', 'rejected')->where('selected.motivo_rechazo', $motivo));
        $this->actingAs($issuer)->get('/presupuestos/' . $version->id)->assertInertia(fn(Assert $page) => $page
            ->where('area', 'gestion')->where('selected.motivo_rechazo', $motivo));
        $this->actingAs($client);
        $this->get('/presupuestos/' . $version->id . '/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post('/acciones/decidir-presupuesto', [
            'id' => $version->id, 'decision' => 'accepted', 'confirmed' => true,
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_budget_drafts_and_expired_versions_cannot_be_accepted(): void
    {
        $client = $this->account();
        $issuer = $this->account(true);
        $this->profile($issuer);
        $id = $this->projectRequest($client);
        $this->actingAs($issuer)->post('/acciones/presupuesto', [...$this->quoteData($id), 'send' => false])->assertSessionHasNoErrors();
        $v = DB::table('budget_versions')->first();
        $this->actingAs($client)->get('/presupuestos/' . $v->id)->assertNotFound();
        DB::table('budget_versions')->where('id', $v->id)->update(['status' => 'sent', 'sent_at' => now(), 'valid_until' => today()->subDay()]);
        $this->post('/acciones/decidir-presupuesto', ['id' => $v->id, 'decision' => 'accepted', 'confirmed' => true])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('projects', 0);
    }

    // La invitación privada se vuelve legible solo para su destinatario después de aceptarla.
    public function test_private_invitation_and_professional_selection_flow(): void
    {
        $client = $this->account();
        $pro = $this->account();
        $profile = $this->profile($pro);
        $other = $this->account();
        $id = $this->projectRequest($client);
        $this->actingAs($client)->post('/acciones/invitacion', ['request_id' => $id, 'professional_id' => $profile])->assertRedirect();
        $invite = DB::table('project_request_invitations')->value('id');
        $this->actingAs($other)->post('/acciones/responder-invitacion', ['id' => $invite, 'status' => 'accepted'])->assertForbidden();
        $this->actingAs($pro)->get('/oportunidades/' . $id)->assertNotFound();
        $this->post('/acciones/responder-invitacion', ['id' => $invite, 'status' => 'accepted'])->assertRedirect();
        $this->get('/oportunidades/' . $id)->assertInertia(fn(Assert $p) => $p->missing('selected.answers')->missing('selected.user_id'));
        $this->post('/acciones/propuesta', ['request_id' => $id, 'message' => 'Puedo desarrollar la solución con una revisión inicial.', 'estimated_delivery_days' => 30, 'currency_code' => 'ARS'])->assertRedirect('/profesional/propuestas');
        $proposal = DB::table('proposals')->value('id');
        $this->post('/acciones/presupuesto', $this->quoteData($id))->assertForbidden();
        $this->actingAs($client)->post('/acciones/seleccionar-propuesta', ['id' => $proposal])->assertRedirect();
        $this->actingAs($pro)->get('/editor/presupuesto?solicitud=' . $id)->assertInertia(fn(Assert $p) => $p->has('budgetRequests', 1)->where('budgetRequests.0.id', $id));
        $this->post('/acciones/presupuesto', $this->quoteData($id))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('budget_versions', 1);
    }

    // El contacto heredado solo se entrega al dueño o Gestión; nunca se incorpora al muro público.
    public function test_legacy_request_contact_is_available_to_management_and_stays_private(): void
    {
        $client = $this->account();
        $manager = $this->account(true);
        $id = $this->projectRequest($client, 'public');
        DB::table('project_requests')->where('id', $id)->update(['contact_snapshot' => null]);
        $this->actingAs($manager)->get('/editor/presupuesto?solicitud=' . $id)->assertInertia(fn(Assert $page) => $page
            ->where('budgetRequests.0.contact.name', $client->name)
            ->where('budgetRequests.0.contact.email', $client->email));
        $this->get('/oportunidades')->assertInertia(fn(Assert $page) => $page
            ->missing('opportunities.0.contact')->missing('opportunities.0.contact_email'));
        $this->actingAs($this->account())->get('/solicitudes/' . $id)->assertNotFound();
    }

    public function test_public_wall_does_not_include_private_answers_or_owner_contacts(): void
    {
        $owner = $this->account();
        $this->projectRequest($owner);
        $id = $this->projectRequest($owner, 'public');
        $this->get('/oportunidades')->assertInertia(fn(Assert $p) => $p->has('opportunities', 1)->where('opportunities.0.id', $id)->missing('opportunities.0.answers')->missing('opportunities.0.user_id')->missing('opportunities.0.email'));
    }

    public function test_contact_is_persisted_and_visible_only_to_management(): void
    {
        $this->post('/contacto', ['name' => 'Persona de prueba', 'email' => 'contacto@example.test', 'topic' => 'Consulta general', 'message' => 'Quisiera conversar sobre un proyecto digital.'])->assertRedirect('/contacto?enviado=1');
        $this->assertDatabaseCount('contact_inquiries', 1);
        $this->actingAs($this->account())->get('/gestion/contactos')->assertForbidden();
        $this->actingAs($this->account(true))->get('/gestion/contactos')->assertInertia(fn(Assert $p) => $p->has('inquiries', 1));
    }

    // Recorre las referencias de diseño y los estados funcionales y prueba que sus datos no crean cuentas ni permisos de sesión.
    public function test_every_figma_reference_state_renders_without_authenticating_a_user(): void
    {
        $screens = json_decode(file_get_contents(resource_path('reference/screens.json')), true);
        $this->assertNotEmpty($screens);
        $this->assertCount(count($screens), array_unique(array_column($screens, 'key')));
        foreach ($screens as $screen) {
            $this->get('/prototipo/' . $screen['key'])->assertOk()->assertInertia(fn(Assert $p) => $p->where('preview', true)->where('referenceKey', $screen['key'])->where('auth', null));
        }
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('budget_versions', 0);
        $this->assertDatabaseCount('tariff_versions', 0);
    }

    // La edición del tarifario conserva el cálculo anterior y un tarifario incompleto no puede activarse.
    public function test_automatic_prices_use_versioned_tariffs_and_snapshot_currency(): void
    {
        $admin = $this->account(true);
        $this->actingAs($admin);
        $service = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $components = DB::table('service_components')->where('service_id', $service)->get();
        $prices = $components->map(fn($c) => ['id' => $c->id, 'min' => 10, 'max' => 20])->all();
        $tariff = ['name' => 'Tarifa de prueba', 'currency_code' => 'USD', 'active' => true, 'services' => [['id' => $service, 'min' => 100, 'max' => 200]], 'components' => $prices];
        $this->post('/admin/guardar/tarifario', [...$tariff, 'components' => []])->assertSessionHasErrors('active');
        $this->assertDatabaseCount('tariff_versions', 0);
        $this->post('/admin/guardar/tarifario', $tariff)->assertSessionHasNoErrors();
        $answers = DB::table('estimator_questions')->where('service_id', $service)->get()->mapWithKeys(fn($q) => [$q->id => (string)DB::table('estimator_options')->where('question_id', $q->id)->value('id')])->all();
        $this->actingAs($this->account());
        $this->post('/estimador/calcular', ['service' => 'sitio-institucional', 'answers' => $answers])->assertSessionHas('pending_estimate', fn($p) => $p['mode'] === 'automatic' && $p['min'] === 140 && $p['max'] === 280 && $p['currency'] === 'USD');
        $this->post('/acciones/guardar-estimacion', [])->assertRedirect();
        $this->actingAs($admin)->post('/admin/guardar/tarifario', [...$tariff, 'name' => 'Versión siguiente', 'currency_code' => 'ARS'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tariff_versions', 2);
        $this->assertDatabaseHas('estimates', ['currency_snapshot' => 'USD', 'price_min' => 140, 'price_max' => 280]);
    }

    public function test_question_options_are_deactivated_without_destroying_history(): void
    {
        $this->actingAs($this->account(true));
        $q = DB::table('estimator_questions')->first();
        $option = DB::table('estimator_options')->where('question_id', $q->id)->first();
        $this->post('/admin/guardar/preguntas', ['id' => $q->id, 'service_id' => $q->service_id, 'question' => $q->question, 'input_type' => 'single_choice', 'sort_order' => 0, 'active' => true, 'options_text' => "Opción nueva\nOtra opción", 'quantity_component_id' => null])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('estimator_options', ['id' => $option->id, 'active' => 0]);
        $this->assertDatabaseHas('estimator_options', ['question_id' => $q->id, 'label' => 'Opción nueva', 'active' => 1]);
        $this->assertDatabaseHas('estimation_rules', ['estimator_option_id' => $option->id]);
    }

    // La portada se sirve desde Laravel con permisos, sin depender de enlaces simbólicos de Windows/Drive.
    public function test_portfolio_upload_and_private_image_permissions(): void
    {
        Storage::fake('public');
        $owner = $this->account();
        $this->profile($owner);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=');
        $data = ['service_id' => DB::table('services')->where('slug', 'sitio-institucional')->value('id'), 'title' => 'Trabajo publicado', 'description' => 'Descripción del proyecto de prueba', 'category' => 'Categoría ajena al catálogo', 'is_public' => true];
        $this->actingAs($owner)->post('/acciones/trabajo', [...$data, 'cover' => UploadedFile::fake()->createWithContent('portada.png', $png)])->assertSessionHasNoErrors()->assertRedirect();
        $work = DB::table('portfolio_projects')->first();
        $this->assertSame('Desarrollo web', $work->category);
        DB::table('portfolio_projects')->where('id', $work->id)->update(['category' => 'Rótulo anterior']);
        $this->get('/trabajos/' . $work->id)->assertInertia(fn(Assert $page) => $page
            ->where('selected.category', 'Desarrollo web'));
        $this->get($work->cover_path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $visitor = $this->account();
        $this->actingAs($visitor)->get($work->cover_path)->assertOk();

        // Ocultar un perfil profesional oculta su trabajo; Gestión puede publicar con su perfil interno.
        DB::table('professional_profiles')->where('user_id', $owner->id)->update(['active' => false]);
        $this->get('/trabajos/' . $work->id)->assertNotFound();
        $this->get($work->cover_path)->assertNotFound();
        $owner->forceFill(['is_admin' => true])->save();
        $this->get('/trabajos/' . $work->id)->assertOk();
        $this->get($work->cover_path)->assertOk();

        $this->actingAs($owner)->post('/acciones/trabajo', [...$data, 'id' => $work->id, 'is_public' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->account())->get($work->cover_path)->assertNotFound();
        $this->actingAs($owner)->get($work->cover_path)->assertOk();
    }

    // La galería acepta reordenar, reemplazar y quitar archivos del trabajo propio en un solo envío.
    public function test_portfolio_gallery_can_be_edited_without_overwriting_a_stale_selection(): void
    {
        Storage::fake('public');
        $owner = $this->account(true);
        $this->profile($owner);
        $data = [
            'service_id' => DB::table('services')->value('id'),
            'title' => 'Galería editable', 'description' => 'Trabajo de prueba', 'category' => 'Desarrollo web',
            'is_public' => true,
        ];
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=');
        $image = fn(string $name) => UploadedFile::fake()->createWithContent($name . '.png', $png);
        $this->actingAs($owner)->post('/acciones/trabajo', [...$data, 'cover_choice' => 'new:1', 'gallery' => [
            $image('primera'), $image('segunda'), $image('tercera'),
        ]])->assertSessionHasNoErrors();
        $work = DB::table('portfolio_projects')->first();
        $images = DB::table('portfolio_project_images')->orderBy('sort_order')->get();
        $this->assertSame($images[1]->image_path, $work->cover_path);
        $revision = app(\App\Services\Portfolio\GaleriaTrabajos::class)->revision($images);
        $this->get('/editor/trabajo/' . $work->id)->assertInertia(fn(Assert $page) => $page
            ->where('galleryRevision', $revision)->has('selected.images', 3));

        $selection = [
            'id' => $work->id, 'gallery_revision' => $revision,
            'gallery_items' => json_encode([
                ['id' => $images[2]->id, 'alt_text' => 'Página de inicio'],
                ['id' => $images[0]->id, 'alt_text' => 'Catálogo'],
            ]),
        ];
        $this->post('/acciones/trabajo', [...$data, ...$selection,
            'cover_choice' => 'saved:' . $images[0]->id,
            'gallery_replacements' => [$images[0]->id => $image('nuevo-catalogo')],
            'gallery' => [$image('cuarta')],
        ])->assertSessionHasNoErrors();
        $updated = DB::table('portfolio_project_images')->orderBy('sort_order')->get();
        $this->assertSame([$images[2]->id, $images[0]->id], $updated->take(2)->pluck('id')->all());
        $this->assertSame('Página de inicio', $updated[0]->alt_text);
        $this->assertSame('Catálogo', $updated[1]->alt_text);
        $this->assertNotSame($images[0]->image_path, $updated[1]->image_path);
        $this->assertDatabaseHas('portfolio_projects', ['id' => $work->id, 'cover_path' => $updated[1]->image_path]);
        $this->post('/acciones/trabajo', [...$data, 'id' => $work->id, 'cover_choice' => 'saved:999999'])
            ->assertSessionHasErrors('cover_choice');
        $this->assertCount(3, $updated);
        $this->get($images[1]->image_path)->assertNotFound();

        $this->post('/acciones/trabajo', [...$data, ...$selection])->assertSessionHasErrors('gallery_items');
        $this->assertDatabaseCount('portfolio_project_images', 3);
        $this->post('/acciones/trabajo', [...$data, ...$selection, 'gallery_items' => '[]',
            'gallery_revision' => app(\App\Services\Portfolio\GaleriaTrabajos::class)->revision($updated),
            'cover_choice' => 'none',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('portfolio_project_images', 0);
        $this->assertDatabaseHas('portfolio_projects', ['id' => $work->id, 'cover_path' => null]);
    }

    public function test_project_progress_can_be_updated_only_by_issuer_or_management(): void
    {
        $client = $this->account();
        $issuer = $this->account(true);
        $this->profile($issuer);
        $id = $this->projectRequest($client);
        $this->actingAs($issuer)->post('/acciones/presupuesto', $this->quoteData($id))->assertSessionHasNoErrors();
        $version = DB::table('budget_versions')->value('id');
        $this->actingAs($client)->post('/acciones/decidir-presupuesto', ['id' => $version, 'decision' => 'accepted', 'confirmed' => true])->assertRedirect();
        $project = DB::table('projects')->value('id');
        $data = ['id' => $project, 'status' => 'in_progress', 'progress' => 60, 'start_date' => null, 'end_date' => null, 'notes' => 'Diseño aprobado, avanzamos en desarrollo.'];
        $this->post('/acciones/proyecto', $data)->assertForbidden();
        $this->actingAs($issuer)->post('/acciones/proyecto', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project, 'progress' => 60, 'status' => 'in_progress']);
        $this->assertDatabaseCount('project_updates', 1);
        $this->post('/acciones/proyecto', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('project_updates', 1);
        $this->post('/acciones/proyecto', [...$data, 'status' => 'completed'])->assertSessionHasErrors('progress');
        $this->assertDatabaseCount('project_updates', 1);
        // La ficha de Gestión enlaza exclusivamente proyectos del cliente y sus documentos.
        $this->get('/gestion/clientes/' . $client->id . '?buscar=estudio&pagina_usuarios=2')
            ->assertInertia(fn(Assert $page) => $page->where('section', 'clientes')
                ->where('selectedUser.id', $client->id)->missing('selectedUser.password')->missing('selectedUser.remember_token')
                ->where('userDirectoryUrl', '/gestion/clientes?buscar=estudio&page=2')
                ->has('clientProjects.data', 1)->has('clientProjects.data.0', fn(Assert $card) => $card
                    ->where('id', $project)->has('title')->where('status', 'in_progress')->where('progress', 60)));
        $this->get('/gestion/clientes/' . $issuer->id)->assertNotFound();
        $this->get('/proyectos/' . $project)
            ->assertInertia(fn(Assert $page) => $page->where('area', 'gestion')->where('selected.id', $project));

        $next = [...$data, 'progress' => 80, 'notes' => 'Desarrollo terminado, falta revisión.', 'next_steps' => 'Revisar el sitio con el cliente.'];
        $this->post('/acciones/proyecto', $next)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('project_updates', 2);
        $this->actingAs($client)->get('/proyectos/' . $project)->assertInertia(fn(Assert $page) => $page
            ->where('selected.next_steps', $next['next_steps'])
            ->has('selected.updates', 2)->where('selected.updates.0.progress', 80)
            ->where('selected.updates.1.notes', $data['notes'])->missing('selected.updates.0.user_id'));
        $this->actingAs($this->account())->get('/proyectos/' . $project)->assertNotFound();
        $this->post('/acciones/proyecto', $next)->assertForbidden();
        $this->assertDatabaseCount('project_updates', 2);
        $this->actingAs($issuer)->post('/acciones/proyecto', [...$next, 'start_date' => null, 'end_date' => today()->toDateString()])
            ->assertSessionHasErrors('start_date');
        $this->post('/acciones/proyecto', [...$next, 'status' => 'completed', 'progress' => 100,
            'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
            'notes' => 'Entrega final realizada.', 'next_steps' => '',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['id' => $project, 'status' => 'completed', 'progress' => 100, 'next_steps' => null]);
        $this->assertDatabaseCount('project_updates', 3);
        $this->assertDatabaseHas('project_updates', ['project_id' => $project, 'progress' => 80, 'next_steps' => $next['next_steps']]);

    }
}
