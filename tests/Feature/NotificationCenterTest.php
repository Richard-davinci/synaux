<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Notifications\NotificationCenter;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Prueba eventos reales y lecturas por cuenta usando exclusivamente SQLite en memoria.
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    // Los borradores no se anuncian; enviar el pedido genera un aviso para cada administrador ajeno.
    public function test_submitting_a_request_notifies_management_without_exposing_drafts(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $client = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $data = ['service_id' => DB::table('services')->value('id'), 'title' => 'Mi nuevo sitio',
            'description' => 'Necesitamos presentar nuestros servicios.', 'visibility' => 'private', 'publish' => false];
        $this->actingAs($client)->post('/acciones/solicitud', $data)->assertSessionHasNoErrors();
        $id = DB::table('project_requests')->value('id');
        $this->assertDatabaseCount('synaux_notifications', 0);
        $this->post('/acciones/solicitud', [...$data, 'id' => $id, 'publish' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('synaux_notifications', 2);
        $this->actingAs($admin)->get('/gestion/solicitudes')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1)->where('notifications.unread_entities.solicitud.0', $id));
        $this->get('/solicitudes/'.$id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));
        $this->assertDatabaseHas('synaux_notifications', ['user_id' => $otherAdmin->id, 'entity_id' => $id, 'read_at' => null]);
        $this->actingAs($other)->get('/cliente')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0)->has('notifications.items', 0));
        $this->get('/solicitudes/'.$id)->assertNotFound();
    }

    // Las rutas de lectura no aceptan avisos ajenos y un administrador revocado pierde sus avisos de gestión.
    public function test_notification_reads_are_private_and_management_access_is_rechecked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $center = app(NotificationCenter::class);
        $center->send('solicitud', 5, 'solicitud-enviada:5', 'Nueva solicitud', 'Pedido privado', management: true);
        $center->send('solicitud', 5, 'solicitud-enviada:5', 'Nueva solicitud', 'Pedido privado', management: true);
        $id = DB::table('synaux_notifications')->value('id');
        $this->assertDatabaseCount('synaux_notifications', 1);
        $this->patch('/notificaciones/'.$id.'/leer')->assertRedirect('/ingresar');
        $this->actingAs($other)->patch('/notificaciones/'.$id.'/leer')->assertNotFound();
        $this->patch('/notificaciones/leer-todas')->assertRedirect();
        $this->assertDatabaseHas('synaux_notifications', ['id' => $id, 'read_at' => null]);
        $admin->forceFill(['is_admin' => false])->save();
        $this->actingAs($admin)->get('/cliente')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0)->has('notifications.items', 0));
        $this->patch('/notificaciones/'.$id.'/leer')->assertNotFound();
    }

    // El ciclo completo distingue borrador, envío, creación y avance; las lecturas no se comparten entre cuentas.
    public function test_budget_and_project_events_generate_persistent_personal_notifications(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $client = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $watcher = User::factory()->create(['is_admin' => true]);
        $requestId = DB::table('project_requests')->insertGetId(['user_id' => $client->id,
            'service_id' => DB::table('services')->value('id'), 'title' => 'Proyecto notificado',
            'description' => 'Prueba de avisos del proyecto.', 'visibility' => 'private', 'status' => 'open']);
        $this->actingAs($admin)->patch('/mi-perfil/emision', ['name' => 'synaux', 'email' => 'equipo@example.test'])->assertSessionHasNoErrors();
        $quote = ['request_id' => $requestId, 'title' => 'Nuevo proyecto', 'currency_code' => 'ARS', 'tax_rate' => 0,
            'valid_until' => today()->addDays(14)->toDateString(), 'estimated_delivery_days' => 20, 'terms' => 'Entrega acordada.',
            'items' => [['description' => 'Servicio digital', 'quantity' => 1, 'unit_price' => 1000]], 'send' => false];
        $this->post('/acciones/presupuesto', $quote)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('synaux_notifications', 0);
        $version = DB::table('budget_versions')->first();
        $this->post('/acciones/presupuesto', [...$quote, 'budget_id' => $version->budget_id, 'send' => true])->assertSessionHasNoErrors();
        $this->actingAs($client)->get('/cliente/presupuestos')->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_entities.presupuesto.0', $version->id));
        $this->get('/presupuestos/'.$version->id)->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));
        $decision = ['id' => $version->id, 'decision' => 'accepted', 'confirmed' => true];
        $this->post('/acciones/decidir-presupuesto', $decision)->assertSessionHasNoErrors();
        $project = DB::table('projects')->first();
        $this->assertDatabaseHas('synaux_notifications', ['user_id' => $admin->id, 'entity_type' => 'proyecto', 'entity_id' => $project->id, 'read_at' => null]);
        $this->assertDatabaseHas('synaux_notifications', ['user_id' => $watcher->id, 'event_key' => 'proyecto-creado:'.$project->id]);
        $this->post('/acciones/decidir-presupuesto', $decision)->assertSessionHasErrors('decision');
        $this->assertSame(2, DB::table('synaux_notifications')->where('event_key', 'proyecto-creado:'.$project->id)->count());
        $this->actingAs($admin)->get('/gestion/proyectos')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_entities.proyecto.0', $project->id));
        $this->get('/proyectos/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));
        $update = ['id' => $project->id, 'status' => 'in_progress', 'progress' => 25, 'notes' => 'Diseño en curso.'];
        $this->post('/acciones/proyecto', $update)->assertSessionHasNoErrors();
        $this->post('/acciones/proyecto', $update)->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('synaux_notifications')->where('event_key', 'like', 'proyecto-actualizado:%')->count());
        $this->actingAs($client)->get('/cliente/proyectos')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_entities.proyecto.0', $project->id));
        $this->patch('/notificaciones/leer-todas')->assertRedirect();
        $this->get('/cliente')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0)->has('notifications.items', 2));
        $this->assertSame(2, app(NotificationCenter::class)->data($watcher)['unread_count']);
    }

    // Una consulta apunta a su tarjeta y no marca como leídas las otras consultas del mismo listado.
    public function test_contact_notifications_link_to_a_specific_inquiry(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (['Consulta de un sitio', 'Consulta de identidad'] as $topic) {
            $this->post('/contacto', ['name' => 'Cliente', 'email' => 'cliente@example.test', 'topic' => $topic, 'message' => 'Quiero conocer más sobre sus servicios.'])->assertSessionHasNoErrors();
        }
        $id = DB::table('contact_inquiries')->value('id');
        $this->actingAs($admin)->get('/gestion/contactos?consulta='.$id)->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1)->has('notifications.unread_entities.consulta', 1));
        $notification = DB::table('synaux_notifications')->whereNull('read_at')->first();
        $this->patch('/notificaciones/'.$notification->id.'/leer')->assertRedirect();
        $this->get('/gestion')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));
    }
}
