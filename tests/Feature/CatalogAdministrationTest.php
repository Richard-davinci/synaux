<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Los recorridos del catálogo se verifican en SQLite en memoria sin modificar datos locales.
class CatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    // Las rutas nuevas y las escrituras exigen administración, incluso con una sesión de cliente.
    public function test_catalog_routes_require_an_administrator(): void
    {
        $client = User::factory()->create();
        foreach (['/admin/categorias', '/admin/servicios/nuevo', '/admin/servicios/1/editar'] as $url) {
            $this->get($url)->assertRedirect('/ingresar');
            $this->actingAs($client)->get($url)->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
        $this->actingAs($client)->post('/admin/guardar/categorias', [])->assertForbidden();
        $this->post('/admin/guardar/servicios', [])->assertForbidden();
        $this->post('/admin/guardar/servicios-destacados', ['service_ids' => []])->assertForbidden();
    }

    // Las páginas comparten los registros existentes y rechazan la edición de un identificador inexistente.
    public function test_admin_can_open_categories_and_service_wizard(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $service = DB::table('services')->value('id');
        foreach (['/admin/categorias', '/admin/servicios/nuevo', "/admin/servicios/$service/editar"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn(Assert $page) => $page
                ->component('EspacioTrabajo')->where('screen', 'admin')->has('administration.categories')->has('administration.services'));
        }
        $this->get('/admin/servicios/999999/editar')->assertNotFound();
    }

    // Administración decide el orden; la portada respeta el límite, los estados y la selección vacía.
    public function test_admin_can_choose_up_to_three_public_featured_services(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $services = DB::table('services')->pluck('id', 'slug');
        $selected = [$services['tienda-online'], $services['diseno-ux-ui'], $services['identidad-visual']];
        $url = '/admin/guardar/servicios-destacados';

        $this->actingAs($admin)->from('/admin/servicios')->post($url, ['service_ids' => $selected])
            ->assertRedirect('/admin/servicios')->assertSessionHasNoErrors();
        $this->get('/admin/servicios')->assertInertia(fn(Assert $page) => $page
            ->where('administration.featuredServiceIds', $selected)->where('administration.featuredServiceLimit', 3));
        $this->get('/inicio')->assertInertia(fn(Assert $page) => $page
            ->has('featuredServices', 3)->where('featuredServices.0.id', 'tienda-online')
            ->where('featuredServices.1.id', 'diseno-ux-ui')->where('featuredServices.2.id', 'identidad-visual'));

        $this->post($url, ['service_ids' => [...$selected, $services['sitio-institucional']]])->assertSessionHasErrors('service_ids');
        $this->post($url, ['service_ids' => [$selected[0], $selected[0]]])->assertSessionHasErrors('service_ids.0');
        $this->post($url, ['service_ids' => [999999]])->assertSessionHasErrors('service_ids');
        DB::table('services')->where('id', $selected[0])->update(['active' => 0]);
        $this->post($url, ['service_ids' => [$selected[0]]])->assertSessionHasErrors('service_ids');
        $category = DB::table('services')->where('id', $selected[1])->value('service_category_id');
        DB::table('service_categories')->where('id', $category)->update(['active' => 0]);
        $this->post($url, ['service_ids' => [$selected[1]]])->assertSessionHasErrors('service_ids');
        $this->get('/inicio')->assertInertia(fn(Assert $page) => $page
            ->has('featuredServices', 1)->where('featuredServices.0.id', 'identidad-visual'));

        $this->post($url, ['service_ids' => []])->assertSessionHasNoErrors();
        $this->get('/inicio')->assertInertia(fn(Assert $page) => $page->has('featuredServices', 0));
    }

    // Guardar información comercial no crea ni modifica versiones o precios del presupuestador.
    public function test_catalog_saves_categories_and_services_without_changing_prices(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $prices = [];
        foreach (['tariff_versions', 'service_prices', 'component_prices'] as $table) {
            $prices[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-24 15:00:00', 'UTC'));
        $this->actingAs($admin)->post('/admin/guardar/categorias', [
            'name' => 'Comunicación', 'slug' => 'comunicacion', 'description' => 'Servicios de comunicación.', 'active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $category = DB::table('service_categories')->where('slug', 'comunicacion')->value('id');
        $values = ['name' => 'Asesoría', 'slug' => 'asesoria', 'service_category_id' => $category,
            'summary' => 'Asesoría de comunicación.', 'description' => 'Definición de objetivos y alcance.',
            'deliverables' => "Diagnóstico\nPlan de trabajo", 'excluded_items' => "Publicidad paga\nProducción audiovisual",
            'conditions' => 'El cliente entrega sus materiales antes del inicio.', 'estimation_mode' => 'manual', 'active' => false];
        $this->post('/admin/guardar/servicios', $values)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['slug' => 'asesoria', 'active' => false, 'estimation_mode' => 'manual']);
        $service = DB::table('services')->where('slug', 'asesoria')->first();
        $this->assertSame(['Diagnóstico', 'Plan de trabajo'], json_decode($service->deliverables, true));
        $this->assertSame(['Publicidad paga', 'Producción audiovisual'], json_decode($service->excluded_items, true));
        $this->assertSame('El cliente entrega sus materiales antes del inicio.', $service->conditions);
        $this->assertSame('2026-11-24 15:00:00', $service->updated_at);
        $this->travel(1)->day();
        $this->post('/admin/guardar/servicios', [...$values, 'slug' => ''])->assertSessionHasErrors('slug');
        $this->post('/admin/guardar/servicios', [...$values, 'id' => $service->id, 'active' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'active' => true, 'updated_at' => '2026-11-25 15:00:00']);
        $this->assertDatabaseHas('services', ['slug' => 'tienda-online', 'updated_at' => null]);
        $this->get('/admin/servicios')->assertInertia(fn(Assert $page) => $page->where('administration.services', fn($services) => collect($services)->firstWhere('id', $service->id)['updated_at'] === '2026-11-25T15:00:00.000000Z'));
        $this->travelBack();
        foreach ($prices as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    // Los borrados conservan vínculos relacionales y referencias guardadas en JSON.
    public function test_catalog_deletion_requires_admin_and_no_linked_data(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $cliente = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $categoria = DB::table('service_categories')->insertGetId([
            'name' => 'Temporal', 'slug' => 'temporal', 'description' => 'Solo para la prueba.', 'active' => false,
        ]);
        $servicio = DB::table('services')->insertGetId([
            'name' => 'Temporal', 'slug' => 'temporal', 'summary' => 'Servicio de prueba.',
            'description' => 'Solo para la prueba.', 'service_category_id' => $categoria,
            'estimation_mode' => 'manual', 'active' => false,
        ]);
        $borrarCategoria = "/admin/catalogo/categorias/$categoria";
        $borrarServicio = "/admin/catalogo/servicios/$servicio";
        $this->delete($borrarServicio)->assertRedirect('/ingresar');
        $this->actingAs($cliente)->delete($borrarServicio)->assertForbidden();
        $this->delete($borrarCategoria)->assertForbidden();
        $this->actingAs($admin)->from('/admin/categorias')->delete($borrarCategoria)
            ->assertSessionHasErrors(['eliminar_catalogo' => 'El registro está vinculado a servicios.']);

        $solicitud = DB::table('project_requests')->insertGetId([
            'user_id' => $cliente->id, 'title' => 'Pedido con un adicional', 'description' => 'Solo para la prueba.',
            'requested_services' => json_encode([['service_id' => $servicio, 'name' => 'Temporal']]),
        ]);
        DB::table('site_home_settings')->updateOrInsert(['id' => 1], ['featured_service_ids' => json_encode([$servicio])]);
        $this->from('/admin/servicios')->delete($borrarServicio)->assertSessionHasErrors([
            'eliminar_catalogo' => 'El registro está vinculado a servicios destacados, solicitudes con este servicio adicional.',
        ]);
        $this->assertDatabaseHas('services', ['id' => $servicio]);

        DB::table('site_home_settings')->where('id', 1)->update(['featured_service_ids' => '[]']);
        DB::table('project_requests')->where('id', $solicitud)->update(['service_id' => $servicio, 'requested_services' => '[]']);
        $this->delete($borrarServicio)->assertSessionHasErrors([
            'eliminar_catalogo' => 'El registro está vinculado a solicitudes.',
        ]);

        DB::table('project_requests')->where('id', $solicitud)->update(['service_id' => null]);
        $this->delete($borrarServicio)->assertRedirect('/admin/servicios')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('services', ['id' => $servicio]);
        $this->from('/admin/categorias')->delete($borrarCategoria)->assertRedirect('/admin/categorias')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('service_categories', ['id' => $categoria]);
        $this->assertDatabaseHas('project_requests', ['id' => $solicitud]);
    }

}
