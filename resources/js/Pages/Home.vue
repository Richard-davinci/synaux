<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import BrandLogo from '../Components/Brand/BrandLogo.vue';
import BrandPatternFragment from '../Components/Brand/BrandPatternFragment.vue';
import B from '../Components/UI/AppButton.vue';
import I from '../Components/UI/AppIcon.vue';
import BudgetDocument from '../Components/Presupuestos/DocumentoPresupuesto.vue';
import '../../css/panel.css';
import '../../css/ajustes-panel.css';

// Las cuatro áreas representan servicios de la primera etapa, sin anunciar el marketplace futuro.
const areas = [
    { icon: 'web', title: 'Desarrollo web', text: 'Presentá tu organización o lanzá una tienda con una experiencia adaptada a tu negocio.' },
    { icon: 'palette', title: 'Identidad visual', text: 'Construí una marca reconocible y mantené una imagen coherente en cada aplicación.' },
    { icon: 'megaphone', title: 'Marketing digital', text: 'Planificá campañas y contenidos con objetivos, canales y entregables definidos.' },
    { icon: 'layout', title: 'Diseño UX/UI', text: 'Organizá los recorridos y las pantallas de tu producto antes de desarrollarlo.' },
];
// El recorrido explica cuándo aparece una referencia orientativa y cuándo se confirma un presupuesto formal.
const steps = [
    { icon: 'folder', title: 'Elegí tu servicio', text: 'Explorá el catálogo y conocé qué incluye cada propuesta. Cuando haya una tarifa disponible, vas a ver su precio orientativo.' },
    { icon: 'edit', title: 'Prepará tu solicitud', text: 'Contanos el objetivo, agregá los servicios que necesitás y revisá el resumen. Ingresá a tu cuenta para confirmar el envío.' },
    { icon: 'check', title: 'Revisá el presupuesto', text: 'Recibí la propuesta con servicios, precio, plazos y condiciones. Podés descargarla y aceptar o rechazar la versión vigente.' },
];
// Los dos renglones ilustrativos reciben los conceptos e importes del ejemplo; la cantidad unitaria permite sumar sus precios sin depender del catálogo.
const exampleItems = [
    { description: 'Desarrollo Sitio Web Corporativo / Institucional', quantity: 1, unit_price: 676360 },
    { description: 'Desarrollo Manual de Identidad Visual', quantity: 1, unit_price: 537164 },
];
// El subtotal suma los renglones y el total aplica el IVA del ejemplo en centavos para conservar los decimales del documento.
const exampleSubtotal = exampleItems.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);
const exampleTaxRate = 21;
const exampleTotal = Math.round(exampleSubtotal * (1 + exampleTaxRate / 100) * 100) / 100;
// El documento es ilustrativo y no consulta ni modifica cuentas, solicitudes o precios publicados.
const exampleDate = new Date();
const exampleBudget = {
    title: 'Sitio e identidad para tu organización', version_number: 1, created_at: exampleDate.toISOString(),
    valid_until: new Date(exampleDate.getTime() + 30 * 86400000).toISOString().slice(0, 10),
    currency_code: 'ARS', tax_rate: exampleTaxRate, estimated_delivery_days: 30,
    items: exampleItems, subtotal: exampleSubtotal, total: exampleTotal,
    terms: 'Ejemplo ilustrativo. El alcance, la forma de pago y las revisiones se acuerdan en cada presupuesto.',
};
</script>

