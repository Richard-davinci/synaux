<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalog\ServiceCatalog;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Database\Seeders\SynauxLandingTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// La configuración usa SQLite en memoria y nunca altera preguntas o precios del entorno local.
class ServiceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SynauxCatalogSeeder::class, SynauxInstitutionalTariffSeeder::class, SynauxLandingTariffSeeder::class]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_old_sections_redirect_to_service_configuration(): void
    {
        $id = DB::table('services')->orderBy('id')->value('id');
        foreach (['preguntas' => 'preguntas', 'componentes' => 'estimacion', 'reglas' => 'estimacion'] as $old => $tab) {
            $this->get('/admin/' . $old)->assertRedirect("/admin/servicios/$id/editar?config=$tab");
        }
    }

    public function test_optional_questions_skip_empty_answers_but_validate_supplied_values(): void
    {
        $catalog = app(ServiceCatalog::class);
        $service = $catalog->service('landing-page');
        $question = $catalog->questions($service['database_id'])[0];
        $answers = [$question['id'] => $question['options'][0]->id];
        $before = $catalog->calculate('landing-page', $answers);
        $this->post('/admin/guardar/preguntas', ['catalog_service_id' => $service['database_id'], 'service_id' => $service['database_id'],
            'question' => 'Referencia opcional', 'input_type' => 'number', 'sort_order' => 5, 'active' => true, 'required' => false, 'quantity_component_id' => null])->assertSessionHasNoErrors();
        $id = DB::table('estimator_questions')->where('question', 'Referencia opcional')->value('id');
        $after = $catalog->calculate('landing-page', $answers);
        $this->assertSame($before['min'], $after['min']);
        $this->assertSame($before['answers'], $after['answers']);
        $this->assertDatabaseHas('estimator_questions', ['id' => $id, 'required' => false]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $catalog->calculate('landing-page', [...$answers, $id => -1]);
    }

    public function test_context_rejects_records_from_another_service(): void
    {
        $web = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $landing = DB::table('services')->where('slug', 'landing-page')->value('id');
        $question = DB::table('estimator_questions')->where('service_id', $landing)->where('active', 1)->first();
        $this->post('/admin/guardar/preguntas', ['catalog_service_id' => $web, 'id' => $question->id, 'service_id' => $web,
            'question' => 'No debe guardarse', 'input_type' => 'text', 'sort_order' => 0, 'active' => true])->assertUnprocessable();
        $this->assertDatabaseHas('estimator_questions', ['id' => $question->id, 'service_id' => $landing, 'question' => $question->question]);
    }

    // Una pregunta general se reutiliza conservando su identidad y las reglas existentes.
    public function test_service_editor_can_share_a_question_without_changing_its_options(): void
    {
        $landing = DB::table('services')->where('slug', 'landing-page')->value('id');
        $web = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $datos = [
            'catalog_service_id' => $landing, 'service_id' => $landing, 'question' => '¿Tenés material de referencia?',
            'input_type' => 'single_choice', 'options_text' => "Sí\nNo", 'sort_order' => 20, 'active' => true, 'required' => false,
        ];
        $this->post('/admin/guardar/preguntas', $datos)->assertSessionHasNoErrors();
        $id = DB::table('estimator_questions')->where('question', $datos['question'])->value('id');
        $opciones = DB::table('estimator_options')->where('question_id', $id)->orderBy('id')->pluck('id')->all();

        $this->post('/admin/guardar/preguntas', [...$datos, 'id' => $id, 'service_id' => null])->assertSessionHasNoErrors();
        $this->assertContains($id, array_column(app(ServiceCatalog::class)->questions($web), 'id'));
        $this->assertSame($opciones, DB::table('estimator_options')->where('question_id', $id)->orderBy('id')->pluck('id')->all());

        $this->post('/admin/guardar/preguntas', [...$datos, 'id' => $id])->assertSessionHasErrors('service_id');
        $this->assertDatabaseHas('estimator_questions', ['id' => $id, 'service_id' => null]);
    }

    // Desvincular una cantidad requiere un cambio explícito antes de compartir la pregunta.
    public function test_linked_quantity_question_cannot_be_shared_directly(): void
    {
        $concepto = DB::table('service_components')->where('active', 1)->first();
        $datos = [
            'catalog_service_id' => $concepto->service_id, 'service_id' => $concepto->service_id,
            'quantity_component_id' => $concepto->id, 'question' => '¿Cuántas unidades necesitás?',
            'input_type' => 'number', 'sort_order' => 20, 'active' => true,
        ];
        $this->post('/admin/guardar/preguntas', $datos)->assertSessionHasNoErrors();
        $id = DB::table('estimator_questions')->where('question', $datos['question'])->value('id');

        $this->post('/admin/guardar/preguntas', [...$datos, 'id' => $id, 'service_id' => null, 'quantity_component_id' => null])
            ->assertSessionHasErrors('service_id');
        $this->assertDatabaseHas('estimator_questions', ['id' => $id, 'quantity_component_id' => $concepto->id]);
    }


    public function test_service_prices_publish_a_complete_version_and_preserve_other_prices(): void
    {
        $web = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $landing = DB::table('services')->where('slug', 'landing-page')->value('id');
        $version = DB::table('tariff_versions')->max('id');
        $history = DB::table('service_prices')->where('tariff_version_id', $version)->orderBy('id')->get()->toJson();
        $other = DB::table('service_prices')->where('tariff_version_id', $version)->where('service_id', $web)->first();
        $components = DB::table('service_components')->where('service_id', $landing)->where('active', 1)->pluck('id')->map(fn($id) => ['id' => $id, 'min' => 10, 'max' => 20])->all();
        $payload = ['service_id' => $landing, 'name' => 'Actualización individual', 'min' => 100, 'max' => 200, 'components' => $components];
        $this->post('/admin/guardar/precios-servicio', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $next = DB::table('tariff_versions')->max('id');
        $this->assertGreaterThan($version, $next);
        $this->assertSame($history, DB::table('service_prices')->where('tariff_version_id', $version)->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('service_prices', ['tariff_version_id' => $next, 'service_id' => $web, 'price_min' => $other->price_min, 'price_max' => $other->price_max]);
        $this->assertDatabaseHas('service_prices', ['tariff_version_id' => $next, 'service_id' => $landing, 'price_min' => 100, 'price_max' => 200]);
        $this->post('/admin/guardar/precios-servicio', [...$payload, 'max' => 1])->assertSessionHasErrors('services.1.max');
        $this->assertSame($next, DB::table('tariff_versions')->max('id'));
        $this->post('/admin/guardar/precios-servicio', [...$payload, 'components' => []])->assertSessionHasErrors('components');
        $this->assertSame($next, DB::table('tariff_versions')->max('id'));
    }

    public function test_clients_cannot_publish_service_prices(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/admin/guardar/precios-servicio', [])->assertForbidden();
    }
}
