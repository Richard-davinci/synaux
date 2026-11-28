<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Prepara categorías para servicios digitales y conserva los vínculos de los registros ya existentes.
return new class extends Migration {
    public function up(): void
    {
        $categorias = [
            ['desarrollo-web-y-tiendas-online', 'Desarrollo web y tiendas online', 'Sitios institucionales, landing pages y comercio electrónico.', ['desarrollo-web']],
            ['aplicaciones-y-software-a-medida', 'Aplicaciones y software a medida', 'Aplicaciones, plataformas, sistemas e integraciones de software.', []],
            ['diseno-ux-ui-y-producto-digital', 'Diseño UX/UI y producto digital', 'Experiencia, interfaces y prototipos para sitios y productos digitales.', ['ux-ui']],
            ['diseno-grafico-e-identidad-visual', 'Diseño gráfico e identidad visual', 'Identidad visual, piezas gráficas y recursos para comunicación digital.', ['diseno']],
            ['marketing-digital-y-contenidos', 'Marketing digital y contenidos', 'Estrategia, campañas y contenidos para canales digitales.', ['marketing']],
            ['automatizacion-e-inteligencia-artificial', 'Automatización e inteligencia artificial', 'Automatizaciones e integraciones con herramientas de inteligencia artificial.', []],
        ];

        DB::transaction(function () use ($categorias) {
            foreach ($categorias as [$slug, $nombre, $descripcion, $slugsAnteriores]) {
                $actual = DB::table('service_categories')->where('slug', $slug)->first();
                $anteriores = DB::table('service_categories')->whereIn('slug', $slugsAnteriores)->get();

                if (!$actual && $anteriores->isNotEmpty()) {
                    $actual = $anteriores->first();
                    DB::table('service_categories')->where('id', $actual->id)->update([
                        'slug' => $slug, 'name' => $nombre, 'description' => $descripcion, 'active' => true,
                    ]);
                } else {
                    DB::table('service_categories')->updateOrInsert(['slug' => $slug], [
                        'name' => $nombre, 'description' => $descripcion, 'active' => true,
                    ]);
                    $actual = DB::table('service_categories')->where('slug', $slug)->first();
                }

                // Si ambas categorías ya existían, reúne sus servicios en la categoría canónica antes de limpiar duplicados.
                foreach ($anteriores->where('id', '!=', $actual->id) as $duplicada) {
                    DB::table('services')->where('service_category_id', $duplicada->id)
                        ->update(['service_category_id' => $actual->id]);
                    DB::table('service_categories')->where('id', $duplicada->id)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        // Las categorías quedan disponibles para servicios que la organización pudo crear después de la migración.
    }
};
