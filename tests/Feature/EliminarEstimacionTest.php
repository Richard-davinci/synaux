<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Estimates\EstimateStore;
use Database\Seeders\SynauxCatalogSeeder;
use Database\Seeders\SynauxInstitutionalTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// El borrado solo afecta la lista propia; los datos que originaron pedidos siguen disponibles para su historial.
class EliminarEstimacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SynauxCatalogSeeder::class, SynauxInstitutionalTariffSeeder::class]);
    }

    private function estimate(User $owner): array
    {
        $catalog = app(ServiceCatalog::class);
        $service = $catalog->service('sitio-institucional');
        $question = $catalog->questions($service['database_id'])[0];
        $calculation = $catalog->calculate('sitio-institucional', [$question['id'] => $question['options'][0]->id]);
        return [app(EstimateStore::class)->save($owner->id, $calculation), $calculation];
    }

    public function test_deletion_requires_authentication_confirmation_and_ownership(): void
    {
        $owner = User::factory()->create();
        [$id] = $this->estimate($owner);
        $this->delete('/estimaciones/'.$id, ['confirmed' => true])->assertRedirect('/ingresar');
        $this->actingAs(User::factory()->create())->delete('/estimaciones/'.$id, ['confirmed' => true])->assertNotFound();
        $this->actingAs($owner)->delete('/estimaciones/'.$id, ['confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->assertDatabaseHas('estimates', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_deleted_estimates_disappear_from_list_detail_and_new_request_entry(): void
    {
        $owner = User::factory()->create();
        [$id] = $this->estimate($owner);
        $this->actingAs($owner)->delete('/estimaciones/'.$id, ['confirmed' => true])->assertRedirect('/cliente/estimaciones');
        $this->assertNotNull(DB::table('estimates')->where('id', $id)->value('deleted_at'));
        $this->get('/cliente/estimaciones')->assertInertia(fn (Assert $page) => $page->has('estimates', 0));
        $this->get('/estimaciones/'.$id)->assertNotFound();
        $this->get('/solicitudes/nueva?estimacion='.$id)->assertNotFound();
        $this->delete('/estimaciones/'.$id, ['confirmed' => true])->assertNotFound();
    }

    public function test_linked_draft_keeps_its_estimate_but_new_requests_cannot_reuse_it(): void
    {
        $owner = User::factory()->create();
        [$id, $calculation] = $this->estimate($owner);
        $data = ['estimate_id' => $id, 'service_id' => $calculation['service']['database_id'],
            'title' => 'Sitio para mi organización', 'description' => 'Necesitamos presentar nuestros servicios institucionales.',
            'visibility' => 'private', 'publish' => false, 'skills' => '', 'additional_services' => [],
            'contact' => ['name' => $owner->name, 'email' => $owner->email]];
        $this->actingAs($owner)->post('/acciones/solicitud', $data)->assertSessionHasNoErrors();
        $requestId = DB::table('project_requests')->value('id');
        $snapshot = DB::table('project_requests')->value('answers_snapshot');
        $this->delete('/estimaciones/'.$id, ['confirmed' => true])->assertRedirect();
        $this->get('/solicitudes/'.$requestId)->assertInertia(fn (Assert $page) => $page
            ->has('estimates', 0)->where('selectedEstimate.id', $id)->where('selectedEstimate.price_min', $calculation['min']));
        $this->post('/acciones/solicitud', [...$data, 'publish' => true])->assertSessionHasErrors('estimate_id');
        $this->post('/acciones/solicitud', [...$data, 'id' => $requestId, 'publish' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('project_requests', 1);
        $this->assertDatabaseHas('project_requests', ['id' => $requestId, 'estimate_id' => $id, 'status' => 'open', 'answers_snapshot' => $snapshot]);
        $this->get('/solicitudes/'.$requestId)->assertOk();
    }

    public function test_explicitly_saving_the_same_calculation_recovers_its_original_record(): void
    {
        $owner = User::factory()->create();
        [$id, $calculation] = $this->estimate($owner);
        $this->actingAs($owner)->delete('/estimaciones/'.$id, ['confirmed' => true])->assertRedirect();
        $this->withSession(['pending_estimate' => $calculation])->post('/acciones/guardar-estimacion')->assertRedirect('/estimaciones/'.$id);
        $this->assertDatabaseCount('estimates', 1);
        $this->assertDatabaseHas('estimates', ['id' => $id, 'deleted_at' => null, 'price_min' => $calculation['min']]);
    }
}
