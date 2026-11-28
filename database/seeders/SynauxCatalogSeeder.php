<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Carga únicamente contenido inicial del prototipo: no crea cuentas ni publica precios de ejemplo.
class SynauxCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $content = [
            ['Desarrollo web y tiendas online', 'Sitio institucional', 'sitio-institucional', 'Una presencia digital clara y administrable para presentar tu organización y orientar a sus públicos.',
                'Diseñamos una experiencia web alineada con los objetivos de tu organización, con una estructura simple de recorrer y preparada para crecer.',
                ['Arquitectura de contenidos y navegación', 'Diseño de interfaz responsive', 'Desarrollo y configuración del sitio', 'Carga inicial y documentación de uso']],
            ['Diseño gráfico e identidad visual', 'Identidad visual', 'identidad-visual', 'Un sistema visual consistente para una marca nueva o en evolución.',
                'Construimos una identidad reconocible y flexible que traduzca la personalidad de la marca en decisiones visuales coherentes.',
                ['Dirección conceptual y universo visual', 'Logotipo y variantes principales', 'Paleta cromática y tipografías', 'Aplicaciones y guía básica de uso']],
            ['Marketing digital y contenidos', 'Campaña digital', 'campana-digital', 'Estrategia, piezas y seguimiento para una campaña con objetivos concretos.',
                'Conectamos objetivos, mensajes y canales en una campaña digital pensada para llegar a las personas correctas.',
                ['Definición de objetivos y públicos', 'Estrategia de contenidos y canales', 'Piezas para los formatos acordados', 'Seguimiento e informe de resultados']],
            ['Desarrollo web y tiendas online', 'Landing page', 'landing-page', 'Una página enfocada en presentar una oferta y facilitar una acción.',
                'Diseñamos y desarrollamos una página para presentar un producto, una campaña o un lanzamiento con un objetivo concreto.',
                ['Estructura y jerarquía de contenidos', 'Diseño responsive', 'Formulario o enlace de conversión']],
            ['Desarrollo web y tiendas online', 'Tienda online', 'tienda-online', 'Un catálogo con carrito y un proceso de compra adaptado a tu negocio.',
                'Definimos la navegación de la tienda y configuramos el proceso de compra según tus productos y operaciones.',
                ['Estructura del catálogo', 'Carrito y checkout', 'Configuración de medios de pago y envío']],
            ['Diseño UX/UI y producto digital', 'Diseño UX/UI', 'diseno-ux-ui', 'Interfaces claras para sitios y productos digitales.',
                'Diseñamos los flujos y las pantallas prioritarias de tu producto para que puedas evaluarlos antes del desarrollo.',
                ['Flujos de navegación', 'Diseño de pantallas', 'Prototipo interactivo']],
            ['Diseño UX/UI y producto digital', 'Auditoría de usabilidad', 'auditoria-usabilidad', 'Una revisión de la experiencia para detectar qué dificulta el uso.',
                'Evaluamos los recorridos principales de tu sitio o producto y priorizamos los problemas que afectan a sus usuarios.',
                ['Revisión de tareas principales', 'Hallazgos con ejemplos', 'Recomendaciones priorizadas']],
            ['Diseño UX/UI y producto digital', 'Prototipo interactivo', 'prototipo-interactivo', 'Un prototipo navegable para validar una idea antes de desarrollarla.',
                'Armamos un recorrido navegable con las pantallas necesarias para evaluar las interacciones principales.',
                ['Definición del recorrido', 'Prototipo navegable', 'Revisión del flujo con el equipo']],
            ['Diseño gráfico e identidad visual', 'Piezas para redes sociales', 'piezas-redes-sociales', 'Recursos gráficos coherentes para comunicar en tus redes.',
                'Adaptamos la identidad visual a los formatos que usás para comunicar en redes sociales.',
                ['Dirección visual', 'Diseño de piezas acordadas', 'Exportaciones por formato']],
            ['Marketing digital y contenidos', 'Auditoría SEO', 'auditoria-seo', 'Un diagnóstico de tu sitio para priorizar mejoras en buscadores.',
                'Revisamos la estructura, la indexación y el contenido de las páginas prioritarias para proponer mejoras concretas.',
                ['Revisión técnica y de contenidos', 'Informe de hallazgos', 'Lista priorizada de mejoras']],
            ['Marketing digital y contenidos', 'Estrategia de contenidos', 'estrategia-contenidos', 'Una planificación para comunicar con objetivos y consistencia.',
                'Definimos qué comunicar, para quién y en qué formatos según tus recursos y canales.',
                ['Definición de públicos y objetivos', 'Temas y formatos de contenido', 'Calendario editorial inicial']],
        ];
        // Mantiene una taxonomía enfocada en los servicios digitales de synaux, incluso antes de cargar servicios nuevos.
        foreach ([
            ['Desarrollo web y tiendas online', 'desarrollo-web-y-tiendas-online', 'Sitios institucionales, landing pages y comercio electrónico.'],
            ['Aplicaciones y software a medida', 'aplicaciones-y-software-a-medida', 'Aplicaciones, plataformas, sistemas e integraciones de software.'],
            ['Diseño UX/UI y producto digital', 'diseno-ux-ui-y-producto-digital', 'Experiencia, interfaces y prototipos para sitios y productos digitales.'],
            ['Diseño gráfico e identidad visual', 'diseno-grafico-e-identidad-visual', 'Identidad visual, piezas gráficas y recursos para comunicación digital.'],
            ['Marketing digital y contenidos', 'marketing-digital-y-contenidos', 'Estrategia, campañas y contenidos para canales digitales.'],
            ['Automatización e inteligencia artificial', 'automatizacion-e-inteligencia-artificial', 'Automatizaciones e integraciones con herramientas de inteligencia artificial.'],
        ] as [$nombre, $slug, $descripcion]) {
            DB::table('service_categories')->updateOrInsert(['slug' => $slug], [
                'name' => $nombre, 'description' => $descripcion, 'active' => 1,
            ]);
        }
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
        if (!DB::table('estimator_questions')->where('service_id', $web)->exists()) {
            foreach ([
                         ['¿Cuántas secciones tendrá el sitio?', ['1 a 5', '6 a 10', 'Más de 10']],
                         ['¿Ya tenés los textos e imágenes?', ['Sí, están listos', 'Necesito ayuda']],
                         ['¿Necesitás vender online?', ['No', 'Sí, hasta 20 productos', 'Sí, más de 20 productos']],
                     ] as $order => [$question, $options]) {
                $qid = DB::table('estimator_questions')->insertGetId(['service_id' => $web, 'question' => $question, 'input_type' => 'single_choice', 'sort_order' => $order, 'active' => 1]);
                foreach ($options as $position => $label) {
                    $oid = DB::table('estimator_options')->insertGetId(['question_id' => $qid, 'label' => $label, 'sort_order' => $position, 'active' => 1]);
                    $componentSlug = match ($order) {
                        0 => 'seccion',
                        1 => 'contenido',
                        2 => 'tienda'
                    };
                    $quantity = match ($order) {
                        0 => [3, 8, 12][$position],
                        1 => $position === 1 ? 8 : 0,
                        2 => $position === 0 ? 0 : 1
                    };
                    DB::table('estimation_rules')->insert(['estimator_option_id' => $oid, 'service_component_id' => DB::table('service_components')->where('service_id', $web)->where('slug', $componentSlug)->value('id'), 'quantity' => $quantity, 'active' => 1]);
                }
            }
        }
        // Cada servicio de revisión profesional necesita una pregunta inicial sobre el alcance.
        foreach ($content as [, , $slug]) {
            if ($slug === 'sitio-institucional') {
                continue;
            }

            $id = DB::table('services')->where('slug', $slug)->value('id');
            if (!DB::table('estimator_questions')->where('service_id', $id)->exists()) {
                DB::table('estimator_questions')->insert(['service_id' => $id, 'question' => '¿Qué objetivo querés lograr y qué necesitás resolver?', 'input_type' => 'text', 'sort_order' => 0, 'active' => 1]);
            }
        }
    }
}
