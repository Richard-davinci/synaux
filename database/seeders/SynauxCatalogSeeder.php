<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Carga únicamente contenido inicial del prototipo: no crea cuentas ni publica precios de ejemplo.
class SynauxCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $content = [
            ['Desarrollo web', 'Sitio institucional', 'sitio-institucional', 'Una presencia digital clara y administrable para presentar tu organización y orientar a sus públicos.',
                'Diseñamos una experiencia web alineada con los objetivos de tu organización, con una estructura simple de recorrer y preparada para crecer.',
                ['Arquitectura de contenidos y navegación', 'Diseño de interfaz responsive', 'Desarrollo y configuración del sitio', 'Carga inicial y documentación de uso']],
            ['Diseño', 'Identidad visual', 'identidad-visual', 'Un sistema visual consistente para una marca nueva o en evolución.',
                'Construimos una identidad reconocible y flexible que traduzca la personalidad de la marca en decisiones visuales coherentes.',
                ['Dirección conceptual y universo visual', 'Logotipo y variantes principales', 'Paleta cromática y tipografías', 'Aplicaciones y guía básica de uso']],
            ['Marketing', 'Campaña digital', 'campana-digital', 'Estrategia, piezas y seguimiento para una campaña con objetivos concretos.',
                'Conectamos objetivos, mensajes y canales en una campaña digital pensada para llegar a las personas correctas.',
                ['Definición de objetivos y públicos', 'Estrategia de contenidos y canales', 'Piezas para los formatos acordados', 'Seguimiento e informe de resultados']],
        ];
        foreach ($content as [$category, $name, $slug, $summary, $description, $includes]) {
            $categorySlug = Str::slug($category);
            DB::table('service_categories')->insertOrIgnore(['slug' => $categorySlug, 'name' => $category, 'description' => $category, 'active' => 1]);
            $categoryId = DB::table('service_categories')->where('slug', $categorySlug)->value('id');
            DB::table('services')->insertOrIgnore(['slug' => $slug, 'name' => $name, 'service_category_id' => $categoryId, 'description' => $description,
                'summary' => $summary, 'deliverables' => json_encode($includes), 'active' => 1, 'estimation_mode' => $slug === 'sitio-institucional' ? 'automatic' : 'manual']);
        }
        $web = DB::table('services')->where('slug', 'sitio-institucional')->value('id');
        foreach ([['diseno', 'Diseño y arquitectura', true], ['seccion', 'Desarrollo de sección', false], ['contenido', 'Asistencia de contenidos', false], ['tienda', 'E-commerce', false]] as [$slug, $name, $default]) {
            DB::table('service_components')->insertOrIgnore(['service_id' => $web, 'slug' => $slug, 'name' => $name, 'description' => $name,
                'unit_code' => 'unidad', 'is_default' => $default, 'default_quantity' => 1, 'active' => 1]);
        }
        if (! DB::table('estimator_questions')->where('service_id', $web)->exists()) {
            foreach ([
                ['¿Cuántas secciones tendrá el sitio?', ['1 a 5', '6 a 10', 'Más de 10']],
                ['¿Ya tenés los textos e imágenes?', ['Sí, están listos', 'Necesito ayuda']],
                ['¿Necesitás vender online?', ['No', 'Sí, hasta 20 productos', 'Sí, más de 20 productos']],
            ] as $order => [$question, $options]) {
                $qid = DB::table('estimator_questions')->insertGetId(['service_id' => $web, 'question' => $question, 'input_type' => 'single_choice', 'sort_order' => $order, 'active' => 1]);
                foreach ($options as $position => $label) {
                    $oid = DB::table('estimator_options')->insertGetId(['question_id' => $qid, 'label' => $label, 'sort_order' => $position, 'active' => 1]);
                    $componentSlug = match ($order) {
                        0 => 'seccion', 1 => 'contenido', 2 => 'tienda'
                    };
                    $quantity = match ($order) {
                        0 => [3, 8, 12][$position], 1 => $position === 1 ? 8 : 0, 2 => $position === 0 ? 0 : 1
                    };
                    DB::table('estimation_rules')->insert(['estimator_option_id' => $oid, 'service_component_id' => DB::table('service_components')->where('service_id', $web)->where('slug', $componentSlug)->value('id'), 'quantity' => $quantity, 'active' => 1]);
                }
            }
        }
        foreach (['identidad-visual', 'campana-digital'] as $slug) {
            $id = DB::table('services')->where('slug', $slug)->value('id');
            if (! DB::table('estimator_questions')->where('service_id', $id)->exists()) {
                DB::table('estimator_questions')->insert(['service_id' => $id, 'question' => '¿Qué objetivo querés lograr y qué necesitás resolver?', 'input_type' => 'text', 'sort_order' => 0, 'active' => 1]);
            }
        }
    }
}
