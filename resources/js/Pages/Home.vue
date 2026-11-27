<script setup>
import {Head} from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import BrandLogo from '../Components/Brand/BrandLogo.vue';
import BrandPatternFragment from '../Components/Brand/BrandPatternFragment.vue';
import B from '../Components/UI/AppButton.vue';
import I from '../Components/UI/AppIcon.vue';
import EjemploPresupuesto from '../Components/Presupuestos/EjemploPresupuesto.vue';
import {referenceHref} from '../support/prototype';
import '../../css/panel.css';

// Las cuatro áreas representan servicios de la primera etapa, sin anunciar el marketplace futuro.
const areas = [
    {
        icon: 'web',
        title: 'Desarrollo web',
        text: 'Sitios institucionales, páginas de lanzamiento y tiendas con carrito y medios de pago.'
    },
    {
        icon: 'palette',
        title: 'Identidad visual',
        text: 'Logotipo y variantes, paleta, tipografías y guía de uso de la marca.'
    },
    {
        icon: 'megaphone',
        title: 'Marketing digital',
        text: 'Piezas para campañas, calendarios editoriales y recomendaciones para mejorar la visibilidad en buscadores.'
    },
    {
        icon: 'layout',
        title: 'Diseño UX/UI',
        text: 'Flujos de navegación, diseño de pantallas y prototipos interactivos.'
    },
];
// El recorrido explica cuándo aparece una referencia orientativa y cuándo se confirma un presupuesto formal.
const steps = [
    {
        icon: 'folder',
        title: 'Explorá el catálogo',
        text: 'Mirá qué incluye cada servicio. Algunos muestran un precio orientativo.'
    },
    {
        icon: 'edit',
        title: 'Armá y enviá tu solicitud',
        text: 'Agregá los servicios, contanos qué querés lograr y revisá el resumen. Iniciá sesión o creá una cuenta para confirmar el envío.'
    },
    {
        icon: 'check',
        title: 'Revisá la propuesta',
        text: 'Cuando recibas la propuesta, podés revisarla y descargarla desde tu cuenta antes de decidir si la aceptás.'
    },
];
</script>

