<?php

namespace Tests\Feature;

use App\Services\Catalog\ServiceCatalog;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Database\Seeders\SynauxLandingTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingTariffTest extends TestCase
{
    use RefreshDatabase;

    // Comprueba los tres segmentos y que publicar la landing no modifique el paquete institucional anterior.
    public function test_landing_prices_are_consistent_and_the_publication_is_idempotent(): void
    {
        $this->seed([SynauxCatalogSeeder::class, SynauxInstitutionalTariffSeeder::class]);
        $catalog = app(ServiceCatalog::class);
        $this->assertArrayNotHasKey('sitio-institucional', $catalog->references());
        $this->assertDatabaseCount('tariff_versions', 0);
        $this->seed(SynauxLandingTariffSeeder::class);
        $this->assertArrayNotHasKey('sitio-institucional', $catalog->references());
        $service = $catalog->service('landing-page');
        $this->assertSame('automatic', $service['estimation_mode']);
        $questions = $catalog->questions($service['database_id'], specificOnly: true);
        $this->assertCount(1, $questions);
        foreach ($questions[0]['options'] as $position => $option) {
            $calculation = $catalog->calculate('landing-page', [$questions[0]['id'] => $option->id]);
            $amount = [254580, 509160, 763740][$position];
            $this->assertEquals($amount, $calculation['min']);
            $this->assertSame('automatic', $calculation['mode']);
            $this->assertEquals($amount, $catalog->references()['landing-page']['segments'][$position]['min']);
        }
        $this->seed(SynauxLandingTariffSeeder::class);
        $this->assertDatabaseCount('tariff_versions', 1);
        $this->assertCount(1, $catalog->questions($service['database_id'], specificOnly: true));
        $this->get('/plataforma')->assertInertia(fn(Assert $page) => $page->has('references.landing-page.segments', 3));
        $this->get('/estimador?servicio=landing-page&cliente=PyME')->assertInertia(fn(Assert $page) => $page
            ->where('requestServiceId', $service['database_id'])
            ->where('requestInitialAnswers.' . $questions[0]['id'], $questions[0]['options'][1]->id));

        // El recorrido público calcula el precio del segmento en el servidor antes de pedir el ingreso.
        $this->post('/solicitudes/preparar', [
            'service_id' => $service['database_id'], 'answers' => [$questions[0]['id'] => $questions[0]['options'][1]->id],
            'title' => 'Landing para una campaña', 'description' => 'Queremos captar consultas para nuestro nuevo servicio.',
            'additional_services' => [], 'destination' => 'review',
        ])->assertSessionHasNoErrors()->assertRedirect('/solicitudes/nueva?continuar=1')
            ->assertSessionHas('pending_request.calculation.min', 509160);
        $this->assertDatabaseCount('estimates', 0);
    }
}
