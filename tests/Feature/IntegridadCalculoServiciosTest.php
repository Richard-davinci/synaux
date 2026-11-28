<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\TipoCliente;
use App\Services\Estimates\EstimateStore;
use App\Services\Solicitudes\RecorridoSolicitud;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Aísla tarifas y conceptos de prueba en SQLite en memoria para no tocar el catálogo comercial.
class IntegridadCalculoServiciosTest extends TestCase
{
    use RefreshDatabase;

    private array $catalogo;
    private ServiceCatalog $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SynauxCatalogSeeder::class);
        $this->motor = app(ServiceCatalog::class);
        $this->catalogo = $this->crearCatalogoPrueba();
    }

    // El servicio usa precios de prueba por segmento y deja libres sus conceptos para cada caso.
    private function crearCatalogoPrueba(): array
    {
        $categoria = DB::table('service_categories')->insertGetId([
            'name' => 'Pruebas de integridad', 'slug' => 'pruebas-integridad', 'description' => 'Datos aislados', 'active' => true,
        ]);
        DB::table('tariff_versions')->update(['active' => false]);
        $tarifa = DB::table('tariff_versions')->insertGetId([
            'name' => 'Tarifa aislada de pruebas', 'currency_code' => 'USD', 'ccl_rate' => 1700,
            'effective_from' => now()->subDay(), 'active' => true,
        ]);
        $opcionesCliente = TipoCliente::opcionesActivas()->keyBy('clave_segmento');
        $servicios = [];
        foreach (['principal', 'adicional'] as $tipo) {
            $servicio = DB::table('services')->insertGetId([
                'service_category_id' => $categoria, 'name' => ucfirst($tipo), 'slug' => 'integridad-' . $tipo,
                'description' => 'Servicio aislado de pruebas', 'active' => true, 'estimation_mode' => 'automatic',
            ]);
            DB::table('service_prices')->insert([
                'tariff_version_id' => $tarifa, 'service_id' => $servicio, 'price_min' => 0, 'price_max' => 0,
            ]);
            foreach (['particular' => 410, 'pyme' => 800, 'empresa' => 1200] as $clave => $precio) {
                DB::table('service_segment_prices')->insert([
                    'tariff_version_id' => $tarifa, 'service_id' => $servicio,
                    'estimator_option_id' => $opcionesCliente[$clave]->id, 'price_min' => $precio, 'price_max' => $precio,
                ]);
            }
            $servicios[$tipo] = $servicio;
        }

        return ['tarifa' => $tarifa, 'pregunta_cliente' => $opcionesCliente['particular']->question_id,
            'opciones_cliente' => $opcionesCliente, 'servicios' => $servicios];
    }

    private function pregunta(string $nombre, string $tipo = 'single_choice', ?int $componenteCantidad = null, bool $obligatoria = false, ?int $servicioId = null): array
    {
        $id = DB::table('estimator_questions')->insertGetId([
            'service_id' => $servicioId ?? $this->catalogo['servicios']['principal'], 'question' => $nombre,
            'input_type' => $tipo, 'sort_order' => 20 + DB::table('estimator_questions')->count(),
            'active' => true, 'required' => $obligatoria, 'quantity_component_id' => $componenteCantidad,
        ]);
        $opciones = [];
        if (in_array($tipo, ['single_choice', 'multiple_choice'], true)) {
            foreach (['Sí', 'También'] as $orden => $label) {
                $opciones[] = DB::table('estimator_options')->insertGetId([
                    'question_id' => $id, 'label' => $label, 'sort_order' => $orden, 'active' => true, 'clave_segmento' => null,
                ]);
            }
        }

        return ['id' => $id, 'opciones' => $opciones];
    }

    private function concepto(string $nombre, string $slug, ?float $precio, string $modo, ?int $servicioId = null): int
    {
        $id = DB::table('service_components')->insertGetId([
            'service_id' => $servicioId ?? $this->catalogo['servicios']['principal'], 'name' => $nombre, 'slug' => $slug,
            'description' => $nombre, 'unit_code' => 'unidad', 'active' => true, 'is_default' => false,
            'default_quantity' => 1, 'modo_cobro' => $modo,
        ]);
        if ($precio !== null) {
            DB::table('component_prices')->insert([
                'tariff_version_id' => $this->catalogo['tarifa'], 'service_component_id' => $id,
                'price_min' => $precio, 'price_max' => $precio,
            ]);
        }
        return $id;
    }

    private function regla(int $opcion, int $concepto, float $unidades = 1): void
    {
        DB::table('estimation_rules')->insert([
            'estimator_option_id' => $opcion, 'service_component_id' => $concepto, 'quantity' => $unidades, 'active' => true,
        ]);
    }

    private function respuestas(string $tipo = 'pyme'): array
    {
        $respuestas = [];
        foreach (DB::table('estimator_questions')->whereNull('service_id')->where('active', 1)->get() as $pregunta) {
            if ($pregunta->input_type === 'single_choice' || $pregunta->input_type === 'multiple_choice') {
                $opciones = DB::table('estimator_options')->where('question_id', $pregunta->id)->where('active', 1)->orderBy('sort_order')->get();
                $opcion = $pregunta->id === $this->catalogo['pregunta_cliente']
                    ? $this->catalogo['opciones_cliente'][$tipo] : $opciones->first();
                if ($opcion) $respuestas[$pregunta->id] = $pregunta->input_type === 'multiple_choice' ? [$opcion->id] : $opcion->id;
            } elseif ($pregunta->input_type === 'number') {
                $respuestas[$pregunta->id] = 1;
            } elseif ($pregunta->input_type === 'text') {
                $respuestas[$pregunta->id] = 'Respuesta de prueba';
            }
        }

        return $respuestas;
    }

    public function test_base_only_prices_each_stable_customer_type_even_after_visible_labels_change(): void
    {
        $precios = ['particular' => 410, 'pyme' => 800, 'empresa' => 1200];
        foreach ($precios as $clave => $precio) {
            $resultado = $this->motor->calculate('integridad-principal', $this->respuestas($clave));
            $this->assertSame('automatic', $resultado['mode']);
            $this->assertEquals($precio, $resultado['min']);
            $this->assertSame([], $resultado['items']);
        }
        DB::table('estimator_questions')->where('id', $this->catalogo['pregunta_cliente'])->update(['question' => '¿Quién contrata?']);
        DB::table('estimator_options')->where('id', $this->catalogo['opciones_cliente']['pyme']->id)->update(['label' => 'Comercio mediano']);
        $resultado = $this->motor->calculate('integridad-principal', $this->respuestas('pyme'));
        $this->assertEquals(800, $resultado['min']);
        $this->assertSame('Comercio mediano', $resultado['answers'][0]['label']);

        $control = $this->pregunta('¿Control de prueba?', 'single_choice');
        DB::table('estimator_questions')->where('id', $this->catalogo['pregunta_cliente'])->update([
            'visibility_question_id' => $control['id'], 'visibility_operator' => 'equals',
            'visibility_option_id' => $control['opciones'][0],
        ]);
        $resultado = $this->motor->calculate('integridad-principal', $this->respuestas('pyme'));
        $this->assertEquals(800, $resultado['min']);
        $this->assertNotEmpty($resultado['answers'][0]['value']);
    }

    public function test_client_preview_matches_the_economic_engine_and_does_not_persist_or_trust_amounts(): void
    {
        foreach (['particular', 'pyme', 'empresa'] as $segmento) {
            $answers = $this->respuestas($segmento);
            $esperado = $this->motor->calculate('integridad-principal', $answers);
            $response = $this->postJson('/estimador/previsualizar', [
                'service' => 'integridad-principal', 'answers' => $answers,
                'price' => 0, 'total_estimado' => 0,
            ]);

            $response->assertOk()->assertJsonPath('moneda', 'USD')
                ->assertJsonPath('version_tarifario.id', $this->catalogo['tarifa'])
                ->assertJsonPath('total_estimado.minimo', $esperado['min'])
                ->assertJsonPath('total_estimado.maximo', $esperado['max'])
                ->assertJsonPath('principal.completa', true);
        }

        $this->assertSame(0, DB::table('estimates')->count());
        $this->assertSame(0, DB::table('project_requests')->count());
        $this->assertSame(0, DB::table('budgets')->count());
    }

    public function test_client_preview_marks_required_answers_pending_without_weakening_definitive_validation(): void
    {
        $required = $this->pregunta('¿Qué tecnología preferís?', 'single_choice', null, true);
        $answers = $this->respuestas('pyme');
        unset($answers[$required['id']]);

        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $answers,
        ])->assertOk()->assertJsonPath('principal.completa', false)
            ->assertJsonPath('estado.0', 'respuestas_pendientes')
            ->assertJsonPath('preguntas_pendientes.0', '¿Qué tecnología preferís?')
            ->assertJsonPath('total_estimado', null)
            ->assertJsonPath('subtotal_conocido.minimo', 800);

        $this->post('/estimador/calcular', ['service' => 'integridad-principal', 'answers' => $answers])
            ->assertSessionHasErrors("answers.{$required['id']}");
    }

    // Las preguntas condicionales dependen de IDs; ocultar una requerida elimina su respuesta y cualquier regla.
    public function test_hidden_required_question_is_ignored_and_cannot_activate_an_economic_rule(): void
    {
        $origen = $this->pregunta('¿Querés sumar una galería?', 'single_choice', null, true);
        $concepto = $this->concepto('Galería opcional', 'galeria-condicional', 90, 'unico');
        $condicional = $this->pregunta('¿Cuántas galerías?', 'single_choice', null, true);
        DB::table('estimator_questions')->where('id', $condicional['id'])->update([
            'visibility_question_id' => $origen['id'], 'visibility_operator' => 'equals',
            'visibility_option_id' => $origen['opciones'][0],
        ]);
        $this->regla($condicional['opciones'][0], $concepto);

        $respuestas = $this->respuestas() + [$origen['id'] => $origen['opciones'][1], $condicional['id'] => $condicional['opciones'][0]];
        $resultado = $this->motor->calculate('integridad-principal', $respuestas);
        $this->assertEquals(800, $resultado['min']);
        $this->assertNotContains($condicional['id'], array_column($resultado['answers'], 'id'));
        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $respuestas,
        ])->assertOk()->assertJsonPath('completa', true)
            ->assertJsonPath('total_estimado.minimo', 800)
            ->assertJsonPath('preguntas_pendientes', []);

        $respuestas[$origen['id']] = $origen['opciones'][0];
        unset($respuestas[$condicional['id']]);
        $incompleto = $this->motor->calculate('integridad-principal', $respuestas, allowIncomplete: true);
        $this->assertTrue($incompleto['incomplete']);
        $this->assertNull($incompleto['min']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->motor->calculate('integridad-principal', $respuestas);
    }

    // Las condiciones de selección múltiple usan la presencia de una opción, no su posición o etiqueta.
    public function test_multiple_choice_condition_uses_stable_option_id_and_yes_no_keeps_option_economics(): void
    {
        $multiple = $this->pregunta('¿Qué módulos incluís?', 'multiple_choice');
        DB::table('estimator_options')->where('id', $multiple['opciones'][1])->update(['label' => 'Módulo premium']);
        $hijoId = DB::table('estimator_questions')->insertGetId([
            'service_id' => $this->catalogo['servicios']['principal'], 'question' => '¿Querés el montaje?',
            'input_type' => 'single_choice', 'is_yes_no' => true, 'sort_order' => 1000,
            'visibility_question_id' => $multiple['id'], 'visibility_operator' => 'contains',
            'visibility_option_id' => $multiple['opciones'][1], 'active' => true, 'required' => false,
        ]);
        $this->assertNotSame(0, $hijoId);
        $no = DB::table('estimator_options')->insertGetId(['question_id' => $hijoId, 'label' => 'No', 'sort_order' => 1, 'active' => true]);
        DB::table('estimator_options')->insert(['question_id' => $hijoId, 'label' => 'Sí', 'sort_order' => 0, 'active' => true]);
        $cargo = $this->concepto('Montaje', 'montaje-condicional', 35, 'unico');
        $this->regla($no, $cargo);
        $respuesta = $this->respuestas() + [$multiple['id'] => [$multiple['opciones'][1]], $hijoId => $no];
        $resultado = $this->motor->calculate('integridad-principal', $respuesta);
        $this->assertEquals(835, $resultado['min']);
        $this->assertSame('No', collect($resultado['answers'])->firstWhere('id', $hijoId)['label']);

        $sinModulo = $this->motor->calculate('integridad-principal', $this->respuestas() + [$multiple['id'] => [], $hijoId => $no]);
        $this->assertEquals(800, $sinModulo['min']);
    }

    // Un servicio adicional reutiliza respuestas globales visibles del servicio principal sin volver a preguntarlas.
    public function test_additional_service_inherits_global_answer_for_visibility_without_repeating_it(): void
    {
        $globalId = DB::table('estimator_questions')->insertGetId([
            'service_id' => null, 'question' => '¿El proyecto incluye comercio?', 'input_type' => 'single_choice',
            'sort_order' => 10, 'active' => true, 'required' => false,
        ]);
        $globalYes = DB::table('estimator_options')->insertGetId(['question_id' => $globalId, 'label' => 'Sí', 'sort_order' => 0, 'active' => true]);
        $globalNo = DB::table('estimator_options')->insertGetId(['question_id' => $globalId, 'label' => 'No', 'sort_order' => 1, 'active' => true]);
        $extraQuestion = DB::table('estimator_questions')->insertGetId([
            'service_id' => $this->catalogo['servicios']['adicional'], 'question' => '¿Qué pasarela necesitás?',
            'input_type' => 'single_choice', 'sort_order' => 100, 'active' => true, 'required' => true,
            'visibility_question_id' => $globalId, 'visibility_operator' => 'equals', 'visibility_option_id' => $globalYes,
        ]);
        $extraOption = DB::table('estimator_options')->insertGetId(['question_id' => $extraQuestion, 'label' => 'Tarjeta', 'sort_order' => 0, 'active' => true]);

        $answersMain = array_replace($this->respuestas(), [$globalId => $globalNo]);
        $main = $this->motor->calculate('integridad-principal', $answersMain);
        $hidden = app(RecorridoSolicitud::class)->calculateAdditionalServices(
            $this->catalogo['servicios']['principal'], [['service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 1, 'answers' => []]],
            $main['answers'], 'USD', [], true,
        )[0];
        $this->assertFalse($hidden['pricing_breakdown']['incomplete']);
        $this->assertNotContains($extraQuestion, array_column($hidden['answers'], 'id'));

        $answersMain[$globalId] = $globalYes;
        $main = $this->motor->calculate('integridad-principal', $answersMain);
        $visible = app(RecorridoSolicitud::class)->calculateAdditionalServices(
            $this->catalogo['servicios']['principal'], [['service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 1, 'answers' => []]],
            $main['answers'], 'USD', [], true,
        )[0];
        $this->assertTrue($visible['pricing_breakdown']['incomplete']);
        $this->assertNotSame(0, $extraOption);
    }

    public function test_live_preview_recalculates_when_a_response_is_selected_or_removed(): void
    {
        $component = $this->concepto('Integración seleccionable', 'integracion-seleccionable', 150, 'unico');
        $question = $this->pregunta('¿Necesitás esta integración?');
        $this->regla($question['opciones'][0], $component);
        $answers = $this->respuestas();
        $selectedAnswers = $answers;
        $selectedAnswers[$question['id']] = $question['opciones'][0];

        $withAnswer = $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal',
            'answers' => $selectedAnswers,
        ])->assertOk()->assertJsonPath('total_estimado.minimo', 950)
            ->assertJsonPath('adicionales.0.subtotal_minimo', 150);

        $withoutAnswer = $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $answers,
        ])->assertOk()->assertJsonPath('total_estimado.minimo', 800)
            ->assertJsonPath('adicionales', []);

        $this->assertNotSame($withAnswer->json('revision'), $withoutAnswer->json('revision'));
    }

    public function test_live_preview_marks_selected_unpriced_additional_for_professional_review(): void
    {
        $component = $this->concepto('Integración a cotizar', 'integracion-a-cotizar', null, 'unico');
        $question = $this->pregunta('¿Necesitás integración a medida?');
        $this->regla($question['opciones'][0], $component);
        $answers = $this->respuestas();
        $answers[$question['id']] = $question['opciones'][0];

        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal',
            'answers' => $answers,
        ])->assertOk()->assertJsonPath('completa', false)
            ->assertJsonPath('estado.0', 'conceptos_pendientes_de_cotizacion')
            ->assertJsonPath('total_estimado', null)
            ->assertJsonPath('subtotal_conocido.minimo', 800)
            ->assertJsonPath('adicionales.0.estado', 'sin_precio');
    }

    public function test_live_preview_requires_a_quote_when_the_selected_customer_segment_has_no_base_price(): void
    {
        DB::table('service_segment_prices')->where('tariff_version_id', $this->catalogo['tarifa'])
            ->where('service_id', $this->catalogo['servicios']['principal'])
            ->where('estimator_option_id', $this->catalogo['opciones_cliente']['pyme']->id)->delete();

        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $this->respuestas('pyme'),
        ])->assertOk()->assertJsonPath('completa', false)
            ->assertJsonPath('estado.0', 'segmento_sin_precio')
            ->assertJsonPath('total_estimado', null);
    }

    public function test_live_preview_recalculates_the_answers_and_additions_of_an_additional_service(): void
    {
        $component = $this->concepto('Ajuste del servicio adicional', 'ajuste-adicional-preview', 50, 'unico',
            $this->catalogo['servicios']['adicional']);
        $question = $this->pregunta('¿Necesitás el ajuste?', 'single_choice', null, false,
            $this->catalogo['servicios']['adicional']);
        $this->regla($question['opciones'][0], $component);

        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $this->respuestas('empresa'),
            'additional_services' => [[
                'service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 2,
                'answers' => [$question['id'] => $question['opciones'][0]],
            ]],
        ])->assertOk()->assertJsonPath('servicios_adicionales.0.subtotal_minimo', 2500)
            ->assertJsonPath('servicios_adicionales.0.conceptos.0.subtotal_min', 50)
            ->assertJsonPath('total_estimado.minimo', 3700);
    }

    public function test_confirming_estimate_detects_tariff_or_ccl_changed_since_live_preview(): void
    {
        $answers = $this->respuestas('pyme');
        $preview = $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $answers,
        ])->assertOk()->json();

        DB::table('tariff_versions')->where('id', $this->catalogo['tarifa'])->update(['ccl_rate' => 1800]);

        $this->postJson('/estimador/calcular', [
            'service' => 'integridad-principal', 'answers' => $answers,
            'preview_revision' => $preview['revision'],
        ])->assertJsonValidationErrors('pricing_revision');

        $this->assertNull(session('pending_estimate'));
        $this->assertSame(0, DB::table('estimates')->count());
    }

    public function test_client_preview_inherits_segment_for_additional_services_and_rejects_unpublished_services(): void
    {
        $mainAnswers = $this->respuestas('pyme');
        $expectedMain = $this->motor->calculate('integridad-principal', $mainAnswers);

        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $mainAnswers,
            'additional_services' => [[
                'service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 2,
                'answers' => [], 'price' => 0,
            ]],
        ])->assertOk()->assertJsonPath('servicios_adicionales.0.subtotal_minimo', 1600)
            ->assertJsonPath('total_estimado.minimo', $expectedMain['min'] + 1600);

        DB::table('services')->where('id', $this->catalogo['servicios']['adicional'])->update(['active' => false]);
        $this->postJson('/estimador/previsualizar', [
            'service' => 'integridad-principal', 'answers' => $mainAnswers,
            'additional_services' => [['service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 1]],
        ])->assertJsonValidationErrors('additional_services.0.service_id');
    }

    public function test_unique_fixed_charge_avoids_duplicate_and_reference_example_totals_usd_1330(): void
    {
        $tecnologia = $this->concepto('Tecnología específica', 'tecnologia-especifica', 150, 'unico');
        $qTecnologiaA = $this->pregunta('¿Usa tecnología A?');
        $qTecnologiaB = $this->pregunta('¿Usa tecnología B?');
        $this->regla($qTecnologiaA['opciones'][0], $tecnologia);
        $this->regla($qTecnologiaB['opciones'][0], $tecnologia);
        $logo = $this->concepto('Diseño de logotipo', 'logotipo', 120, 'unico');
        $imagenes = $this->concepto('Imágenes', 'imagenes', 80, 'unico');
        $qLogo = $this->pregunta('¿Necesitás logotipo?');
        $qImagenes = $this->pregunta('¿Necesitás imágenes?');
        $this->regla($qLogo['opciones'][0], $logo);
        $this->regla($qImagenes['opciones'][0], $imagenes);
        $secciones = $this->concepto('Sección adicional', 'seccion-adicional', 60, 'cantidad');
        $qSecciones = $this->pregunta('¿Cuántas secciones adicionales?', 'number', $secciones);

        $answers = $this->respuestas();
        $answers[$qTecnologiaA['id']] = $qTecnologiaA['opciones'][0];
        $answers[$qTecnologiaB['id']] = $qTecnologiaB['opciones'][0];
        $answers[$qLogo['id']] = $qLogo['opciones'][0];
        $answers[$qImagenes['id']] = $qImagenes['opciones'][0];
        $answers[$qSecciones['id']] = 3;
        $resultado = $this->motor->calculate('integridad-principal', $answers);

        $this->assertSame('automatic', $resultado['mode']);
        $this->assertEquals(1330, $resultado['min']);
        $this->assertEquals(150, collect($resultado['items'])->firstWhere('slug', 'tecnologia-especifica')['subtotal_min']);
        $this->assertEquals(180, collect($resultado['items'])->firstWhere('slug', 'seccion-adicional')['subtotal_min']);
    }

    public function test_accumulation_multiple_selection_and_changed_or_removed_answers_recalculate(): void
    {
        $concepto = $this->concepto('Bloques acumulables', 'bloques', 25, 'acumulativo');
        $q1 = $this->pregunta('¿Bloque uno?');
        $q2 = $this->pregunta('¿Bloque dos?');
        $this->regla($q1['opciones'][0], $concepto, 2);
        $this->regla($q2['opciones'][0], $concepto, 3);
        $multi = $this->pregunta('¿Qué módulos?', 'multiple_choice');
        $this->regla($multi['opciones'][0], $concepto, 1);
        $this->regla($multi['opciones'][1], $concepto, 1);
        $q1Values = [$q1['id'] => $q1['opciones'][0], $q2['id'] => $q2['opciones'][0],
            $multi['id'] => [$multi['opciones'][0], $multi['opciones'][0], $multi['opciones'][1]]];
        $ambas = $this->motor->calculate('integridad-principal', $this->respuestas() + $q1Values);
        $this->assertEquals(975, $ambas['min']);
        $una = $this->motor->calculate('integridad-principal', $this->respuestas() + [$q1['id'] => $q1['opciones'][0],
            $multi['id'] => [$multi['opciones'][0], $multi['opciones'][1]]]);
        $this->assertEquals(900, $una['min']);
        $cambiada = $this->motor->calculate('integridad-principal', $this->respuestas() + [$q1['id'] => $q1['opciones'][1],
            $q2['id'] => $q2['opciones'][0], $multi['id'] => []]);
        $this->assertEquals(875, $cambiada['min']);
        DB::table('estimator_questions')->whereIn('id', [$q1['id'], $q2['id'], $multi['id']])->update(['required' => false]);
        $sinSelecciones = $this->motor->calculate('integridad-principal', $this->respuestas());
        $this->assertEquals(800, $sinSelecciones['min']);
    }

    public function test_optional_missing_price_keeps_public_base_and_selected_concept_is_partial_not_free(): void
    {
        $pendiente = $this->concepto('Integración especial', 'integracion-pendiente', null, 'unico');
        $pregunta = $this->pregunta('¿Necesitás integración?');
        $this->regla($pregunta['opciones'][0], $pendiente);
        $reference = $this->motor->references()['integridad-principal'];
        $this->assertEquals(800, collect($reference['segments'])->firstWhere('key', 'pyme')['min']);
        $selected = $this->motor->calculate('integridad-principal', $this->respuestas() + [$pregunta['id'] => $pregunta['opciones'][0]]);
        $this->assertSame('manual', $selected['mode']);
        $this->assertNull($selected['min']);
        $this->assertEquals(800, $selected['subtotal_min']);
        $this->assertNull(collect($selected['items'])->firstWhere('component_id', $pendiente)['subtotal_min']);
        $cliente = User::factory()->create();
        $store = app(EstimateStore::class);
        $savedId = $store->save($cliente->id, $selected);
        $saved = $store->find($cliente->id, $savedId);
        $this->assertEquals(800, $saved['pricing_partial']['min']);
        $this->assertSame($this->catalogo['tarifa'], $saved['tariff_version_id']);
        $this->assertEquals(1700, $saved['ccl_rate_snapshot']);
        $this->assertNull($saved['items'][0]['subtotal_min']);

        $cero = $this->concepto('Opción gratuita confirmada', 'opcion-cero', 0, 'unico');
        $otra = $this->pregunta('¿Elegís la opción de importe cero?');
        $this->regla($otra['opciones'][0], $cero);
        $zeroResult = $this->motor->calculate('integridad-principal', $this->respuestas() + [$otra['id'] => $otra['opciones'][0]]);
        $this->assertSame('automatic', $zeroResult['mode']);
        $this->assertEquals(800, $zeroResult['min']);
        $this->assertSame(0.0, collect($zeroResult['items'])->firstWhere('component_id', $cero)['subtotal_min']);
    }

    public function test_additional_service_inherits_client_type_and_snapshots_currency_tariff_ccl_and_lines(): void
    {
        $resultadoPrincipal = $this->motor->calculate('integridad-principal', $this->respuestas('empresa'));
        foreach (['particular' => 410, 'pyme' => 800, 'empresa' => 1200] as $segmento => $precio) {
            $resultadoPrincipal = $this->motor->calculate('integridad-principal', $this->respuestas($segmento));
            $adicionales = app(RecorridoSolicitud::class)->calculateAdditionalServices(
                $this->catalogo['servicios']['principal'], [['service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 2, 'answers' => []]],
                $resultadoPrincipal['answers'], 'USD',
            );
            $guardado = $adicionales[0];
            $this->assertSame($segmento, $guardado['segmento_cliente']);
            $this->assertEquals($precio, $guardado['pricing_reference']['min']);
            $this->assertSame('USD', $guardado['pricing_breakdown']['currency']);
            $this->assertSame($this->catalogo['tarifa'], $guardado['pricing_breakdown']['tariff_version_id']);
            $this->assertEquals(1700, $guardado['pricing_breakdown']['ccl_rate']);
            $this->assertEquals($precio * 2, $guardado['pricing_breakdown']['subtotal_total_min']);
        }
    }

    public function test_saved_estimate_and_additional_snapshot_do_not_change_after_tariff_or_rule_edits(): void
    {
        $component = $this->concepto('Edición posterior', 'edicion-posterior', 60, 'unico');
        $question = $this->pregunta('¿Agregar ajuste?');
        $this->regla($question['opciones'][0], $component);
        $answers = [...$this->respuestas(), $question['id'] => $question['opciones'][0]];
        $calculation = $this->motor->calculate('integridad-principal', $answers);
        $user = User::factory()->create();
        $store = app(EstimateStore::class);
        $estimateId = $store->save($user->id, $calculation);
        $before = $store->find($user->id, $estimateId);

        $extraComponent = $this->concepto('Ajuste del servicio adicional', 'ajuste-adicional', 35, 'unico', $this->catalogo['servicios']['adicional']);
        $extraQuestion = $this->pregunta('¿Agregar ajuste adicional?', 'single_choice', null, false, $this->catalogo['servicios']['adicional']);
        $this->regla($extraQuestion['opciones'][0], $extraComponent);
        $extraBefore = app(RecorridoSolicitud::class)->calculateAdditionalServices(
            $this->catalogo['servicios']['principal'], [['service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 1,
                'answers' => [$extraQuestion['id'] => $extraQuestion['opciones'][0]]]],
            $calculation['answers'], 'USD',
        );
        DB::table('component_prices')->where('tariff_version_id', $this->catalogo['tarifa'])
            ->where('service_component_id', $component)->update(['price_min' => 999, 'price_max' => 999]);
        DB::table('component_prices')->where('tariff_version_id', $this->catalogo['tarifa'])
            ->where('service_component_id', $extraComponent)->update(['price_min' => 999, 'price_max' => 999]);
        DB::table('estimator_options')->where('id', $question['opciones'][0])->update(['label' => 'Texto revisado']);
        DB::table('estimation_rules')->where('estimator_option_id', $question['opciones'][0])->update(['quantity' => 50]);
        DB::table('estimator_options')->where('id', $extraQuestion['opciones'][0])->update(['label' => 'Texto adicional revisado']);
        DB::table('estimation_rules')->where('estimator_option_id', $extraQuestion['opciones'][0])->update(['quantity' => 50]);

        $this->assertSame($before, $store->find($user->id, $estimateId));
        $extraAfter = app(RecorridoSolicitud::class)->calculateAdditionalServices(
            $this->catalogo['servicios']['principal'], [['service_id' => $this->catalogo['servicios']['adicional'], 'quantity' => 1,
                'answers' => [$extraQuestion['id'] => $extraQuestion['opciones'][0]]]],
            $calculation['answers'], 'USD', $extraBefore,
        );
        $this->assertSame($extraBefore[0]['pricing_breakdown'], $extraAfter[0]['pricing_breakdown']);
        $this->assertSame($extraBefore[0]['pricing_reference'], $extraAfter[0]['pricing_reference']);
    }
}
