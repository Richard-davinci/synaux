<script setup>
import {Head} from '@inertiajs/vue3';
import AppButton from '../Components/UI/AppButton.vue';
import AppIcon from '../Components/UI/AppIcon.vue';
import EjemploPresupuesto from '../Components/Presupuestos/EjemploPresupuesto.vue';
import PublicLayout from '../Layouts/PublicLayout.vue';
import '../../css/panel.css';
import '../../css/ajustes-panel.css';

// La portada recibe una selección de servicios activos y proyectos publicados.
defineProps({
    featuredServices: {type: Array, default: () => []},
    featuredWorks: {type: Array, default: () => []},
});
</script>

<template>
    <Head title="Inicio">
        <meta name="description" content="Conocé los servicios de diseño, desarrollo web y marketing digital de synaux. Explorá el catálogo, el portfolio y las formas de contacto.">
    </Head>
    <PublicLayout>
        <!-- La primera acción lleva al formulario de presupuesto; el catálogo queda como alternativa. -->
        <section class="shell site-home-hero" aria-labelledby="site-home-title">
            <div>
                <h1 id="site-home-title">Diseño y desarrollo para tu <em>presencia digital</em></h1>
                <p>Creamos sitios web, identidades visuales y campañas digitales. Contanos qué necesitás para recibir una propuesta con alcance, precio y plazos.</p>
                <div class="site-home-actions">
                    <AppButton v-if="!$page.props.auth?.is_admin" href="/solicitudes/nueva" arrow>Pedir presupuesto</AppButton>
                    <AppButton href="/plataforma" :variant="$page.props.auth?.is_admin ? 'primary' : 'secondary'">Explorar servicios</AppButton>
                </div>
            </div>
            <img class="site-home-photo" src="/images/landing/planificacion.webp"
                 alt="Bocetos de un sitio web junto a una notebook." width="1200" height="800" fetchpriority="high">
        </section>

        <!-- El ejemplo se consulta en la propia portada y la acción abre el formulario público. -->
        <section class="site-home-budget" aria-labelledby="site-home-budget-title">
            <div class="shell site-home-section">
                <div class="site-home-section-heading">
                    <div>
                        <h2 id="site-home-budget-title">Presupuestos para tu proyecto</h2>
                        <p>Elegí los servicios y prepará tu solicitud sin cuenta. Después de enviarla, synaux te prepara una propuesta con los entregables, el precio, los plazos y las condiciones.</p>
                    </div>
                    <AppButton v-if="!$page.props.auth?.is_admin" href="/solicitudes/nueva" arrow>Pedir presupuesto</AppButton>
                </div>
                <details class="site-home-budget-details">
                    <summary>Ver presupuesto de ejemplo</summary>
                    <EjemploPresupuesto/>
                </details>
            </div>
        </section>

        <!-- Una ficha por categoría permite entender la oferta y abrir el detalle del servicio. -->
        <section v-if="featuredServices.length" class="site-home-services" aria-labelledby="site-home-services-title">
            <div class="shell site-home-section">
                <div class="site-home-section-heading">
                    <h2 id="site-home-services-title">Servicios destacados</h2>
                    <AppButton href="/plataforma" compact variant="secondary" arrow>Ver catálogo completo</AppButton>
                </div>
                <ul class="site-home-service-list">
                    <li v-for="service in featuredServices" :key="service.id">
                        <a class="site-home-service" :href="'/servicios/' + encodeURIComponent(service.id)">
                            <span class="site-home-category">{{ service.category }}</span>
                            <h3>{{ service.name }}</h3>
                            <p v-if="service.summary">{{ service.summary }}</p>
                            <span class="site-home-details" aria-hidden="true">Ver servicio <AppIcon name="arrow"/></span>
                        </a>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Los proyectos enlazan a sus fichas y la sección se omite si el portfolio todavía no tiene publicaciones. -->
        <section v-if="featuredWorks.length" class="shell site-home-section" aria-labelledby="site-home-works-title">
            <div class="site-home-section-heading">
                <h2 id="site-home-works-title">Proyectos seleccionados</h2>
                <AppButton href="/portfolio" compact variant="secondary" arrow>Ver portfolio completo</AppButton>
            </div>
            <ul class="site-home-works-list">
                <li v-for="work in featuredWorks" :key="work.id">
                    <a class="site-home-work" :href="'/trabajos/' + work.id" :aria-labelledby="'proyecto-inicio-' + work.id">
                        <img v-if="work.cover_path" class="site-home-work-image" :src="work.cover_path" alt="" loading="lazy">
                        <div class="site-home-work-copy">
                            <h3 :id="'proyecto-inicio-' + work.id">{{ work.title }}</h3>
                            <p v-if="work.description">{{ work.description }}</p>
                            <span class="site-home-details" aria-hidden="true">Ver proyecto <AppIcon name="arrow"/></span>
                        </div>
                    </a>
                </li>
            </ul>
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

/* Los servicios se adaptan al ancho disponible y toda la ficha abre un único destino. */
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

.site-home-service-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    gap: 16px;
    padding: 0;
    margin: 24px 0 0;
    list-style: none;
}

.site-home-service {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 20px;
    border: 1px solid #1f6f7f33;
    border-radius: 14px;
    background: #fff;
    text-decoration: none;
    overflow-wrap: anywhere;
}

.site-home-service:hover {
    border-color: #1f6f7f;
}

.site-home-service:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.site-home-category {
    color: #1f6f7f;
    font-size: .875rem;
    font-weight: 700;
}

.site-home-service h3 {
    margin: 6px 0 8px;
    text-wrap: pretty;
}

.site-home-service p {
    margin-bottom: 16px;
}

.site-home-details {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    color: #153b64;
    font-weight: 700;
}

.site-home-service:hover .site-home-details,
.site-home-service:focus-visible .site-home-details {
    text-decoration: underline;
}

/* La portada da espacio a un caso solo y mantiene una grilla de dos cuando hay más publicaciones. */
.site-home-works-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
    gap: 20px;
    margin: 24px 0 0;
    padding: 0;
    list-style: none;
}

.site-home-works-list > :only-child {
    max-width: 720px;
}

.site-home-work {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    border: 1px solid #1f6f7f33;
    border-radius: 14px;
    background: #fff;
    text-decoration: none;
}

.site-home-work:hover {
    border-color: #1f6f7f;
}

.site-home-work:focus-visible, .site-home-budget-details summary:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.site-home-work-image {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
}

.site-home-work-copy {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 20px;
    overflow-wrap: anywhere;
}

.site-home-work-copy h3 {
    margin: 0 0 8px;
}

.site-home-work-copy p {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    margin-bottom: 16px;
}

/* El ejemplo se despliega sin desplazar las acciones ni abrir otra página. */
.site-home-budget {
    border-block: 1px solid #1f6f7f14;
    background: #f5f9fa;
}

.site-home-budget p {
    max-width: 64ch;
    margin: 12px 0 0;
    text-wrap: pretty;
}

.site-home-budget-details {
    max-width: 650px;
}

.site-home-budget-details summary {
    width: fit-content;
    padding: 12px 0;
    color: #153b64;
    font-weight: 700;
    cursor: pointer;
}

.site-home-budget-details[open] summary {
    margin-bottom: 12px;
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

@media (max-width: 760px) {
    .site-home-hero {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