<template>
    <Head title="Pedí un presupuesto para tu proyecto digital">
        <meta name="description"
              content="Explorá los servicios de synaux y prepará tu solicitud. Recibí una propuesta con el alcance, el precio y los plazos.">
    </Head>
    <PublicLayout landing>
        <!-- El logo conduce a la portada del sitio; la campaña mantiene su acceso al catálogo. -->
        <section class="landing-hero shell" aria-labelledby="landing-title">
            <div class="landing-hero-copy">
                <a :href="referenceHref('/inicio', $page.props)" class="landing-brand-signature"
                   aria-label="synaux, Creciendo juntos. Ir al inicio">
                    <BrandLogo :with-slogan="true"/>
                </a>
                <h1 id="landing-title">Pedí un presupuesto para tu <em>proyecto digital</em></h1>
                <p class="landing-lead">Elegí los servicios que necesitás y contanos qué querés hacer. Después de enviar
                    tu solicitud, synaux te prepara una propuesta con el alcance, el precio y los plazos.</p>
                <div class="landing-actions">
                    <B v-if="!$page.props.auth?.es_cuenta_interna" href="/solicitudes/nueva" arrow>Preparar mi
                        solicitud</B>
                    <B v-else href="/plataforma" arrow>Explorar servicios</B>
                    <a href="#presupuesto-de-ejemplo" class="landing-action-link">Ver presupuesto de ejemplo</a>
                    <a v-if="!$page.props.auth?.es_cuenta_interna" :href="referenceHref('/plataforma', $page.props)"
                       class="landing-action-link">Ver catálogo</a>
                </div>
                <p v-if="!$page.props.auth" class="landing-support">Podés explorar y preparar tu solicitud sin cuenta.
                    Para enviarla, iniciá sesión o creá una cuenta.</p>
            </div>
            <div class="landing-visual-media">
                <BrandPatternFragment class="landing-hero-pattern"/>
                <figure class="landing-visual">
                    <img src="/images/landing/colaboracion-ricardo-catalogo.webp"
                         alt="Dos personas revisan el catálogo de synaux en una notebook."
                         width="1536" height="1024" fetchpriority="high">
                </figure>
            </div>
        </section>
        <!-- Las áreas orientan al catálogo sin repetir su listado completo ni prometer servicios futuros. -->
        <section class="landing-services" aria-labelledby="services-title">
            <div class="shell landing-section">
                <div class="landing-section-heading">
                    <h2 id="services-title">Qué podés contratar</h2>
                    <B compact href="/plataforma" variant="secondary" arrow>Explorar servicios</B></div>
                <div class="landing-service-grid">
                    <article v-for="area in areas" :key="area.title"><I :name="area.icon" class="landing-service-icon"/>
                        <h3>{{ area.title }}</h3>
                        <p>{{ area.text }}</p></article>
                </div>
            </div>
        </section>
        <!-- El proceso usa tres columnas equilibradas y se apila en móvil sin fotografías que generen vacíos. -->
        <section id="como-funciona" class="shell landing-section landing-process" aria-labelledby="process-title">
            <div class="landing-section-heading">
                <h2 id="process-title">Cómo pedir un presupuesto</h2>
            </div>
            <ol class="landing-steps">
                <li v-for="(step,index) in steps" :key="step.title">
                    <div class="step-top"><span aria-hidden="true">0{{ index + 1 }}</span><I :name="step.icon"/></div>
                    <h3>{{ step.title }}</h3>
                    <p>{{ step.text }}</p></li>
            </ol>
        </section>
        <!-- El enlace apunta a la muestra para que el documento aparezca primero al llegar desde el inicio. -->
        <section class="landing-budget-section" aria-labelledby="budget-title">
            <div class="shell landing-section landing-budget">
                <div>
                    <h2 id="budget-title">Qué incluye tu presupuesto</h2>
                    <p class="landing-lead">El precio orientativo es una referencia. La propuesta que recibís indica qué
                        se entrega, cuánto cuesta y en qué plazo.</p>
                    <ul class="landing-benefits">
                        <li><I name="file"/><span><strong>Alcance y precio por servicio</strong>Entregables, subtotal e IVA cuando corresponda.</span>
                        </li>
                        <li><I name="clock"/><span><strong>Plazos y condiciones</strong>Fecha de vigencia, plazo estimado de entrega y ajustes incluidos.</span>
                        </li>
                        <li><I name="user"/><span><strong>Disponible en tu cuenta</strong>Podés consultarlo y descargarlo.</span>
                        </li>
                    </ul>
                    <B v-if="!$page.props.auth?.es_cuenta_interna" compact href="/solicitudes/nueva" arrow>Preparar mi
                        solicitud</B>
                </div>
                <EjemploPresupuesto/>
            </div>
        </section>
        <!-- El cierre explica cuándo conviene preparar un pedido y cuándo consultar. -->
        <section class="shell landing-section" aria-labelledby="landing-close-title">
            <div class="landing-close">
                <div>
                    <h2 id="landing-close-title">Contanos qué necesitás</h2>
                    <p v-if="$page.props.auth?.es_cuenta_interna">Si tenés dudas sobre qué servicio elegir,
                        consultanos.</p>
                    <p v-else>Si ya sabés qué necesitás, prepará tu solicitud. Si tenés dudas sobre qué servicio elegir,
                        consultanos.</p>
                </div>
                <div class="landing-close-actions">
                    <B v-if="!$page.props.auth?.es_cuenta_interna" href="/solicitudes/nueva" arrow>Preparar mi
                        solicitud</B>
                    <B href="/contacto" variant="secondary">Hacer una consulta</B>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* La decoración del hero comparte el original amplio y su escala con el borde del footer. */
.landing-hero, .landing-section {
    font-family: 'Nunito', sans-serif;
}

h1, h2, h3 {
    color: #153b64;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    text-wrap: balance;
}

p {
    color: #446075;
    text-wrap: pretty;
    line-height: 1.6;
}

/* La marca y la foto arrancan sobre la misma línea, con margen superior para la trama. */
.landing-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
    gap: clamp(24px, 5vw, 64px);
    align-items: start;
    padding-block: clamp(48px, 5vw, 80px) clamp(32px, 4vw, 52px);
}

/* El título deja espacio para leer «proyecto digital» como una unidad en escritorio. */
h1 {
    font-size: clamp(2.25rem, 3.75vw, 3.375rem);
    text-wrap: pretty;
    line-height: 1.05;
    margin: 0 0 20px;
}

h1 em {
    display: inline-block;
    max-width: 100%;
    color: #1f6f7f;
    font-style: normal;
}

.landing-lead {
    max-width: 52ch;
    font-size: 1.075rem;
    margin-top: 20px;
}

.landing-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px 24px;
    margin-top: 24px;
}

/* Los accesos al ejemplo y al catálogo son enlaces secundarios con área táctil amplia. */
.landing-action-link {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    color: #153b64;
    font-weight: 700;
    text-underline-offset: 4px;
}

.landing-action-link:hover, .landing-action-link:focus-visible {
    text-decoration: underline;
}

.landing-action-link:focus-visible, .landing-brand-signature:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.landing-support {
    font-size: .85rem;
    max-width: 50ch;
    margin: 16px 0 0;
}

