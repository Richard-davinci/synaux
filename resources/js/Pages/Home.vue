<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import BrandLogo from '../Components/BrandLogo.vue';
import B from '../Components/SynauxButton.vue';
import I from '../Components/SynauxIcon.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';

// Estas áreas presentan la oferta inicial. El catálogo muestra los servicios disponibles.
const areas = [
    { icon: 'web', title: 'Desarrollo web', text: 'Sitios institucionales para presentar tu organización y orientar a quienes la visitan.' },
    { icon: 'palette', title: 'Identidad visual', text: 'Un sistema visual coherente para una marca nueva o en evolución.' },
    { icon: 'megaphone', title: 'Campañas digitales', text: 'Estrategia, piezas y seguimiento para una campaña con objetivos concretos.' },
];

// El recorrido distingue la estimación orientativa de la propuesta formal.
const steps = [
    { icon: 'folder', title: 'Elegí por dónde empezar', text: 'Explorá el catálogo y descubrí qué servicio se ajusta a tu idea.' },
    { icon: 'edit', title: 'Definí el alcance', text: 'Usá el estimador para orientar tu consulta y contanos qué necesitás.' },
    { icon: 'check', title: 'Acordá los próximos pasos', text: 'Revisá los entregables y las condiciones de la propuesta antes de avanzar.' },
];
</script>

<template>
    <Head title="Estrategia, diseño y desarrollo">
        <meta name="description" content="Explorá los servicios digitales de synaux y definí el próximo paso de tu proyecto.">
    </Head>

    <PublicLayout landing>
        <!-- La presentación vincula la marca aprobada con las dos entradas principales a la plataforma. -->
        <section class="landing-hero shell" aria-labelledby="landing-title">
            <div>
                <p class="eyebrow">Estrategia · Diseño · Desarrollo</p>
                <h1 id="landing-title">Dale forma a tu <em>próximo proyecto.</em></h1>
                <p class="landing-lead">En synaux conectamos estrategia, diseño y desarrollo para ayudarte a entender qué necesitás y avanzar con un alcance claro.</p>
                <div class="landing-actions">
                    <B href="/plataforma" arrow>Explorar servicios</B>
                    <a href="#como-funciona">Cómo funciona</a>
                </div>
            </div>
            <figure class="landing-brand"><img class="landing-photo" src="/images/landing/colaboracion.webp" alt="Dos personas revisando un proyecto digital en una mesa de trabajo." width="1200" height="800" fetchpriority="high"><div class="landing-logo-panel"><BrandLogo /></div></figure>
        </section>

        <!-- Las áreas orientan la lectura y comparten un acceso al catálogo vigente. -->
        <section id="que-es-synaux" class="landing-services" aria-labelledby="services-title">
            <div class="shell landing-section">
                <div class="landing-section-heading">
                    <h2 id="services-title">Servicios para tu proyecto</h2>
                    <B href="/plataforma" variant="secondary" arrow>Ver catálogo</B>
                </div>
                <div class="landing-service-grid">
                    <article v-for="area in areas" :key="area.title">
                        <I :name="area.icon" class="landing-service-icon" /><h3>{{ area.title }}</h3>
                        <p>{{ area.text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- La lista ordenada explica el recorrido y enlaza al estimador existente. -->
        <section id="como-funciona" class="shell landing-section landing-process" aria-labelledby="process-title">
            <div>
                <h2 id="process-title">De la idea a la propuesta</h2>
                <p class="landing-lead">Si ya tenés un servicio en mente, podés empezar con una estimación orientativa.</p>
                <B href="/estimador" arrow>Usar el estimador</B>
                <img class="landing-process-photo" src="/images/landing/planificacion.webp" alt="Bocetos de una página web junto a una computadora." width="1200" height="800" loading="lazy" decoding="async">
            </div>
            <ol class="landing-steps">
                <li v-for="step in steps" :key="step.title">
                    <h3><I :name="step.icon" />{{ step.title }}</h3>
                    <p>{{ step.text }}</p>
                </li>
            </ol>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* La guía de marca asigna Space Grotesk a títulos y Nunito a lectura y apoyos. */
.landing-hero,
.landing-section {
    font-family: 'Nunito', sans-serif;
}

.landing-hero h1,
.landing-section h2,
.landing-section h3 {
    color: #153b64;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
}

.landing-hero .eyebrow {
    color: #1f6f7f;
    font-size: .875rem;
    font-weight: 500;
}

.landing-hero .landing-lead,
.landing-section p {
    font-weight: 400;
    line-height: 1.6;
}

.landing-section p {
    font-size: 1rem;
}

.landing-section .landing-lead {
    font-size: 1.125rem;
}
/* El bloque inicial prioriza el mensaje y reserva espacio para el logo original de 360 px. */
.landing-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    align-items: center;
    gap: clamp(2rem, 6vw, 6rem);
    padding-block: clamp(3.5rem, 8vw, 7rem);
}

.landing-hero h1 {
    max-width: 12ch;
    margin: .8rem 0 1.4rem;
    font-size: clamp(2.8rem, 6vw, 5.8rem);
    line-height: .99;
}

.landing-hero em {
    color: #1f6f7f;
    font-style: normal;
}

.landing-lead {
    max-width: 48ch;
    color: #446075;
    font-size: 1.125rem;
}

.landing-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem 1.5rem;
    margin-top: 1.75rem;
}

