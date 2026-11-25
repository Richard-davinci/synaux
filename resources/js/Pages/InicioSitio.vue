<script setup>
import {Head} from '@inertiajs/vue3';
import {ref} from 'vue';
import AppButton from '../Components/UI/AppButton.vue';
import AppIcon from '../Components/UI/AppIcon.vue';
import SidePanel from '../Components/UI/SidePanel.vue';
import TarjetaServicio from '../Components/Catalog/TarjetaServicio.vue';
import EjemploPresupuesto from '../Components/Presupuestos/EjemploPresupuesto.vue';
import GrillaProyectos from '../Components/Portfolio/GrillaProyectos.vue';
import PublicLayout from '../Layouts/PublicLayout.vue';
import '../../css/panel.css';
import '../../css/ajustes-panel.css';

// La portada recibe una selección de servicios activos y proyectos publicados.
defineProps({
    featuredServices: {type: Array, default: () => []},
    featuredReferences: {type: Object, default: () => ({})},
    featuredWorks: {type: Array, default: () => []},
});

// Vuetify devuelve el foco al botón que abrió el panel.
const budgetOpen = ref(false);
</script>

<template>
    <Head title="Inicio">
        <meta name="description" content="Conocé los servicios de diseño, desarrollo web y marketing digital de synaux. Explorá el catálogo, el portfolio y las formas de contacto.">
    </Head>
    <PublicLayout>
        <!-- La primera acción lleva al formulario de presupuesto; el catálogo queda como alternativa. -->
        <section class="shell site-home-hero" aria-labelledby="site-home-title">
            <div>
                <h1 id="site-home-title">Sitios web, marcas y <em>campañas digitales</em></h1>
                <p>Elegí los servicios que necesitás para tu organización. Podés preparar tu solicitud sin crear una cuenta.</p>
                <div class="site-home-actions">
                    <AppButton v-if="!$page.props.auth?.is_admin" href="/solicitudes/nueva" arrow>Preparar presupuesto</AppButton>
                    <AppButton href="/plataforma" :variant="$page.props.auth?.is_admin ? 'primary' : 'secondary'">Explorar servicios</AppButton>
                </div>
            </div>
            <img class="site-home-photo" src="/images/inicio/diseno-web-synaux.webp"
                 alt="Notebook con el boceto de un sitio web en azul y turquesa." width="1536" height="1024" fetchpriority="high">
        </section>

        <!-- La muestra responde qué recibe el cliente y ofrece pedir presupuesto después de revisarla. -->
        <section class="site-home-budget" aria-labelledby="site-home-budget-title">
            <div class="shell site-home-section site-home-budget-layout">
                <div class="site-home-budget-copy">
                    <h2 id="site-home-budget-title">Qué incluye tu presupuesto</h2>
                    <p>Después de enviar tu solicitud, recibís una propuesta con el detalle del trabajo, el precio, el plazo de entrega y las condiciones.</p>
                </div>
                <SidePanel v-model="budgetOpen" title="Presupuesto de ejemplo" max-width="680"
                           body-label="Documento de ejemplo" close-label="Cerrar presupuesto de ejemplo">
                    <template #activator="{ props: activatorProps }">
                        <button v-bind="activatorProps" type="button" class="site-home-budget-trigger">
                            <AppIcon name="file"/>
                            <span>
                                <strong>Ver presupuesto de ejemplo</strong>
                                <small>Documento con importes ilustrativos</small>
                            </span>
                            <AppIcon name="arrow"/>
                        </button>
                    </template>
                    <div class="site-home-budget-document">
                        <EjemploPresupuesto/>
                    </div>
                    <template v-if="!$page.props.auth?.is_admin" #footer>
                        <AppButton href="/solicitudes/nueva" arrow>Preparar presupuesto</AppButton>
                    </template>
                </SidePanel>
            </div>
        </section>

        <!-- La portada muestra una fila de fichas del catálogo, con las mismas referencias publicadas. -->
        <section v-if="featuredServices.length" class="site-home-services" aria-labelledby="site-home-services-title">
            <div class="shell site-home-section">
                <div class="site-home-section-heading">
                    <h2 id="site-home-services-title">Servicios destacados</h2>
                    <AppButton href="/plataforma" compact variant="secondary" arrow>Ver catálogo completo</AppButton>
                </div>
                <div class="service-grid site-home-service-grid">
                    <TarjetaServicio v-for="service in featuredServices" :key="service.id" :service="service"
                                        :reference="featuredReferences[service.id]" heading-tag="h3"/>
                </div>
            </div>
        </section>

        <!-- Los proyectos enlazan a sus fichas y la sección se omite si el portfolio todavía no tiene publicaciones. -->
        <section v-if="featuredWorks.length" class="shell site-home-section" aria-labelledby="site-home-works-title">
            <div class="site-home-section-heading">
                <h2 id="site-home-works-title">Proyectos seleccionados</h2>
                <AppButton href="/portfolio" compact variant="secondary" arrow>Ver portfolio completo</AppButton>
            </div>
            <GrillaProyectos class="site-home-project-grid" :trabajos="featuredWorks" nivel-titulo="h3"/>
        </section>

        <!-- Estas entradas orientan a quien todavía está definiendo qué necesita. -->
        <section class="shell site-home-section" aria-labelledby="site-home-contact-title">
            <div class="site-home-contact">
                <div>
                    <h2 id="site-home-contact-title">¿Por dónde empiezo?</h2>
                    <p>Si todavía estás definiendo tu proyecto, conocé cómo trabajamos o contanos qué querés resolver.</p>
                </div>
                <div class="site-home-actions">
                    <AppButton href="/contacto" arrow>Hacer una consulta</AppButton>
                    <AppButton href="/nosotros" variant="secondary">Cómo trabajamos</AppButton>
                </div>
                <!-- Las dos fotos muestran la definición del alcance y el diseño de la estructura. -->
                <div class="site-home-process">
                    <figure>
                        <img src="/images/inicio/colaboracion-synaux.webp" alt="Dos personas revisan el esquema de un sitio."
                             width="1536" height="1024" loading="lazy" decoding="async">
                        <figcaption>Definimos el alcance</figcaption>
                    </figure>
                    <figure>
                        <img src="/images/inicio/bocetos-synaux.webp" alt="Dos bocetos en papel organizan las secciones de un sitio web."
                             width="1536" height="1024" loading="lazy" decoding="async">
                        <figcaption>Diseñamos la estructura</figcaption>
                    </figure>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* La portada comparte la tipografía y los colores del sitio; la foto acompaña la presentación. */