.landing-visual-media {
    position: relative;
    min-width: 0;
}

/* La firma completa encabeza el bloque de texto y comparte el margen del título. */
.landing-brand-signature {
    display: block;
    width: min(230px, 100%);
    margin: 0 0 24px;
}

.landing-brand-signature :deep(.brand-logo) {
    display: block;
    width: 100%;
    height: auto;
}

/* El fragmento elimina piezas que no caben completas y deja visible la esquina derecha. */
.landing-hero-pattern {
    position: absolute;
    top: -32px;
    right: -32px;
    width: min(76%, 380px);
    height: 180px;
    opacity: .4;
    pointer-events: none;
}

.landing-visual {
    position: relative;
    margin: 0;
    border: 1px solid #1f6f7f22;
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 6px 20px #153b6412;
}

.landing-visual img {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: 3 / 2;
    object-fit: cover;
}

.landing-section {
    padding-block: clamp(40px, 5vw, 64px);
}

.landing-section h2 {
    font-size: clamp(1.75rem, 3vw, 2.6rem);
    line-height: 1.15;
    margin: 0;
    max-width: 26ch;
}

.landing-section h3 {
    font-size: 1.25rem;
    line-height: 1.25;
    margin: 0 0 8px;
}

.landing-section-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
}

.landing-services, .landing-budget-section {
    background: #f5f9fa;
    border-block: 1px solid #1f6f7f14;
}

.landing-service-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.landing-service-grid article {
    padding: 22px;
    border: 1px solid #1f6f7f22;
    border-top: 3px solid #2db3a3;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 3px 12px #153b640a;
}

.landing-service-grid p {
    font-size: .95rem;
    margin: 0;
}

.landing-service-icon {
    width: 28px;
    height: 28px;
    color: #1f6f7f;
    margin-bottom: 20px;
}

.landing-steps {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 28px;
    padding: 0;
    margin: 0;
    counter-reset: landing-step;
}

.landing-steps li {
    min-width: 0;
    padding-top: 16px;
    border-top: 2px solid #1f6f7f26;
}

/* El número y el ícono de cada paso forman una sola referencia visual; los datos de steps mantienen su orden y estilo original. */
.step-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    color: #1f6f7f;
}

.step-top span {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
}

.landing-steps p {
    margin: 0;
}

.landing-budget {
    display: grid;
    grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
    gap: clamp(24px, 5vw, 64px);
    align-items: center;
}

.landing-benefits {
    list-style: none;
    display: grid;
    gap: 20px;
    padding: 0;
    margin: 28px 0;
}

.landing-benefits li {
    display: flex;
    gap: 12px;
    color: #446075;
    line-height: 1.5;
}

.landing-benefits .icon {
    color: #1f6f7f;
    flex-shrink: 0;
    margin-top: 3px;
}

.landing-benefits strong {
    display: block;
    color: #153b64;
    margin-bottom: 3px;
}

.landing-close {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    padding: 32px;
    border-radius: 16px;
    border: 1px solid #1f6f7f26;
    border-left: 4px solid #2db3a3;
    background: #f5f9fa;
}

.landing-close p:last-child {
    max-width: 54ch;
    margin: 16px 0 0;
}

.landing-close-actions {
    display: grid;
    gap: 12px;
}

/* La trama se acerca al borde de la foto cuando disminuye el margen lateral. */
@media (max-width: 1244px) {
    .landing-hero-pattern {
        right: -10px;
    }
}

@media (max-width: 1000px) {
    .landing-service-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 780px) {
    .landing-hero, .landing-budget {
        grid-template-columns: minmax(0, 1fr);
    }

    .landing-hero {
        gap: 28px;
    }

    /* Al apilar la foto, la trama queda separada de los enlaces y del texto de cuenta. */
    .landing-visual-media {
        padding-top: 20px;
    }

    /* La firma conserva el mínimo de lectura del eslogan en pantallas angostas. */
    .landing-brand-signature {
        width: min(200px, 100%);
    }

    h1 {
        font-size: clamp(1.75rem, 8.25vw, 2.25rem);
    }

    .landing-visual img {
        aspect-ratio: 3 / 2;
    }

    .landing-steps {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
    }

    .step-top {
        margin-bottom: 12px;
    }

    .landing-close {
        padding: 24px;
    }
}

/* En móvil la esquina respeta el margen lateral para evitar desbordamientos. */
@media (max-width: 540px) {
    .landing-hero-pattern {
        top: -24px;
    }

    .landing-service-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .landing-close-actions {
        width: 100%;
    }

    .landing-actions {
        gap: 16px;
    }
}
</style>
