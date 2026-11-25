<?php

namespace Tests\Feature;

use App\Models\Organizacion;
use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagosProyectoTest extends TestCase
{
    use RefreshDatabase;

    // El presupuesto aceptado crea cuotas exactas y la revisión está limitada a las partes correspondientes.
    public function test_seña_y_saldo_se_registran_con_comprobantes_privados(): void
    {
        [$cliente, $supervisor, $datos] = $this->prepararPedido();
        Storage::fake('local');
        $this->actingAs($supervisor)->post('/acciones/presupuesto', [
            ...$datos, 'modalidad_pago' => 'sena_saldo', 'porcentaje_sena' => 50,
        ])->assertSessionHasErrors('instrucciones_transferencia');

        $version = $this->emitir([...$datos, 'modalidad_pago' => 'sena_saldo', 'porcentaje_sena' => 50,
            'instrucciones_transferencia' => 'Titular de prueba. Alias: prueba.synaux']);
        $this->actingAs($cliente)->get('/presupuestos/' . $version)->assertInertia(fn(Assert $pagina) => $pagina
            ->where('selected.plan_pago.porcentaje_sena', 50)
            ->where('selected.plan_pago.cuotas.0.importe_centavos', 606)
            ->where('selected.plan_pago.cuotas.1.importe_centavos', 605));
        $this->get('/presupuestos/' . $version . '/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $proyecto = $this->aceptar($version);
        $sena = DB::table('proyecto_pagos')->where('project_id', $proyecto)->where('concepto', 'sena')->firstOrFail();
        $this->assertDatabaseHas('proyecto_pagos', ['project_id' => $proyecto, 'concepto' => 'saldo', 'importe_centavos' => 605]);
        $ruta = "/proyectos/$proyecto/pagos/$sena->id";
        $comprobante = fn() => UploadedFile::fake()->createWithContent('transferencia.pdf', "%PDF-1.4\n%%EOF");
        $otroCliente = User::factory()->create();
        $this->actingAs($otroCliente)->post("$ruta/comprobante", ['comprobante' => $comprobante()])->assertNotFound();
        $this->get("$ruta/comprobante")->assertNotFound();
        $this->actingAs($supervisor)->post("$ruta/comprobante", ['comprobante' => $comprobante()])->assertForbidden();
        $this->actingAs($cliente)->patch("$ruta/revision", ['decision' => 'confirmado', 'intento' => 1])->assertForbidden();
        $this->post("$ruta/comprobante", ['comprobante' => UploadedFile::fake()->create('otro.txt')])->assertSessionHasErrors('comprobante');
        $this->post("$ruta/comprobante", ['comprobante' => $comprobante()])->assertSessionHasNoErrors();
        $primero = DB::table('proyecto_pagos')->where('id', $sena->id)->value('comprobante');
        Storage::disk('local')->assertExists($primero);
        $this->get("$ruta/comprobante")->assertOk()->assertDownload("comprobante-$sena->id.pdf");
        $this->get("/proyectos/$proyecto")->assertInertia(fn(Assert $pagina) => $pagina
            ->where('pagos.0.estado', 'en_revision')->where('pagos.0.tiene_comprobante', true)->missing('pagos.0.comprobante'));
        $this->post("$ruta/comprobante", ['comprobante' => $comprobante()])->assertSessionHasErrors('comprobante');

        $this->actingAs($supervisor)->get("$ruta/comprobante")->assertOk();
        $this->patch("$ruta/revision", ['decision' => 'observado', 'intento' => 1])->assertSessionHasErrors('observacion');
        $this->patch("$ruta/revision", ['decision' => 'observado', 'intento' => 1, 'observacion' => 'El importe no coincide.'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('proyecto_pagos', ['id' => $sena->id, 'estado' => 'observado', 'observacion' => 'El importe no coincide.']);

        $this->actingAs($cliente)->post("$ruta/comprobante", ['comprobante' => $comprobante()])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($primero);
        $this->actingAs($supervisor)->patch("$ruta/revision", ['decision' => 'confirmado', 'intento' => 1])->assertSessionHasErrors('decision');
        $this->patch("$ruta/revision", ['decision' => 'confirmado', 'intento' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('proyecto_pagos', ['id' => $sena->id, 'estado' => 'confirmado', 'revisado_por' => $supervisor->id]);
        $this->assertDatabaseHas('synaux_notifications', ['user_id' => $cliente->id, 'title' => 'Pago confirmado', 'entity_id' => $proyecto]);
        $this->assertDatabaseHas('projects', ['id' => $proyecto, 'status' => 'pending']);
    }

    // Una versión sin anticipo genera un solo pago. Un presupuesto anterior conserva sus condiciones.
    public function test_pago_total_al_finalizar_y_compatibilidad_con_versiones_anteriores(): void
    {
        [$cliente, $supervisor, $datos] = $this->prepararPedido();
        $this->actingAs($supervisor);
        $anterior = $this->emitir($datos);
        $proyectoAnterior = $this->actingAs($cliente)->aceptar($anterior);
        $this->assertDatabaseMissing('proyecto_pagos', ['project_id' => $proyectoAnterior]);

        $solicitud = DB::table('project_requests')->insertGetId([
            'user_id' => $cliente->id, 'service_id' => DB::table('services')->value('id'),
            'title' => 'Otro proyecto', 'description' => 'Un pedido para el segundo escenario.', 'status' => 'open',
        ]);
        $this->actingAs($supervisor);
        $version = $this->emitir([...$datos, 'request_id' => $solicitud, 'modalidad_pago' => 'al_finalizar',
            'instrucciones_transferencia' => 'Titular y alias de prueba']);
        $this->actingAs($cliente)->post('/acciones/decidir-presupuesto', [
            'id' => $version, 'decision' => 'accepted', 'confirmed' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('proyecto_pagos', 1);
        $this->assertDatabaseHas('proyecto_pagos', ['concepto' => 'saldo', 'importe_centavos' => 1211, 'estado' => 'pendiente']);
    }

    // Los datos de prueba representan una organización, un supervisor y una solicitud enviada.
    private function prepararPedido(): array
    {
        $this->seed(SynauxCatalogSeeder::class);
        $cliente = User::factory()->create();
        $supervisor = User::factory()->create();
        $organizacion = Organizacion::principal();
        $organizacion->forceFill(['razon_social' => 'synaux', 'correo' => 'empresa@example.test'])->save();
        $organizacion->miembros()->attach($supervisor->id, ['rol' => 'supervisor']);
        $solicitud = DB::table('project_requests')->insertGetId([
            'user_id' => $cliente->id, 'service_id' => DB::table('services')->value('id'),
            'title' => 'Proyecto de prueba', 'description' => 'Un pedido para verificar los pagos.', 'status' => 'open',
        ]);

        return [$cliente, $supervisor, [
            'request_id' => $solicitud, 'title' => 'Presupuesto de prueba', 'currency_code' => 'ARS', 'tax_rate' => 21,
            'valid_until' => today()->addDays(14)->toDateString(), 'estimated_delivery_days' => 30, 'terms' => 'Incluye revisión.',
            'items' => [['description' => 'Servicio digital', 'quantity' => 1, 'unit_price' => 10.01]], 'send' => true,
        ]];
    }

    private function emitir(array $datos): int
    {
        $this->post('/acciones/presupuesto', $datos)->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('budget_versions')->max('id');
    }

    private function aceptar(int $version): int
    {
        $this->post('/acciones/decidir-presupuesto', ['id' => $version, 'decision' => 'accepted', 'confirmed' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('projects')->where('accepted_budget_version_id', $version)->value('id');
    }
}
