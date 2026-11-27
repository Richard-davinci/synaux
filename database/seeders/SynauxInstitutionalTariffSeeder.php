<?php

namespace Database\Seeders;

use App\Services\Tariffs\PrepararConfiguracionTarifaInstitucional;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Mantiene una opción de reparación manual para instalaciones antiguas; no carga precios ni crea versiones.
class SynauxInstitutionalTariffSeeder extends Seeder
{
    public function run(): void
    {
        $service = DB::table('services')->where('slug', 'sitio-institucional')->first();
        if (!$service) {
            throw new \RuntimeException('Creá primero el servicio institucional desde Administración → Servicios.');
        }
        app(PrepararConfiguracionTarifaInstitucional::class)->preparar((int)$service->id);
    }
}
