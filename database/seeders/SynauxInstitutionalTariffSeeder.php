<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Publica una versión explícita del paquete consultado en Tarifario.ar el 17/11/2026.
// Fuente: https://tarifario.ar/categoria/dev-web-app#service-pd-3
// ARS: Particulares 676240, PyMEs 1352480, Empresas 2028720. Referencias orientativas.
class SynauxInstitutionalTariffSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $name = 'Tarifario.ar · Institucional hasta 7 secciones · 2026-11';
            // Repetir esta carga no modifica versiones ni estimaciones ya emitidas.
            if (DB::table('tariff_versions')->where('name', $name)->exists()) {
                return;
            }

            $service = DB::table('services')->where('slug', 'sitio-institucional')->first();
            if (!$service) {
                throw new \RuntimeException('Cargá primero SynauxCatalogSeeder.');
            }
            $otherAutomatic = DB::table('services')->where('active', 1)->where('estimation_mode', 'automatic')->where('id', '!=', $service->id)->exists();
            if ($otherAutomatic) {
                throw new \RuntimeException('Esta versión solo cubre Sitio institucional.');
            }

            // Conserva registros anteriores para que las instantáneas históricas mantengan sus referencias.
            DB::table('estimator_questions')->where('service_id', $service->id)->update(['active' => 0]);
            DB::table('service_components')->where('service_id', $service->id)->update(['active' => 0]);
            DB::table('services')->where('id', $service->id)->update([
                'summary' => 'Sitio institucional en WordPress de hasta 7 secciones.',
                'description' => 'Diseño y desarrollo de un sitio corporativo o institucional responsive en WordPress, de hasta 7 secciones. Incluye formulario, blog básico, SEO on-page inicial y dos rondas de ajustes. Los requerimientos adicionales se revisan al preparar el presupuesto formal.',
                'deliverables' => json_encode([
                    'Diseño UI/UX y adaptación responsive',
                    'Hasta 7 secciones en WordPress',
                    'Formulario y blog básico',
                    'SEO on-page inicial',
                    'Dos rondas de ajustes',
                ]),
            ]);

            // El importe base corresponde a Particulares. Una unidad adicional lleva al valor PyME y dos a Empresas.
            $component = DB::table('service_components')->insertGetId([
                'service_id' => $service->id, 'name' => 'Adecuación por tipo de cliente',
                'slug' => 'segmento-tarifario-2026-11', 'description' => 'Diferencia entre los segmentos publicados por Tarifario.ar.',
                'unit_code' => 'segmento', 'active' => 1, 'is_default' => 0, 'default_quantity' => 1,
            ]);
            $question = DB::table('estimator_questions')->insertGetId([
                'service_id' => $service->id, 'question' => '¿Para qué tipo de cliente es el sitio?',
                'input_type' => 'single_choice', 'sort_order' => 0, 'active' => 1,
            ]);
            foreach (['Particular', 'PyME', 'Empresa'] as $quantity => $label) {
                $option = DB::table('estimator_options')->insertGetId([
                    'question_id' => $question, 'label' => $label, 'sort_order' => $quantity, 'active' => 1,
                ]);
                DB::table('estimation_rules')->insert([
                    'estimator_option_id' => $option, 'service_component_id' => $component,
                    'quantity' => $quantity, 'active' => 1,
                ]);
            }

            // Publica un paquete completo y una moneda única. Los valores anteriores siguen en su versión original.
            $version = DB::table('tariff_versions')->insertGetId([
                'name' => $name, 'currency_code' => 'ARS', 'effective_from' => now(),
                'active' => 1, 'created_at' => now(),
            ]);
            DB::table('service_prices')->insert([
                'tariff_version_id' => $version, 'service_id' => $service->id,
                'price_min' => 676240, 'price_max' => 676240,
            ]);
            DB::table('component_prices')->insert([
                'tariff_version_id' => $version, 'service_component_id' => $component,
                'price_min' => 676240, 'price_max' => 676240,
            ]);
        });
    }
}