.landing-actions > a:last-child {
    color: #153b64;
    font-weight: 700;
    text-underline-offset: .25em;
}

/* Las fotografías acompañan el trabajo digital y el logo se presenta sobre su propia superficie clara. */
.landing-brand { margin: 0; overflow: hidden; border: 1px solid rgb(31 111 127 / 18%); border-radius: 2rem; background: #fff; }
.landing-photo { display: block; width: 100%; height: auto; aspect-ratio: 3 / 2; object-fit: cover; }
.landing-logo-panel { display: grid; place-items: center; padding: 1.5rem; }
.landing-brand :deep(.brand-logo) { display: block; width: min(360px,100%); height: auto; }
.landing-process-photo { display: block; width: 100%; height: auto; aspect-ratio: 3 / 2; object-fit: cover; margin-top: 1.75rem; border-radius: 1.25rem; }
.landing-service-icon { width: 2rem; height: 2rem; color: #1f6f7f; margin-bottom: 1rem; }
.landing-steps h3 { display: flex; align-items: center; gap: .75rem; }
.landing-steps h3 :deep(.icon) { flex-shrink: 0; color: #1f6f7f; }
/* El fondo suave distingue el catálogo y las tarjetas conservan una jerarquía común. */
.landing-services {
    background: #f5f9fa;
}

.landing-section {
    padding-block: clamp(3rem, 6vw, 5.5rem);
}

.landing-section h2 {
    margin: 0;
    font-size: clamp(2rem, 4vw, 3.4rem);
    line-height: 1.08;
}

.landing-section-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}

.landing-service-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

/* El turquesa acentúa las tarjetas sobre su superficie blanca. */
.landing-service-grid article {
    padding: clamp(1.15rem, 3vw, 1.8rem);
    border: 1px solid rgb(31 111 127 / 18%);
    border-top: 3px solid #2db3a3;
    border-radius: 1.25rem;
    background: #fff;
}

.landing-section h3 {
    margin: 0 0 .4rem;
    font-size: 1.25rem;
}

.landing-section p {
    color: #446075;
}

.landing-service-grid p,
.landing-steps p {
    margin: 0;
}

/* El recorrido usa la numeración de la lista para indicar el orden de cada paso. */
.landing-process {
    display: grid;
    grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
    gap: clamp(1.75rem, 6vw, 5rem);
}

.landing-process .landing-lead {
    margin: 1rem 0 1.5rem;
}

.landing-steps {
    margin: 0;
    padding-left: 1.75rem;
}

.landing-steps li {
    padding: .9rem 0 .9rem .5rem;
    border-bottom: 1px solid rgb(31 111 127 / 18%);
}

.landing-steps li::marker {
    color: #1f6f7f;
    font-weight: 700;
}

/* La composición pasa a una columna antes de reducir el espacio disponible para los textos. */
@media (max-width: 780px) {
    .landing-hero,
    .landing-service-grid,
    .landing-process {
        grid-template-columns: minmax(0, 1fr);
    }

    .landing-hero {
        gap: 2rem;
    }

    .landing-brand {
        min-height: 0;
    }
}
</style>