.site-home-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, .95fr);
    align-items: center;
    gap: clamp(28px, 5vw, 64px);
    padding-block: clamp(40px, 6vw, 76px);
}

h1, h2, h3 {
    font-family: 'Space Grotesk', sans-serif;
    color: #153b64;
    font-weight: 700;
    text-wrap: balance;
}

h1 {
    margin: 0 0 20px;
    font-size: clamp(1.95rem, 3.9vw, 3.25rem);
    text-wrap: pretty;
    line-height: 1.06;
}

h1 em {
    display: inline-block;
    max-width: 100%;
    color: #1f6f7f;
    font-style: normal;
}

.site-home-hero p, .site-home-contact p {
    max-width: 54ch;
    margin: 0;
    text-wrap: pretty;
}

.site-home-photo {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 18px;
    border: 1px solid #1f6f7f26;
}

.site-home-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.site-home-hero .site-home-actions {
    margin-top: 24px;
}

/* La sección mantiene la separación del resto de la portada. */
.site-home-services {
    border-bottom: 1px solid #1f6f7f14;
}

.site-home-section {
    padding-block: clamp(36px, 5vw, 60px);
}

.site-home-section-heading, .site-home-contact {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.site-home-section-heading h2 {
    margin: 0;
}

.site-home-service-grid {
    margin-top: 24px;
}

.site-home-project-grid {
    margin-top: 24px;
}

.site-home-budget-trigger:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

/* El acceso al ejemplo acompaña la explicación y abre el panel sin mover la página. */
.site-home-budget {
    border-block: 1px solid #1f6f7f14;
    background: #f5f9fa;
}

.site-home-budget-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, .95fr);
    align-items: start;
    gap: clamp(20px, 4vw, 52px);
}

.site-home-budget-copy h2 {
    margin: 0 0 12px;
}

.site-home-budget-copy p {
    max-width: 55ch;
    margin: 0;
    text-wrap: pretty;
}

.site-home-budget-trigger {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: 1px solid #1f6f7f33;
    border-radius: 12px;
    background: #fff;
    color: #153b64;
    cursor: pointer;
    text-align: left;
}

.site-home-budget-trigger:hover {
    border-color: #1f6f7f;
}

.site-home-budget-trigger small {
    display: block;
    margin-top: 2px;
    color: #446075;
    font-size: .875rem;
}

/* El documento conserva su fondo y su margen dentro del panel compartido. */
.site-home-budget-document {
    min-height: 100%;
    padding: clamp(12px, 3vw, 24px);
    background: #f5f9fa;
}

/* El cierre agrupa las dos alternativas para quien todavía está definiendo el alcance. */
.site-home-contact {
    padding: clamp(20px, 3vw, 32px);
    border: 1px solid #1f6f7f33;
    border-radius: 16px;
}

.site-home-contact h2 {
    margin: 0 0 8px;
}

/* Los recortes mantienen un encuadre parejo y las imágenes se apilan cuando falta ancho. */
.site-home-process {
    display: grid;
    flex: 1 0 100%;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    min-width: 0;
}

.site-home-process figure {
    margin: 0;
}

.site-home-process img {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 10;
    object-fit: cover;
    border-radius: 12px;
}

.site-home-process figcaption {
    margin-top: 8px;
    color: #153b64;
    font-weight: 700;
}

@media (max-width: 760px) {
    .site-home-hero, .site-home-budget-layout, .site-home-process {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
