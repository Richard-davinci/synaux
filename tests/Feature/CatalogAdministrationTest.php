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
        $this->actingAs($admin)->post('/admin/guardar/categorias', [
            'name' => 'Comunicación', 'slug' => 'comunicacion', 'description' => 'Servicios de comunicación.', 'active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $category = DB::table('service_categories')->where('slug', 'comunicacion')->value('id');
        $values = ['name' => 'Asesoría', 'slug' => 'asesoria', 'service_category_id' => $category,
            'summary' => 'Asesoría de comunicación.', 'description' => 'Definición de objetivos y alcance.',
            'deliverables' => "Diagnóstico\nPlan de trabajo", 'estimation_mode' => 'manual', 'active' => false];
        $this->post('/admin/guardar/servicios', $values)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['slug' => 'asesoria', 'active' => false, 'estimation_mode' => 'manual']);
        $service = DB::table('services')->where('slug', 'asesoria')->first();
        $this->assertSame(['Diagnóstico', 'Plan de trabajo'], json_decode($service->deliverables, true));
        $this->post('/admin/guardar/servicios', [...$values, 'slug' => ''])->assertSessionHasErrors('slug');
        $this->post('/admin/guardar/servicios', [...$values, 'id' => $service->id, 'active' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'active' => true]);
        foreach ($prices as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        }
    }
}
