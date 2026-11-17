<?php

namespace Tests\Feature;

use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Pruebas de las rutas públicas y de los datos que Laravel entrega a las páginas de Inertia.
class HomeTest extends TestCase
{
    use RefreshDatabase;

    // El catálogo de prueba usa los mismos servicios iniciales sin cargar precios ni cuentas.
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SynauxCatalogSeeder::class);
    }

    // Comprueba que la presentación ofrece la entrada a la plataforma sin cargar el catálogo.
    public function test_landing_provides_the_separate_platform_destination(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('appName', 'Synaux')
                ->where('homeUrl', '/')
                ->where('platformUrl', '/plataforma')
                ->where('year', (int) date('Y'))
                ->missing('services')
            );
    }

    // Comprueba la pantalla del catálogo y las categorías del contenido inicial.
    public function test_platform_has_its_own_catalog_page_and_public_services(): void
    {
        $this->get('/plataforma')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog')
                ->where('homeUrl', '/')
                ->where('platformUrl', '/plataforma')
                ->has('services', 11)
                ->where('services.0.id', 'sitio-institucional')
                ->where('services.1.category', 'Diseño')
                ->where('services.2.category', 'Marketing')
                ->where('services.5.category', 'UX/UI')
                ->where('services.6.id', 'auditoria-usabilidad')
                ->where('services.7.id', 'prototipo-interactivo')
            );
    }

    // Volver a cargar el catálogo reubica UX/UI sin duplicar servicios ni pisar su descripción.
    public function test_catalog_reseed_updates_the_legacy_ux_category_without_duplicates(): void
    {
        $design = DB::table('service_categories')->where('slug', 'diseno')->value('id');
        $ux = DB::table('service_categories')->where('name', 'UX/UI')->value('id');
        DB::table('services')->where('slug', 'diseno-ux-ui')->update([
            'service_category_id' => $design,
            'description' => 'Descripción editorial de Synaux.',
        ]);
        $questionsCount = DB::table('estimator_questions')->count();

        $this->seed(SynauxCatalogSeeder::class);
        $this->seed(SynauxCatalogSeeder::class);

        $this->assertDatabaseHas('services', [
            'slug' => 'diseno-ux-ui',
            'service_category_id' => $ux,
            'description' => 'Descripción editorial de Synaux.',
        ]);
        $this->assertDatabaseCount('service_categories', 4);
        $this->assertDatabaseCount('services', 11);
        $this->assertDatabaseCount('estimator_questions', $questionsCount);
    }


    // El estimador recibe el servicio del detalle, incluso cuando no es el servicio predeterminado.
    public function test_estimator_uses_the_service_selected_from_the_catalog(): void
    {
        $slug = 'tienda-online';

        $this->get('/servicios/'.$slug)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workspace')
                ->where('screen', 'servicio')
                ->where('selected.id', $slug)
            );

        $this->get('/estimador?servicio='.$slug)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workspace')
                ->where('screen', 'publicar')
                ->where('selected.id', $slug)
                ->where('step', 1)
                ->has('questions', 1)
            );
    }


    // Simula una navegación de Inertia con la versión de recursos actual y verifica la respuesta JSON.
    public function test_inertia_can_navigate_from_the_landing_to_the_platform(): void
    {
        $initialPage = $this->get('/')->viewData('page');

        $this->get('/plataforma', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $initialPage['version'],
        ])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Catalog')
            ->assertJsonPath('props.homeUrl', '/')
            ->assertJsonPath('props.services.0.id', 'sitio-institucional');
    }
}
