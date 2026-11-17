<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SynauxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Verifica la instalación de etapa 1 con el marketplace reservado para revisión administrativa.
class StageTwoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_two_is_hidden_from_visitors_and_clients(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $this->get('/plataforma')->assertInertia(fn (Assert $page) => $page->where('stageTwo', false));
        $this->get('/oportunidades')->assertNotFound();
        $this->get('/profesionales')->assertNotFound();
        $this->get('/prototipo/muro')->assertNotFound();

        $this->actingAs(User::factory()->create());
        foreach (['/admin/etapa-2', '/profesional', '/cliente/publicaciones', '/cliente/propuestas', '/cliente/invitaciones'] as $path) {
            $this->get($path)->assertStatus($path === '/admin/etapa-2' ? 403 : 404);
        }
        $this->post('/acciones/propuesta', [])->assertNotFound();
        $this->post('/acciones/solicitud', [
            'service_id' => DB::table('services')->value('id'), 'title' => 'Una solicitud pública',
            'description' => 'Descripción suficiente de mi proyecto.', 'visibility' => 'public', 'publish' => true,
        ])->assertSessionHasErrors('visibility');
        $this->assertDatabaseCount('project_requests', 0);
        $this->get('/cliente')->assertInertia(fn (Assert $page) => $page->where('stageTwo', false));
    }

    public function test_admin_can_open_the_review_panel_and_stage_two_pages(): void
    {
        $this->seed(SynauxCatalogSeeder::class);
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $this->actingAs($admin)->get('/admin/etapa-2')->assertInertia(fn (Assert $page) => $page
            ->component('StageTwo')->where('stageTwo', true));
        $this->get('/oportunidades')->assertOk();
        $this->get('/profesionales')->assertOk();
        $this->get('/profesional')->assertOk();
        $this->get('/prototipo/muro')->assertOk();
    }
}
