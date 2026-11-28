<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
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
            ->assertInertia(fn(Assert $page) => $page
                ->component('Home')
                ->where('appName', 'synaux')
                ->where('homeUrl', '/')
                ->where('platformUrl', '/plataforma')
                ->where('year', (int)date('Y'))
                ->missing('services')
            );
    }

    // Comprueba la pantalla del catálogo y las categorías del contenido inicial.
    public function test_platform_has_its_own_catalog_page_and_public_services(): void
    {
        $this->get('/plataforma')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Catalog')
                ->where('homeUrl', '/')
                ->where('platformUrl', '/plataforma')
                ->has('services', 11)
                ->where('services.0.id', 'sitio-institucional')
                ->where('services.1.category', 'Diseño gráfico e identidad visual')
                ->where('services.2.category', 'Marketing digital y contenidos')
                ->where('services.5.category', 'Diseño UX/UI y producto digital')
                ->where('services.6.id', 'auditoria-usabilidad')
                ->where('services.7.id', 'prototipo-interactivo')
            );
    }

    // Las fichas de Inicio reciben el mismo alcance y tarifario que las del catálogo completo.
    public function test_site_home_uses_the_same_service_content_without_inventing_an_unpublished_tariff(): void
    {
        $this->seed(SynauxInstitutionalTariffSeeder::class);
        $catalog = $this->get('/plataforma')->assertOk()->inertiaProps();
        $service = $catalog['services'][0];

        $this->get('/inicio')->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('InicioSitio')
            ->has('featuredServices', 3)
            ->where('featuredServices.0.id', $service['id'])
            ->where('featuredServices.0.includes', $service['includes'])
            ->missing('featuredReferences.' . $service['id'])
            ->missing('featuredReferences.identidad-visual')
        );
    }

    // La portada muestra los tres trabajos públicos más recientes de los perfiles activos.
    public function test_site_home_shows_three_recent_public_works_only(): void
    {
        $this->get('/inicio')->assertInertia(fn(Assert $page) => $page->component('InicioSitio')->has('featuredWorks', 0));

        $owner = User::factory()->create();
        $inactiveOwner = User::factory()->create();
        $profile = ['brand_name' => 'Estudio de prueba', 'headline' => 'Diseño', 'bio' => 'Perfil de prueba'];
        $active = DB::table('professional_profiles')->insertGetId([...$profile, 'user_id' => $owner->id, 'active' => 1]);
        $inactive = DB::table('professional_profiles')->insertGetId([...$profile, 'user_id' => $inactiveOwner->id, 'active' => 0]);
        $work = [
            'professional_profile_id' => $active,
            'service_id' => DB::table('services')->where('slug', 'tienda-online')->value('id'),
            'description' => 'Trabajo de prueba',
            'is_public' => 1,
        ];
        DB::table('portfolio_projects')->insert([...$work, 'title' => 'Proyecto anterior']);
        $third = DB::table('portfolio_projects')->insertGetId([...$work, 'title' => 'Tercer proyecto']);
        $second = DB::table('portfolio_projects')->insertGetId([...$work, 'title' => 'Segundo proyecto']);
        $first = DB::table('portfolio_projects')->insertGetId([...$work, 'title' => 'Proyecto más reciente']);
        DB::table('portfolio_projects')->insert([...$work, 'title' => 'Proyecto privado', 'is_public' => 0]);
        DB::table('portfolio_projects')->insert([...$work, 'title' => 'Proyecto inactivo', 'professional_profile_id' => $inactive]);

        $this->get('/inicio')->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('InicioSitio')
            ->has('featuredWorks', 3)
            ->where('featuredWorks.0.id', $first)
            ->where('featuredWorks.0.category', 'Desarrollo web y tiendas online')
            ->where('featuredWorks.1.id', $second)
            ->where('featuredWorks.2.id', $third)
            ->missing('featuredWorks.0.professional_profile_id')
        );
        $this->get('/trabajos/' . $first)->assertOk();
    }

    // Volver a cargar el catálogo conserva las categorías canónicas y no duplica ni pisa contenido editorial.
    public function test_catalog_reseed_is_idempotent_and_preserves_editorial_content(): void
    {
        $ux = DB::table('services')->where('slug', 'diseno-ux-ui')->value('service_category_id');
        DB::table('services')->where('slug', 'diseno-ux-ui')->update([
            'description' => 'Descripción editorial de Synaux.',
        ]);
        $categoryCount = DB::table('service_categories')->count();
        $questionsCount = DB::table('estimator_questions')->count();

        $this->seed(SynauxCatalogSeeder::class);
        $this->seed(SynauxCatalogSeeder::class);

        $this->assertDatabaseHas('services', ['slug' => 'diseno-ux-ui', 'service_category_id' => $ux,
            'description' => 'Descripción editorial de Synaux.']);
        $this->assertSame($categoryCount, DB::table('service_categories')->count());
        $this->assertDatabaseCount('services', 11);
        $this->assertDatabaseCount('estimator_questions', $questionsCount);
    }


    // El estimador recibe el servicio del detalle, incluso cuando no es el servicio predeterminado.
    public function test_estimator_uses_the_service_selected_from_the_catalog(): void
    {
        $slug = 'tienda-online';

        $this->get('/servicios/' . $slug)
            ->assertInertia(fn(Assert $page) => $page
                ->component('EspacioTrabajo')
                ->where('screen', 'servicio')
                ->where('selected.id', $slug)
            );

        $this->get('/estimador?servicio=' . $slug)
            ->assertInertia(fn(Assert $page) => $page
                ->component('EspacioTrabajo')
                ->where('screen', 'publicar')
                ->where('selected.id', $slug)
                ->where('step', 1)
                ->has('questions', 2)
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
