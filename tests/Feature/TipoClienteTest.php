<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\TipoCliente;
use App\Services\Estimates\EstimateStore;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TipoClienteTest extends TestCase
{
    use RefreshDatabase;

    private const PRINCIPAL = ['particular' => 110, 'pyme' => 220, 'empresa' => 330];
    private const ADICIONAL = ['particular' => 15, 'pyme' => 30, 'empresa' => 45];

    // Actualiza una copia del esquema anterior con precios ya asociados y una pregunta cuyo título había cambiado.
    public function test_migration_retains_existing_option_ids_and_price_relationships(): void
    {
        $conexion = 'sqlite_migracion_tipo_cliente';
        config(["database.connections.$conexion" => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $migracion = database_path('migrations/2026_11_28_000200_identificar_tipo_de_cliente.php');
        $anteriores = array_values(array_diff(glob(database_path('migrations/*.php')), [$migracion]));
        $migrador = app('migrator');

        try {
            $migrador->usingConnection($conexion, function () use ($migrador, $anteriores, $migracion) {
                Schema::clearResolvedInstance('db.schema');
                $migrador->getRepository()->createRepository();
                $migrador->run($anteriores);
                $this->seed(SynauxCatalogSeeder::class);
                $pregunta = DB::table('estimator_questions')->where('question', '¿Para qué tipo de cliente?')->value('id');
                DB::table('estimator_questions')->where('id', $pregunta)->update(['question' => '¿Cuál es tu organización?']);
                $opciones = DB::table('estimator_options')->where('question_id', $pregunta)->orderBy('sort_order')->get();
                $servicio = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
                $version = DB::table('tariff_versions')->insertGetId(['name' => 'Precios anteriores', 'currency_code' => 'USD',
                    'effective_from' => now()->subDay(), 'active' => true]);
                foreach ($opciones as $opcion) {
                    DB::table('service_segment_prices')->insert(['tariff_version_id' => $version, 'service_id' => $servicio,
                        'estimator_option_id' => $opcion->id, 'price_min' => $opcion->id * 10, 'price_max' => $opcion->id * 20]);
                }
                $precios = DB::table('service_segment_prices')->orderBy('id')->get()->toArray();

                $migrador->run([$migracion]);

                $this->assertDatabaseHas('estimator_questions', ['id' => $pregunta, 'clave_sistema' => TipoCliente::PREGUNTA,
                    'question' => '¿Cuál es tu organización?']);
                $this->assertSame($opciones->pluck('id')->all(), TipoCliente::opcionesActivas()->pluck('id')->all());
                $this->assertSame(array_keys(TipoCliente::ETIQUETAS), TipoCliente::opcionesActivas()->pluck('clave_segmento')->all());
                $this->assertEquals($precios, DB::table('service_segment_prices')->orderBy('id')->get()->toArray());
            });
        } finally {
            Schema::clearResolvedInstance('db.schema');
            DB::purge($conexion);
        }
    }

    // La migración identifica las filas del catálogo y las pruebas agregan precios exclusivamente en SQLite en memoria.
    private function catalogo(): array
    {
        $categoria = DB::table('service_categories')->insertGetId([
            'name' => 'Prueba de estimación', 'slug' => 'prueba-estimacion', 'description' => 'Categoría de prueba', 'active' => true,
        ]);
        $version = DB::table('tariff_versions')->insertGetId([
            'name' => 'Tarifario de prueba', 'currency_code' => 'USD', 'effective_from' => now()->subDay(), 'active' => true,
        ]);
        $opciones = TipoCliente::opcionesActivas()->keyBy('clave_segmento');
        $this->assertCount(3, $opciones);
        $servicios = [];
        foreach (['principal' => self::PRINCIPAL, 'adicional' => self::ADICIONAL] as $slug => $importes) {
            $servicio = DB::table('services')->insertGetId([
                'service_category_id' => $categoria, 'name' => ucfirst($slug), 'slug' => $slug,
                'description' => 'Servicio de prueba', 'estimation_mode' => 'automatic', 'active' => true,
            ]);
            DB::table('service_prices')->insert([
                'tariff_version_id' => $version, 'service_id' => $servicio, 'price_min' => 0, 'price_max' => 0,
            ]);
            foreach ($importes as $clave => $monto) {
                DB::table('service_segment_prices')->insert([
                    'tariff_version_id' => $version, 'service_id' => $servicio,
                    'estimator_option_id' => $opciones[$clave]->id, 'price_min' => $monto, 'price_max' => $monto,
                ]);
            }
            $servicios[$slug] = $servicio;
        }

        return [$version, $opciones, $servicios];
    }

    // Cambiar la pregunta y permutar los rótulos no cambia ninguna relación ni las estimaciones ya guardadas.
    public function test_renaming_customer_type_preserves_prices_ids_and_existing_estimates(): void
    {
        [$version, $opciones, $servicios] = $this->catalogo();
        $pregunta = $opciones['pyme']->question_id;
        $usuario = User::factory()->create();
        $estimador = app(ServiceCatalog::class);
        $almacen = app(EstimateStore::class);
        $cliente = [$pregunta => $opciones['pyme']->id];
        $id = $almacen->save($usuario->id, $estimador->calculate('principal', $cliente));
        $instantanea = $almacen->find($usuario->id, $id);
        $precios = DB::table('service_segment_prices')->orderBy('id')->get()->toArray();
        $rotulos = ['particular' => 'Empresa', 'pyme' => 'Organización pequeña', 'empresa' => 'Particular'];
        $admin = User::factory()->create(['is_admin' => true]);
        $cambios = ['catalog_service_id' => $servicios['principal'], 'id' => $pregunta, 'service_id' => null,
            'question' => '¿Qué organización hace el pedido?', 'input_type' => 'single_choice', 'sort_order' => 0,
            'active' => true, 'required' => true, 'etiquetas_segmentos' => $opciones->mapWithKeys(
                fn($opcion, $clave) => [$opcion->id => $rotulos[$clave]],
            )->all()];
        $this->actingAs($admin)->post('/admin/guardar/preguntas', $cambios)->assertSessionHasNoErrors();
        $this->post('/admin/guardar/preguntas', [...$cambios, 'active' => false])->assertSessionHasErrors('question');
        $this->post('/admin/guardar/preguntas', [...$cambios, 'etiquetas_segmentos' => [1 => 'Uno', 2 => 'Dos', 99999 => 'Otra']])
            ->assertSessionHasErrors('etiquetas_segmentos');

        $referencia = $estimador->references()['principal'];
        foreach (self::PRINCIPAL as $clave => $precio) {
            $resultado = $estimador->calculate('principal', [$pregunta => $opciones[$clave]->id]);
            $this->assertSame('automatic', $resultado['mode']);
            $this->assertEquals($precio, $resultado['base_min']);
            $this->assertEquals($precio, $resultado['min']);
            $this->assertSame($version, $resultado['tariff_id']);
            $this->assertSame($rotulos[$clave], $resultado['answers'][0]['label']);
            $this->assertEquals($precio, collect($referencia['segments'])->firstWhere('key', $clave)['min']);
        }
        $this->assertEquals($precios, DB::table('service_segment_prices')->orderBy('id')->get()->toArray());
        $this->assertSame($opciones->pluck('id')->all(), TipoCliente::opcionesActivas()->pluck('id')->all());
        $this->assertSame($instantanea, $almacen->find($usuario->id, $id));

        $this->actingAs($usuario)->get('/estimador?servicio=principal&paso=2&cliente=PyME')->assertInertia(fn(Assert $page) => $page
            ->where('requestInitialAnswers.' . $pregunta, $opciones['pyme']->id)
            ->where('requestQuestions.' . $servicios['principal'] . '.0.clave_sistema', TipoCliente::PREGUNTA));
    }

    // Una variante incompleta y el cero técnico nunca se ofrecen como importe comercial.
    public function test_missing_segment_or_price_routes_to_professional_review(): void
    {
        [$version, $opciones, $servicios] = $this->catalogo();
        $estimador = app(ServiceCatalog::class);
        $pregunta = $opciones['empresa']->question_id;
        $precios = DB::table('service_segment_prices')->where('tariff_version_id', $version)->where('service_id', $servicios['principal']);
        (clone $precios)->where('estimator_option_id', $opciones['empresa']->id)->delete();
        foreach ([[], [$pregunta => $opciones['empresa']->id]] as $respuestas) {
            $resultado = $estimador->calculate('principal', $respuestas);
            $this->assertSame('manual', $resultado['mode']);
            $this->assertNull($resultado['min']);
            $this->assertNull($resultado['base_min']);
        }
        $referencia = $estimador->references()['principal'];
        $this->assertSame(['particular', 'pyme'], array_column($referencia['segments'], 'key'));
        $this->assertGreaterThan(0, $referencia['min']);
        DB::table('estimator_questions')->where('id', $pregunta)->update(['active' => false]);
        $this->assertNull($estimador->calculate('principal', [$pregunta => $opciones['pyme']->id])['min']);
        $this->assertArrayNotHasKey('principal', $estimador->references());
        DB::table('estimator_questions')->where('id', $pregunta)->update(['active' => true]);
        $precios->delete();
        $this->assertNull($estimador->calculate('principal', [$pregunta => $opciones['pyme']->id])['min']);
        $this->assertArrayNotHasKey('principal', $estimador->references());
    }

    // El precio del adicional usa el segmento del pedido aunque el navegador intente enviar otra variante.
    public function test_additional_services_inherit_each_customer_type_and_keep_unpriced_lines_pending(): void
    {
        [$version, $opciones, $servicios] = $this->catalogo();
        $pregunta = $opciones['particular']->question_id;
        $this->actingAs(User::factory()->create());
        $payload = ['service_id' => $servicios['principal'], 'title' => 'Proyecto de prueba',
            'description' => 'Queremos probar el precio de dos servicios.', 'destination' => 'review',
            'additional_services' => [['service_id' => $servicios['adicional'], 'quantity' => 1,
                'answers' => [$pregunta => $opciones['particular']->id]]]];
        foreach (self::ADICIONAL as $clave => $precio) {
            $this->post('/solicitudes/preparar', [...$payload, 'answers' => [$pregunta => $opciones[$clave]->id]])
                ->assertSessionHasNoErrors()->assertSessionHas('pending_request.additional_services.0.segmento_cliente', $clave)
                ->assertSessionHas('pending_request.additional_services.0.pricing_reference.min', fn($min) => (float)$min === (float)$precio);
        }
        $this->post('/acciones/solicitud', [...$payload, 'answers' => [$pregunta => $opciones['empresa']->id],
            'visibility' => 'private', 'publish' => true])->assertSessionHasNoErrors();
        $guardado = json_decode(DB::table('project_requests')->value('requested_services'), true)[0];
        $this->assertSame('empresa', $guardado['segmento_cliente']);
        $this->assertEquals(self::ADICIONAL['empresa'], $guardado['pricing_reference']['min']);

        DB::table('service_segment_prices')->where('tariff_version_id', $version)->where('service_id', $servicios['adicional'])
            ->where('estimator_option_id', $opciones['empresa']->id)->delete();
        $this->post('/solicitudes/preparar', [...$payload, 'answers' => [$pregunta => $opciones['empresa']->id]])
            ->assertSessionHasNoErrors();
        $snapshot = session('pending_request.additional_services.0');
        $this->assertNull($snapshot['pricing_reference']);
    }
}