<template>
    <Head title="Servicios digitales con un alcance claro"><meta name="description" content="Explorá los servicios de synaux, prepará tu solicitud y revisá tu presupuesto desde un mismo lugar."></Head>
    <PublicLayout landing>
        <!-- La marca principal se presenta sobre blanco, integrada a la foto sin alterar el SVG original. -->
        <section class="landing-hero shell" aria-labelledby="landing-title">
            <div class="landing-hero-copy"><p class="eyebrow">Diseño · Desarrollo · Estrategia</p><h1 id="landing-title">De tu idea a un <em>proyecto digital.</em></h1><p class="landing-lead">Explorá servicios digitales, prepará tu solicitud y revisá tu presupuesto antes de avanzar.</p><div class="landing-actions"><B href="/plataforma" arrow>Explorar servicios</B><a href="#como-funciona">Cómo funciona</a></div><p class="landing-support">Podés empezar sin registrarte. Te pedimos una cuenta al enviar tu solicitud.</p></div>
            <div class="landing-visual-wrap"><BrandPatternFragment class="landing-hero-pattern" /><figure class="landing-visual"><img src="/images/landing/colaboracion-ricardo-catalogo.webp" alt="Colaboración en el diseño de un proyecto digital." width="1536" height="1024" fetchpriority="high"></figure><div class="landing-brand-signature"><BrandLogo /></div></div>
        </section>
        <!-- Las áreas orientan al catálogo sin repetir su listado completo ni prometer servicios futuros. -->
        <section class="landing-services" aria-labelledby="services-title"><div class="shell landing-section"><div class="landing-section-heading"><div><p class="eyebrow">Qué necesitás resolver</p><h2 id="services-title">Servicios que acompañan tu idea</h2></div><B compact href="/plataforma" variant="secondary" arrow>Ver catálogo</B></div><div class="landing-service-grid"><article v-for="area in areas" :key="area.title"><I :name="area.icon" class="landing-service-icon" /><h3>{{ area.title }}</h3><p>{{ area.text }}</p></article></div></div></section>
        <!-- El proceso usa tres columnas equilibradas y se apila en móvil sin fotografías que generen vacíos. -->
        <section id="como-funciona" class="shell landing-section landing-process" aria-labelledby="process-title"><div class="landing-section-heading"><div><p class="eyebrow">Cómo funciona</p><h2 id="process-title">Un recorrido, de principio a fin</h2></div></div><ol class="landing-steps"><li v-for="(step,index) in steps" :key="step.title"><div class="step-top"><span aria-hidden="true">0{{ index + 1 }}</span><I :name="step.icon" /></div><h3>{{ step.title }}</h3><p>{{ step.text }}</p></li></ol></section>
        <!-- La muestra utiliza el documento real de la aplicación con datos ficticios identificados como ejemplo. -->
        <section class="landing-budget-section" aria-labelledby="budget-title"><div class="shell landing-section landing-budget"><div><p class="eyebrow">Antes de confirmar</p><h2 id="budget-title">Una propuesta que podés revisar</h2><p class="landing-lead">El precio orientativo te ayuda a empezar. El presupuesto formal define qué se entrega, cuánto cuesta y bajo qué condiciones.</p><ul class="landing-benefits"><li><I name="file" /><span><strong>Servicios y precios desglosados</strong>Conceptos, cantidades, subtotal e IVA cuando corresponda.</span></li><li><I name="clock" /><span><strong>Plazos y condiciones visibles</strong>Vigencia de la propuesta y entregas acordadas.</span></li><li><I name="user" /><span><strong>El documento en tu cuenta</strong>Consultalo, descargalo y revisalo antes de decidir.</span></li></ul><B v-if="!$page.props.auth?.is_admin" compact href="/solicitudes/nueva" arrow>Preparar mi solicitud</B></div><div class="landing-budget-example"><p class="example-label">Documento de ejemplo · Importes ilustrativos</p><div class="landing-budget-preview"><BudgetDocument compact :budget="exampleBudget" :client="{name:'Cliente de ejemplo',company:'Tu organización'}" :issuer="{name:'synaux'}" /></div></div></div></section>
        <!-- El cierre ofrece una siguiente acción concreta y deja al footer su función de navegación y firma. -->
        <section class="shell landing-section"><div class="landing-close"><div><p class="eyebrow">Tu próximo paso</p><h2>Contanos qué necesitás.</h2><p>Empezá por un servicio o consultanos si todavía estás definiendo tu proyecto.</p></div><div class="landing-close-actions"><B v-if="!$page.props.auth?.is_admin" href="/solicitudes/nueva" arrow>Comenzar mi solicitud</B><B href="/contacto" variant="secondary">Hacer una consulta</B></div></div></section>
    </PublicLayout>
</template>

