<?php

namespace Tests\Feature;

use App\Services\Catalog\ServiceCatalog;
use App\Services\Tariffs\TariffImport;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TariffImportTest extends TestCase
{
    use RefreshDatabase;

    // Las respuestas controladas reproducen el formato público sin depender de Internet durante las pruebas.
    private function remote(string $scope = 'Sitio (hasta 7 secciones). UI/UX. Form. Blog básico. Responsive. CMS (WP) básico. SEO on-page inicial. 2 rondas.', bool $validRate = true): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://tarifario.ar/' => Http::response('<script src="/_next/static/chunks/catalog.js"></script>'),
            'https://tarifario.ar/_next/static/chunks/catalog.js' => Http::response(
                '{id:"pd-3",category:"dev-web-app",title:"Institucional",description:'.json_encode($scope, JSON_UNESCAPED_UNICODE).',priceUSD:800,priceType:"minimum"}'
            ),
            TariffImport::RATE => Http::response(['casa' => 'contadoconliqui', 'venta' => $validRate ? 1690.9 : 0, 'fechaActualizacion' => now()->toIso8601String()]),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Esta prueba simula solo Tarifario y la cotización; no depende del servidor SSR local de Vite.
        config(['inertia.ssr.enabled' => false]);
        $this->seed(SynauxCatalogSeeder::class);
        $this->seed(SynauxInstitutionalTariffSeeder::class);
    }

    public function test_import_creates_a_version_with_provenance_and_is_idempotent(): void
    {
        $this->remote();
        $import = app(TariffImport::class);
        $result = $import->sync();
        $this->assertTrue($result['created']);
        $this->assertSame(1352720, $result['pyme']);
        $this->assertFalse($import->sync()['created']);
        $this->assertDatabaseCount('tariff_versions', 2);
        $this->assertDatabaseHas('tariff_versions', ['id' => $result['id'], 'source_usd' => 800, 'source_rate' => 1690.9]);

        $catalog = app(ServiceCatalog::class);
        $service = $catalog->service('sitio-institucional');
        // La referencia inicial sale de la versión importada y solo existe para servicios con tarifas completas.
        $reference = $catalog->references()['sitio-institucional'];
        $this->assertEquals(676360, $reference['min']);
        $this->assertSame('Particular', $reference['segment']);
        $this->assertSame(TariffImport::SOURCE, $reference['source']);
        $this->assertArrayNotHasKey('identidad-visual', $catalog->references());
        $this->get('/plataforma')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('references.sitio-institucional.segments', 3)
            ->where('references.sitio-institucional.segments.1.label', 'PyME'));
        $this->get('/estimador?servicio=sitio-institucional')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('references.sitio-institucional.currency', 'ARS')
            ->where('references.sitio-institucional.segment', 'Particular'));
        $question = $catalog->questions($service['database_id'])[0];
        foreach ($question['options'] as $position => $option) {
            $estimate = $catalog->calculate('sitio-institucional', [$question['id'] => $option->id]);
            $this->assertEquals([676360, 1352720, 2029080][$position], $estimate['min']);
            $this->assertEquals($estimate['min'], $reference['segments'][$position]['min']);
            $this->assertSame($result['id'], $estimate['tariff_id']);
        }
    }

    public function test_scope_change_or_network_failure_preserves_the_existing_version(): void
    {
        $this->remote('Paquete modificado de doce secciones.');
        try {
            app(TariffImport::class)->sync();
            $this->fail('Un alcance diferente debe detener la importación.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('alcance', $exception->getMessage());
        }
        $this->assertDatabaseCount('tariff_versions', 1);
    }

    // El paso de detalle a estimación conserva el segmento mediante una opción activa del servidor.
    public function test_detail_customer_type_preselects_only_valid_service_options(): void
    {
        $service = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $question = DB::table('estimator_questions')->where('service_id', $service)->where('active', 1)->value('id');
        foreach (['Particular', 'PyME', 'Empresa'] as $label) {
            $option = DB::table('estimator_options')->where('question_id', $question)->where('label', $label)->value('id');
            $this->get('/estimador?servicio=sitio-institucional&paso=2&cliente='.urlencode($label))
                ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                    ->where('step', 2)->where('selected.id', 'sitio-institucional')
                    ->where('initialAnswers.'.$question, $option));
        }
        $this->get('/estimador?servicio=sitio-institucional&cliente=Otro')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('initialAnswers', []));
        $this->assertDatabaseCount('estimates', 0);
    }

    public function test_network_failure_keeps_the_current_tariff(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://tarifario.ar/' => Http::response('', 503)]);
        $this->artisan('synaux:tarifario')->assertExitCode(1);
        $this->assertDatabaseCount('tariff_versions', 1);
    }

    public function test_only_admin_can_import_and_invalid_currency_conversion_is_rejected(): void
    {
        $this->actingAs(\App\Models\User::factory()->create())->post('/admin/tarifario/importar')->assertForbidden();
        $this->remote(validRate: false);

        $this->expectException(\RuntimeException::class);
        app(TariffImport::class)->sync();
    }
}
