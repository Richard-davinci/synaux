<?php

namespace Tests\Feature;

use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    // Comprueba la pantalla del catálogo y las tres categorías del contenido inicial.
    public function test_platform_has_its_own_catalog_page_and_public_services(): void
    {
        $this->get('/plataforma')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog')
                ->where('homeUrl', '/')
                ->where('platformUrl', '/plataforma')
                ->has('services', 3)
                ->where('services.0.id', 'sitio-institucional')
                ->where('services.1.category', 'Diseño')
                ->where('services.2.category', 'Marketing')
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
