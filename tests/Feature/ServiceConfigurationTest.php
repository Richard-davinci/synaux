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

    // Completa las preguntas obligatorias con la primera opción válida para comparar el motor antes y después de editar.
    private function respuestasDePrueba(ServiceCatalog $catalog, string $slug): array
    {
        $service = $catalog->service($slug);
        $answers = [];
        foreach ($catalog->questions($service['database_id']) as $question) {
            if (!$question['required']) continue;
            if (in_array($question['input_type'], ['single_choice', 'multiple_choice'], true)) {
                $option = $question['options'][0] ?? null;
                if ($option) $answers[$question['id']] = $question['input_type'] === 'multiple_choice' ? [$option->id] : $option->id;
            } elseif ($question['input_type'] === 'number') {
                $answers[$question['id']] = 1;
            } else {
                $answers[$question['id']] = 'Respuesta de prueba';
            }
        }

        return $answers;
    }

    public function test_old_sections_redirect_to_service_configuration(): void
    {
        $id = DB::table('services')->orderBy('id')->value('id');
        foreach (['preguntas' => 'preguntas', 'componentes' => 'precios', 'reglas' => 'estimacion'] as $old => $tab) {
            $this->get('/admin/' . $old)->assertRedirect("/admin/servicios/$id/editar?config=$tab");
        }
    }

    // Las opciones conservan sus IDs y las relaciones existentes al editar sus textos visibles.
    public function test_answer_options_can_link_multiple_components_without_replacing_the_calculation_engine(): void
    {
        $serviceId = DB::table('services')->where('slug', 'landing-page')->value('id');
        $tariffId = DB::table('tariff_versions')->where('active', 1)->max('id');
        $firstComponent = DB::table('service_components')->insertGetId([
            'service_id' => $serviceId, 'name' => 'Diseño de logotipo', 'slug' => 'test-logotipo',
            'description' => 'Concepto de prueba', 'unit_code' => 'unidad', 'active' => true,
            'is_default' => false, 'default_quantity' => 1, 'modo_cobro' => 'unico',
        ]);
        $secondComponent = DB::table('service_components')->insertGetId([
            'service_id' => $serviceId, 'name' => 'Banco de imágenes', 'slug' => 'test-imagenes',
            'description' => 'Concepto de prueba', 'unit_code' => 'unidad', 'active' => true,
            'is_default' => false, 'default_quantity' => 1, 'modo_cobro' => 'acumulativo',
        ]);
        DB::table('component_prices')->insert([
            ['tariff_version_id' => $tariffId, 'service_component_id' => $firstComponent, 'price_min' => 120, 'price_max' => 120],
            ['tariff_version_id' => $tariffId, 'service_component_id' => $secondComponent, 'price_min' => 80, 'price_max' => 80],
        ]);
        $catalog = app(ServiceCatalog::class);
        $answers = $this->respuestasDePrueba($catalog, 'landing-page');
        $antes = $catalog->calculate('landing-page', $answers);

        $datos = [
            'catalog_service_id' => $serviceId, 'service_id' => $serviceId,
            'question' => '¿Contás con un logotipo?', 'input_type' => 'single_choice',
            'sort_order' => 40, 'active' => true, 'required' => false,
            'options' => [
                ['id' => null, 'label' => 'Sí', 'component_ids' => []],
                ['id' => null, 'label' => 'No, necesito uno', 'component_ids' => [$firstComponent, $secondComponent]],
            ],
        ];
        $this->post('/admin/guardar/preguntas', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $questionId = DB::table('estimator_questions')->where('question', $datos['question'])->value('id');
        $options = DB::table('estimator_options')->where('question_id', $questionId)->orderBy('sort_order')->get();
        $optionIds = $options->pluck('id')->all();
        $this->assertSame(2, DB::table('estimation_rules')->whereIn('service_component_id', [$firstComponent, $secondComponent])->count());
        $this->assertSame($antes['min'], $catalog->calculate('landing-page', $answers)['min']);

        $datos['id'] = $questionId;
        $datos['options'] = [
            ['id' => $options[0]->id, 'label' => 'Sí, ya tengo uno', 'component_ids' => []],
            ['id' => $options[1]->id, 'label' => 'No, quiero diseñarlo', 'component_ids' => [$firstComponent, $secondComponent]],
        ];
        $this->post('/admin/guardar/preguntas', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($optionIds, DB::table('estimator_options')->where('question_id', $questionId)->orderBy('sort_order')->pluck('id')->all());
        $this->assertSame('No, quiero diseñarlo', DB::table('estimator_options')->where('id', $optionIds[1])->value('label'));
        $this->assertSame(2, DB::table('estimation_rules')->where('estimator_option_id', $optionIds[1])->where('active', true)->count());
        $this->assertEquals($antes['min'] + 200, $catalog->calculate('landing-page', $answers + [$questionId => $optionIds[1]])['min']);

        $datos['options'][1]['component_ids'] = [$firstComponent];
        $this->post('/admin/guardar/preguntas', $datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('estimation_rules', [
            'estimator_option_id' => $optionIds[1], 'service_component_id' => $secondComponent, 'active' => false,
        ]);
        $this->assertDatabaseHas('service_components', ['id' => $secondComponent, 'active' => true]);
        $this->assertEquals($antes['min'] + 120, $catalog->calculate('landing-page', $answers + [$questionId => $optionIds[1]])['min']);
    }

    // Las dependencias guardan IDs y el editor rechaza referencias circulares o posteriores.
    public function test_conditional_question_and_yes_no_are_saved_without_converting_existing_options(): void
    {
        $serviceId = (int)DB::table('services')->where('slug', 'landing-page')->value('id');
        $sourceData = [
            'catalog_service_id' => $serviceId, 'service_id' => $serviceId, 'question' => '¿Necesitás una tienda?',
            'input_type' => 'single_choice', 'sort_order' => 50, 'active' => true, 'required' => true,
            'options' => [['id' => null, 'label' => 'Sí', 'component_ids' => []], ['id' => null, 'label' => 'No', 'component_ids' => []]],
        ];
        $this->post('/admin/guardar/preguntas', $sourceData)->assertRedirect()->assertSessionHasNoErrors();
        $source = DB::table('estimator_questions')->where('question', $sourceData['question'])->first();
        $sourceOption = DB::table('estimator_options')->where('question_id', $source->id)->where('label', 'Sí')->first();
        $yesNoData = [
            'catalog_service_id' => $serviceId, 'service_id' => $serviceId, 'question' => '¿Necesitás carga inicial?',
            'input_type' => 'single_choice', 'is_yes_no' => true, 'sort_order' => 60, 'active' => true, 'required' => false,
            'visibility_question_id' => $source->id, 'visibility_operator' => 'equals', 'visibility_option_id' => $sourceOption->id,
            'options' => [['id' => null, 'label' => 'Sí', 'component_ids' => []], ['id' => null, 'label' => 'No', 'component_ids' => []]],
        ];
        $this->post('/admin/guardar/preguntas', $yesNoData)->assertRedirect()->assertSessionHasNoErrors();
        $target = DB::table('estimator_questions')->where('question', $yesNoData['question'])->first();
        $this->assertTrue((bool)$target->is_yes_no);
        $this->assertSame((int)$source->id, (int)$target->visibility_question_id);
        $this->assertSame('equals', $target->visibility_operator);
        $this->assertSame(['Sí', 'No'], DB::table('estimator_options')->where('question_id', $target->id)->orderBy('sort_order')->pluck('label')->all());

        $sourceData['id'] = $source->id;
        $sourceData['options'] = [
            ['id' => $sourceOption->id, 'label' => 'Incluir tienda', 'component_ids' => []],
            ['id' => DB::table('estimator_options')->where('question_id', $source->id)->where('label', 'No')->value('id'), 'label' => 'No', 'component_ids' => []],
        ];
        $this->post('/admin/guardar/preguntas', $sourceData)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((int)$sourceOption->id, (int)DB::table('estimator_options')->where('question_id', $source->id)->where('label', 'Incluir tienda')->value('id'));
        $this->assertSame((int)$sourceOption->id, (int)DB::table('estimator_questions')->where('id', $target->id)->value('visibility_option_id'));

        $sourceOptions = DB::table('estimator_options')->where('question_id', $source->id)->orderBy('sort_order')->get();
        $sourceData['options'] = $sourceOptions->map(fn ($option) => [
            'id' => $option->id, 'label' => $option->label, 'component_ids' => [],
        ])->all();
        $sourceData['visibility_question_id'] = $target->id;
        $sourceData['visibility_operator'] = 'equals';
        $sourceData['visibility_option_id'] = DB::table('estimator_options')->where('question_id', $target->id)->value('id');
        $this->post('/admin/guardar/preguntas', $sourceData)->assertSessionHasErrors('visibility_question_id');
        $this->assertSame(2, DB::table('estimator_options')->where('question_id', $source->id)->count());
    }

    // Una pregunta general puede tener reglas por servicio sin apagar vínculos de otros servicios.
    public function test_general_question_links_are_scoped_to_the_service_being_edited(): void
    {
        $landing = DB::table('services')->where('slug', 'landing-page')->value('id');
        $institutional = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        $components = [];
        foreach ([$landing, $institutional] as $index => $serviceId) {
            $components[] = DB::table('service_components')->insertGetId([
                'service_id' => $serviceId, 'name' => 'Adicional de prueba ' . $index, 'slug' => 'test-general-' . $index,
                'description' => 'Concepto de prueba', 'unit_code' => 'unidad', 'active' => true,
                'is_default' => false, 'default_quantity' => 1, 'modo_cobro' => 'unico',
            ]);
        }
        $question = [
            'service_id' => null, 'question' => '¿Necesitás material adicional?', 'input_type' => 'single_choice',
            'sort_order' => 41, 'active' => true, 'required' => false,
            'options' => [['id' => null, 'label' => 'Sí', 'component_ids' => [$components[0]]]],
        ];
        $this->post('/admin/guardar/preguntas', ['catalog_service_id' => $landing, ...$question])
            ->assertRedirect()->assertSessionHasNoErrors();
        $questionId = DB::table('estimator_questions')->where('question', $question['question'])->value('id');
        $optionId = DB::table('estimator_options')->where('question_id', $questionId)->value('id');

        $question['id'] = $questionId;
        $question['options'] = [['id' => $optionId, 'label' => 'Sí', 'component_ids' => [$components[1]]]];
        $this->post('/admin/guardar/preguntas', ['catalog_service_id' => $institutional, ...$question])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, DB::table('estimation_rules')->where('estimator_option_id', $optionId)->where('active', true)->count());
        $this->assertDatabaseHas('estimation_rules', ['estimator_option_id' => $optionId, 'service_component_id' => $components[0], 'active' => true]);
        $this->assertDatabaseHas('estimation_rules', ['estimator_option_id' => $optionId, 'service_component_id' => $components[1], 'active' => true]);
    }

    public function test_optional_questions_skip_empty_answers_but_validate_supplied_values(): void
    {
        $catalog = app(ServiceCatalog::class);
        $service = $catalog->service('landing-page');
        $answers = $this->respuestasDePrueba($catalog, 'landing-page');
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
        DB::table('tariff_versions')->update(['active' => false]);
        $version = DB::table('tariff_versions')->insertGetId([
            'name' => 'Tarifa USD de prueba', 'currency_code' => 'USD', 'effective_from' => now(), 'active' => true, 'created_at' => now(),
        ]);
        DB::table('service_prices')->insert(['tariff_version_id' => $version, 'service_id' => $web, 'price_min' => 50, 'price_max' => 60]);
        $history = DB::table('service_prices')->where('tariff_version_id', $version)->orderBy('id')->get()->toJson();
        $segments = app(\App\Services\Admin\PreciosCatalogo::class)->segmentos()
            ->map(fn($option) => ['id' => $option->id, 'min' => 100, 'max' => 200])->all();
        $payload = ['precios' => ['segmentos' => $segments, 'conceptos' => []]];
        $this->post("/admin/servicios/$landing/precios", $payload)->assertRedirect()->assertSessionHasNoErrors();
        $next = DB::table('tariff_versions')->max('id');
        $this->assertGreaterThan($version, $next);
        $this->assertSame($history, DB::table('service_prices')->where('tariff_version_id', $version)->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('service_prices', ['tariff_version_id' => $next, 'service_id' => $web, 'price_min' => 50, 'price_max' => 60]);
        foreach ($segments as $segment) $this->assertDatabaseHas('service_segment_prices', [
            'tariff_version_id' => $next, 'service_id' => $landing, 'estimator_option_id' => $segment['id'], 'price_min' => 100, 'price_max' => 200,
        ]);
        $invalid = $segments;
        $invalid[1]['max'] = 1;
        $this->post("/admin/servicios/$landing/precios", ['precios' => ['segmentos' => $invalid, 'conceptos' => []]])
            ->assertSessionHasErrors('precios.segmentos.1.max');
        $this->assertSame($next, DB::table('tariff_versions')->max('id'));
        $this->post("/admin/servicios/$landing/precios", ['precios' => ['segmentos' => [], 'conceptos' => []]])
            ->assertSessionHasErrors('precios.segmentos');
        $this->assertSame($next, DB::table('tariff_versions')->max('id'));
    }

    public function test_clients_cannot_publish_service_prices(): void
    {
        $this->actingAs(User::factory()->create());
        $service = DB::table('services')->where('slug', 'landing-page')->value('id');
        $this->post("/admin/servicios/$service/precios", [])->assertForbidden();
    }
}
