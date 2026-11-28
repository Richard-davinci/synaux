<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\TipoCliente;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimuladorAdministrativoTest extends TestCase
{
    use RefreshDatabase;

    private int $serviceId;
    private int $tariffId;
    private array $segmentOptionIds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SynauxCatalogSeeder::class, SynauxInstitutionalTariffSeeder::class]);
        $this->serviceId = (int)DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        DB::table('service_components')->where('service_id', $this->serviceId)->update(['active' => false]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->configurarTarifa(['particular' => 410, 'pyme' => 800, 'empresa' => 1200]);
    }

    // Aísla una tarifa USD de prueba con segmentos reales y evita usar los importes del entorno de desarrollo.
    private function configurarTarifa(array $precios): void
    {
        DB::table('tariff_versions')->update(['active' => false]);
        $this->tariffId = (int)DB::table('tariff_versions')->insertGetId([
            'name' => 'Tarifa aislada para simulador', 'currency_code' => 'USD', 'effective_from' => now(),
            'active' => true, 'created_at' => now(),
        ]);
        DB::table('service_prices')->insert([
            'tariff_version_id' => $this->tariffId, 'service_id' => $this->serviceId, 'price_min' => 0, 'price_max' => 0,
        ]);
        $this->segmentOptionIds = [];
        foreach (TipoCliente::opcionesActivas() as $opcion) {
            $this->segmentOptionIds[$opcion->clave_segmento] = (int)$opcion->id;
            if (array_key_exists($opcion->clave_segmento, $precios)) {
                DB::table('service_segment_prices')->insert([
                    'tariff_version_id' => $this->tariffId, 'service_id' => $this->serviceId,
                    'estimator_option_id' => $opcion->id, 'price_min' => $precios[$opcion->clave_segmento],
                    'price_max' => $precios[$opcion->clave_segmento],
                ]);
            }
        }
    }

    // Agrega preguntas y conceptos temporales vinculados solo al servicio de esta base aislada.
    private function preguntaOpciones(string $texto, array $opciones, string $tipo = 'single_choice', ?int $concepto = null): array
    {
        $pregunta = (int)DB::table('estimator_questions')->insertGetId([
            'service_id' => $this->serviceId, 'question' => $texto, 'input_type' => $tipo,
            'sort_order' => 90, 'active' => true, 'required' => false, 'quantity_component_id' => $concepto,
        ]);
        $ids = [];
        foreach ($opciones as $orden => $etiqueta) {
            $ids[] = (int)DB::table('estimator_options')->insertGetId([
                'question_id' => $pregunta, 'label' => $etiqueta, 'sort_order' => $orden, 'active' => true,
            ]);
        }
        return [$pregunta, $ids];
    }

    private function concepto(string $nombre, string $slug, string $modo, ?float $importe): int
    {
        $id = (int)DB::table('service_components')->insertGetId([
            'service_id' => $this->serviceId, 'name' => $nombre, 'slug' => $slug,
            'description' => 'Concepto exclusivo de la prueba del simulador', 'unit_code' => 'unidad',
            'is_default' => false, 'default_quantity' => 1, 'modo_cobro' => $modo, 'active' => true,
        ]);
        if ($importe !== null) {
            DB::table('component_prices')->insert([
                'tariff_version_id' => $this->tariffId, 'service_component_id' => $id,
                'price_min' => $importe, 'price_max' => $importe,
            ]);
        }
        return $id;
    }

    private function regla(int $opcionId, int $conceptoId, float $cantidad = 1): void
    {
        DB::table('estimation_rules')->insert([
            'estimator_option_id' => $opcionId, 'service_component_id' => $conceptoId,
            'quantity' => $cantidad, 'active' => true,
        ]);
    }

    // Completa las preguntas obligatorias del servicio y selecciona el segmento pedido para el caso de prueba.
    private function respuestas(string $segmento = 'particular'): array
    {
        $catalog = app(ServiceCatalog::class);
        $questions = $catalog->questions($this->serviceId);
        $answers = [];
        foreach ($questions as $question) {
            if (!$question['required']) continue;
            if (($question['clave_sistema'] ?? null) === TipoCliente::PREGUNTA) {
                $answers[$question['id']] = $this->segmentOptionIds[$segmento];
            } elseif (in_array($question['input_type'], ['single_choice', 'multiple_choice'], true)) {
                $first = $question['options'][0] ?? null;
                if ($first) $answers[$question['id']] = $question['input_type'] === 'multiple_choice' ? [$first->id] : $first->id;
            } elseif ($question['input_type'] === 'number') {
                $answers[$question['id']] = 1;
            } else {
                $answers[$question['id']] = 'Respuesta de prueba';
            }
        }
        $system = collect($questions)->first(fn($question) => ($question['clave_sistema'] ?? null) === TipoCliente::PREGUNTA);
        $answers[$system['id']] = $this->segmentOptionIds[$segmento];

        return $answers;
    }

    private function simular(array $answers)
    {
        return $this->postJson("/admin/servicios/{$this->serviceId}/simular", ['answers' => $answers]);
    }

    public function test_only_an_administrator_can_open_the_simulator_endpoint(): void
    {
        auth()->logout();
        $this->postJson("/admin/servicios/{$this->serviceId}/simular", ['answers' => []])->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->postJson("/admin/servicios/{$this->serviceId}/simular", ['answers' => []])->assertForbidden();
    }

    public function test_draft_service_calculates_each_segment_using_only_its_base_price(): void
    {
        DB::table('services')->where('id', $this->serviceId)->update(['active' => false]);
        foreach (['particular' => 410, 'pyme' => 800, 'empresa' => 1200] as $segment => $expected) {
            $this->simular($this->respuestas($segment))->assertOk()->assertJsonPath('estado.0', 'listo')
                ->assertJsonPath('base.segmento', TipoCliente::ETIQUETAS[$segment])
                ->assertJsonPath('total_estimado.minimo', $expected)
                ->assertJsonPath('total_estimado.maximo', $expected)
                ->assertJsonPath('adicionales', []);
        }
        $this->assertDatabaseHas('services', ['id' => $this->serviceId, 'active' => false]);
    }

    public function test_an_explicit_zero_component_price_is_not_treated_as_missing(): void
    {
        $concepto = $this->concepto('Ajuste gratuito confirmado', 'ajuste-cero', 'unico', 0);
        [$pregunta, $opciones] = $this->preguntaOpciones('¿Aplicar ajuste?', ['Sí', 'No']);
        $this->regla($opciones[0], $concepto);
        $answers = $this->respuestas();
        $answers[$pregunta] = $opciones[0];

        $this->simular($answers)->assertOk()->assertJsonPath('estado.0', 'listo')
            ->assertJsonPath('adicionales.0.min', 0)->assertJsonPath('adicionales.0.subtotal_min', 0)
            ->assertJsonPath('total_estimado.minimo', 410);
    }

    public function test_unique_charges_do_not_duplicate_and_accumulative_charges_add_units(): void
    {
        $unico = $this->concepto('Tecnología específica', 'tecnologia-prueba', 'unico', 150);
        [$preguntaA, $opcionesA] = $this->preguntaOpciones('¿Usar tecnología A?', ['Sí']);
        [$preguntaB, $opcionesB] = $this->preguntaOpciones('¿Usar tecnología B?', ['Sí']);
        $this->regla($opcionesA[0], $unico);
        $this->regla($opcionesB[0], $unico);
        $answers = $this->respuestas();
        $answers[$preguntaA] = $opcionesA[0];
        $answers[$preguntaB] = $opcionesB[0];
        $this->simular($answers)->assertOk()->assertJsonPath('adicionales.0.quantity', 1)
            ->assertJsonPath('adicionales.0.activado_por.0', '¿Usar tecnología A? · Sí')
            ->assertJsonPath('adicionales.0.activado_por.1', '¿Usar tecnología B? · Sí')
            ->assertJsonPath('total_estimado.minimo', 560);

        $acumulativo = $this->concepto('Licencias acumuladas', 'licencias-prueba', 'acumulativo', 25);
        $this->regla($opcionesA[0], $acumulativo, 2);
        $this->regla($opcionesB[0], $acumulativo, 3);
        $respuesta = $this->simular($answers)->assertOk();
        $acumulados = collect($respuesta->json('adicionales'))->firstWhere('slug', 'licencias-prueba');
        $this->assertEquals(5, $acumulados['quantity']);
        $this->assertEquals(125, $acumulados['subtotal_min']);
        $respuesta->assertJsonPath('total_estimado.minimo', 685);
    }

    public function test_quantity_additional_and_multiple_answers_update_when_changed_or_removed(): void
    {
        $unitario = $this->concepto('Sección adicional', 'seccion-prueba', 'cantidad', 60);
        [$cantidadQuestion] = $this->preguntaOpciones('¿Cuántas secciones adicionales?', [], 'number', $unitario);
        $answers = $this->respuestas();
        $answers[$cantidadQuestion] = 3;
        $this->simular($answers)->assertOk()->assertJsonPath('adicionales.0.quantity', 3)
            ->assertJsonPath('adicionales.0.subtotal_min', 180)->assertJsonPath('total_estimado.minimo', 590);
        $answers[$cantidadQuestion] = 1;
        $this->simular($answers)->assertOk()->assertJsonPath('adicionales.0.quantity', 1)->assertJsonPath('total_estimado.minimo', 470);
        unset($answers[$cantidadQuestion]);
        $this->simular($answers)->assertOk()->assertJsonPath('adicionales', [])->assertJsonPath('total_estimado.minimo', 410);

        $opcionista = $this->concepto('Imágenes', 'imagenes-prueba', 'acumulativo', 40);
        [$multipleQuestion, $opciones] = $this->preguntaOpciones('¿Qué imágenes necesitás?', ['Pack A', 'Pack B'], 'multiple_choice');
        $this->regla($opciones[0], $opcionista);
        $this->regla($opciones[1], $opcionista);
        $answers[$multipleQuestion] = $opciones;
        $this->simular($answers)->assertOk()->assertJsonPath('adicionales.0.quantity', 2)
            ->assertJsonPath('adicionales.0.subtotal_min', 80);
        $answers[$multipleQuestion] = [$opciones[1]];
        $this->simular($answers)->assertOk()->assertJsonPath('adicionales.0.quantity', 1)
            ->assertJsonPath('adicionales.0.subtotal_min', 40);
    }

    public function test_selected_component_without_price_returns_a_partial_subtotal_for_review(): void
    {
        $component = $this->concepto('Logotipo', 'logotipo-sin-precio', 'unico', null);
        [$question, $options] = $this->preguntaOpciones('¿Necesitás logotipo?', ['Sí']);
        $this->regla($options[0], $component);
        $answers = $this->respuestas();
        $answers[$question] = $options[0];

        $this->simular($answers)->assertOk()->assertJsonFragment(['estado' => ['conceptos_sin_precio']])
            ->assertJsonPath('adicionales.0.min', null)->assertJsonPath('adicionales.0.subtotal_min', null)
            ->assertJsonPath('subtotal_conocido.minimo', 410)->assertJsonPath('subtotal_conocido.parcial', true)
            ->assertJsonPath('total_estimado', null)->assertJsonPath('requiere_revision', true);
    }

    public function test_segment_without_price_is_distinguished_from_an_unselected_segment(): void
    {
        $this->configurarTarifa(['particular' => 410, 'pyme' => 800]);
        $answers = $this->respuestas('empresa');
        $this->simular($answers)->assertOk()->assertJsonFragment(['estado' => ['segmento_sin_precio']])
            ->assertJsonPath('base.minimo', null)->assertJsonPath('total_estimado', null);
        $preguntaSistema = DB::table('estimator_questions')->where('clave_sistema', TipoCliente::PREGUNTA)->value('id');
        unset($answers[$preguntaSistema]);
        $this->simular($answers)->assertOk()->assertJsonFragment(['estado' => ['segmento_pendiente', 'respuestas_pendientes']]);
    }

    public function test_answers_from_another_service_are_rejected_and_simulation_has_no_commercial_side_effects(): void
    {
        $otherId = (int)DB::table('services')->where('slug', 'landing-page')->value('id');
        $foreignQuestion = (int)DB::table('estimator_questions')->where('service_id', $otherId)->value('id');
        $before = DB::table('estimates')->count() + DB::table('project_requests')->count() + DB::table('budgets')->count();
        $this->simular($this->respuestas() + [$foreignQuestion => 'Respuesta ajena'])
            ->assertUnprocessable()->assertJsonValidationErrors('answers');
        $this->simular($this->respuestas())->assertOk();
        $after = DB::table('estimates')->count() + DB::table('project_requests')->count() + DB::table('budgets')->count();
        $this->assertSame($before, $after);
    }

    public function test_simulator_total_matches_the_commercial_calculation_engine(): void
    {
        $component = $this->concepto('Desarrollo adicional', 'adicional-comparacion', 'unico', 120);
        [$question, $options] = $this->preguntaOpciones('¿Agregar tarea?', ['Sí']);
        $this->regla($options[0], $component);
        $answers = $this->respuestas();
        $answers[$question] = $options[0];
        $commercial = app(ServiceCatalog::class)->calculate('sitio-institucional', $answers);
        $this->simular($answers)->assertOk()->assertJsonPath('total_estimado.minimo', $commercial['min'])
            ->assertJsonPath('total_estimado.maximo', $commercial['max']);
    }

    public function test_reference_example_totals_usd_1330(): void
    {
        $this->configurarTarifa(['particular' => 800, 'pyme' => 800, 'empresa' => 800]);
        $tecnologia = $this->concepto('Tecnología específica', 'tecnologia-referencia', 'unico', 150);
        $logo = $this->concepto('Logotipo', 'logotipo-referencia', 'unico', 120);
        $imagenes = $this->concepto('Imágenes', 'imagenes-referencia', 'unico', 80);
        $secciones = $this->concepto('Secciones adicionales', 'secciones-referencia', 'cantidad', 60);
        [$qTech, $oTech] = $this->preguntaOpciones('¿Tecnología específica?', ['Sí']);
        [$qLogo, $oLogo] = $this->preguntaOpciones('¿Diseñar logotipo?', ['Sí']);
        [$qImages, $oImages] = $this->preguntaOpciones('¿Comprar imágenes?', ['Sí']);
        [$qSections] = $this->preguntaOpciones('¿Cuántas secciones extra?', [], 'number', $secciones);
        $this->regla($oTech[0], $tecnologia);
        $this->regla($oLogo[0], $logo);
        $this->regla($oImages[0], $imagenes);
        $answers = $this->respuestas();
        $answers[$qTech] = $oTech[0];
        $answers[$qLogo] = $oLogo[0];
        $answers[$qImages] = $oImages[0];
        $answers[$qSections] = 3;

        $this->simular($answers)->assertOk()->assertJsonPath('total_estimado.minimo', 1330)
            ->assertJsonPath('total_estimado.maximo', 1330)->assertJsonPath('moneda', 'USD')
            ->assertJsonPath('estado', ['listo']);
    }
}
