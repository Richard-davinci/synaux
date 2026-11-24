<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Referencia consultada el 17/11/2026: https://tarifario.ar/categoria/dev-web-app#service-pd-1
// Landing optimizada: USD 300 para PyME, cotización CCL venta 1697,2; segmentos 50%, 100% y 150%.
class SynauxLandingTariffSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $name = 'Synaux · Landing optimizada · 2026-11-17';
            if (DB::table('tariff_versions')->where('name', $name)->exists()) return;
            $service = DB::table('services')->where('slug', 'landing-page')->lockForUpdate()->first();
            if (!$service) throw new \RuntimeException('Cargá primero SynauxCatalogSeeder.');
            $latest = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')->orderByDesc('id')->first();
            if ($latest && $latest->currency_code !== 'ARS') throw new \RuntimeException('La versión vigente debe usar ARS.');

            // La nueva configuración conserva preguntas y componentes anteriores para no romper instantáneas históricas.
            DB::table('estimator_questions')->where('service_id', $service->id)->update(['active' => 0]);
            DB::table('service_components')->where('service_id', $service->id)->update(['active' => 0]);
            DB::table('services')->where('id', $service->id)->update([
                'estimation_mode' => 'automatic',
                'summary' => 'Una landing optimizada para presentar una oferta y generar consultas.',
                'description' => 'Diseño y desarrollo de una página única orientada a conversión. Incluye UI/UX, copy básico, formulario, adaptación responsive, optimización de rendimiento básica, configuración de tracking y dos rondas de ajustes. Los adicionales se cotizan en el presupuesto formal.',
                'deliverables' => json_encode(['Diseño UI/UX y adaptación responsive', 'Página única con copy básico y formulario', 'Optimización de rendimiento básica', 'Configuración de tracking (GA4 o Pixel)', 'Dos rondas de ajustes'], JSON_UNESCAPED_UNICODE),
            ]);
            $component = DB::table('service_components')->insertGetId([
                'service_id' => $service->id, 'name' => 'Adecuación por tipo de cliente',
                'slug' => 'segmento-tarifario-2026-11', 'description' => 'Incremento de la referencia según el tipo de cliente.',
                'unit_code' => 'segmento', 'active' => 1, 'is_default' => 0, 'default_quantity' => 1,
            ]);
            $question = DB::table('estimator_questions')->insertGetId([
                'service_id' => $service->id, 'question' => '¿Para qué tipo de cliente es la landing?',
                'input_type' => 'single_choice', 'sort_order' => 0, 'active' => 1,
            ]);
            foreach (['Particular', 'PyME', 'Empresa'] as $quantity => $label) {
                $option = DB::table('estimator_options')->insertGetId([
                    'question_id' => $question, 'label' => $label, 'sort_order' => $quantity, 'active' => 1,
                ]);
                DB::table('estimation_rules')->insert([
                    'estimator_option_id' => $option, 'service_component_id' => $component, 'quantity' => $quantity, 'active' => 1,
                ]);
            }

            // Copia la versión completa para mantener las demás tarifas; nunca reescribe precios ya utilizados.
            $version = DB::table('tariff_versions')->insertGetId([
                'name' => $name, 'currency_code' => 'ARS', 'effective_from' => now(), 'active' => 1, 'created_at' => now(),
                // Estos campos siguen describiendo la referencia institucional que se copia sin modificaciones.
                'source_url' => $latest->source_url ?? null, 'source_usd' => $latest->source_usd ?? null,
                'source_rate' => $latest->source_rate ?? null, 'source_checked_at' => $latest->source_checked_at ?? null,
            ]);
            foreach (['service_prices' => ['service_id', $service->id], 'component_prices' => ['service_component_id', $component]] as $table => [$key, $excluded]) {
                foreach ($latest ? DB::table($table)->where('tariff_version_id', $latest->id)->where($key, '!=', $excluded)->get() : [] as $row) {
                    DB::table($table)->insert(['tariff_version_id' => $version, $key => $row->$key, 'price_min' => $row->price_min, 'price_max' => $row->price_max]);
                }
                DB::table($table)->insert(['tariff_version_id' => $version, $key => $excluded, 'price_min' => 254580, 'price_max' => 254580]);
            }
        });
    }
}