<style scoped>
/* La decoración del hero comparte el original amplio y su escala con el borde del footer. */
.landing-hero, .landing-section { font-family: 'Nunito', sans-serif; }
h1, h2, h3 { color: #153b64; font-family: 'Space Grotesk', sans-serif; font-weight: 700; text-wrap: balance; }
.eyebrow { color: #1f6f7f; font-size: .8rem; font-weight: 700; letter-spacing: .1em; margin-bottom: 12px; }
p { color: #446075; text-wrap: pretty; line-height: 1.6; }
.landing-hero { display: grid; grid-template-columns: minmax(0,1.1fr) minmax(0,1fr); gap: clamp(24px,5vw,64px); align-items: center; padding-block: clamp(32px,4vw,52px); }
h1 { font-size: clamp(2.5rem,4.7vw,4.25rem); line-height: 1.05; margin: 0 0 20px; max-width: 17ch; }
h1 em { color: #1f6f7f; font-style: normal; }
.landing-lead { max-width: 52ch; font-size: 1.075rem; margin-top: 20px; }
.landing-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 24px; margin-top: 24px; }
.landing-actions > a:last-child { color: #153b64; font-weight: 700; text-underline-offset: 4px; }
.landing-actions > a:last-child:hover { text-decoration: underline; }
.landing-support { font-size: .85rem; max-width: 50ch; margin: 16px 0 0; }
.landing-visual-wrap { position: relative; min-width: 0; padding-top: 20px; padding-bottom: 90px; }
/* La firma mantiene el tamaño del logo con menos margen blanco y deja libre la pantalla. */
.landing-brand-signature { position: absolute; bottom: 0; right: -12px; width: min(238px,80%); padding: 8px; background: #fff; border: 1px solid #1f6f7f0a; border-radius: 16px; box-shadow: 0 4px 16px #153b640a; }
.landing-brand-signature :deep(.brand-logo) { display: block; width: 100%; height: auto; }
/* El fragmento elimina piezas que no caben completas y deja visible la esquina derecha. */
.landing-hero-pattern { position: absolute; top: -32px; right: -32px; width: min(76%,380px); height: 180px; opacity: .4; pointer-events: none; }
.landing-visual { position: relative; margin: 0; border: 1px solid #1f6f7f22; border-radius: 20px; overflow: hidden; background: #fff; box-shadow: 0 6px 20px #153b6412; }
.landing-visual img { display: block; width: 100%; height: auto; aspect-ratio: 3 / 2; object-fit: cover; }
.landing-section { padding-block: clamp(40px,5vw,64px); }
.landing-section h2 { font-size: clamp(1.75rem,3vw,2.6rem); line-height: 1.15; margin: 0; max-width: 26ch; }
.landing-section h3 { font-size: 1.25rem; line-height: 1.25; margin: 0 0 8px; }
.landing-section-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
.landing-services, .landing-budget-section { background: #f5f9fa; border-block: 1px solid #1f6f7f14; }
.landing-service-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 16px; }
.landing-service-grid article { padding: 22px; border: 1px solid #1f6f7f22; border-top: 3px solid #2db3a3; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px #153b640a; }
.landing-service-grid p { font-size: .95rem; margin: 0; }
.landing-service-icon { width: 28px; height: 28px; color: #1f6f7f; margin-bottom: 20px; }
.landing-steps { list-style: none; display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 28px; padding: 0; margin: 0; counter-reset: landing-step; }
.landing-steps li { min-width: 0; padding-top: 16px; border-top: 2px solid #1f6f7f26; }
/* El número y el ícono de cada paso forman una sola referencia visual; los datos de steps mantienen su orden y estilo original. */
.step-top { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; color: #1f6f7f; }
.step-top span { font-family: 'Space Grotesk',sans-serif; font-size: 1.5rem; font-weight: 700; }
.landing-steps p { margin: 0; }
.landing-budget { display: grid; grid-template-columns: minmax(0,.9fr) minmax(0,1.1fr); gap: clamp(24px,5vw,64px); align-items: center; }
.landing-benefits { list-style: none; display: grid; gap: 20px; padding: 0; margin: 28px 0; }
.landing-benefits li { display: flex; gap: 12px; color: #446075; line-height: 1.5; }
.landing-benefits .icon { color: #1f6f7f; flex-shrink: 0; margin-top: 3px; }
.landing-benefits strong { display: block; color: #153b64; margin-bottom: 3px; }
.landing-budget-example { min-width: 0; padding: 12px; background: #fff; border: 1px solid #1f6f7f26; border-radius: 16px; box-shadow: 0 10px 28px #153b6412; }
.example-label { margin: 0; padding: 4px 8px 12px; font-size: .8rem; color: #446075; }
.landing-budget-preview { border-radius: 10px; }
.landing-close { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 28px; padding: 32px; border-radius: 16px; border: 1px solid #1f6f7f26; border-left: 4px solid #2db3a3; background: #f5f9fa; }
.landing-close p:last-child { max-width: 54ch; margin: 16px 0 0; }
.landing-close-actions { display: grid; gap: 12px; }
@media (max-width: 1000px) { .landing-service-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media (max-width: 780px) { .landing-hero, .landing-budget { grid-template-columns: minmax(0,1fr); } .landing-hero { gap: 28px; } h1 { max-width: 20ch; } .landing-visual img { aspect-ratio: 3 / 2; } .landing-steps { grid-template-columns: minmax(0,1fr); gap: 24px; } .step-top { margin-bottom: 12px; } .landing-close { padding: 24px; } }
/* En móvil la esquina respeta el margen lateral para evitar desbordamientos. */
@media (max-width: 540px) { .landing-hero-pattern { top: -24px; right: -10px; } .landing-visual-wrap { padding-bottom: 80px; } .landing-brand-signature { right: 0; width: min(218px,80%); padding: 8px; } .landing-service-grid { grid-template-columns: minmax(0,1fr); } .landing-close-actions { width: 100%; } .landing-actions { gap: 16px; } }
</style>
