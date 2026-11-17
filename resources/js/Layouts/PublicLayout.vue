<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BrandLogo from '../Components/BrandLogo.vue';
// El navbar usa el PNG horizontal a color sin eslogan de Recursos finales.
import navbarLogo from '../../images/brand/synaux-horizontal-sin-slogan-color.png';
import SynauxButton from '../Components/SynauxButton.vue';
import { demoNotice, referenceHref } from '../support/synaux';
// El encabezado cambia de área según la sesión real; la galería usa datos aislados de referencia.
defineProps({ homeUrl: { type: String, default: '/' }, platformUrl: { type: String, default: '/plataforma' }, year: { type: Number, default: 2026 }, landing: Boolean });
const page = usePage();
const mobile = ref(false);
const area = computed(() => page.props.area ?? (page.url.startsWith('/gestion') || page.url.startsWith('/admin') ? 'gestion' : page.url.startsWith('/profesional') ? 'profesional' : page.url.startsWith('/cliente') ? 'cliente' : 'public'));
const publicNav = [['Catálogo', '/plataforma'], ['Portfolio', '/portfolio'], ['Nosotros', '/nosotros'], ['Contacto', '/contacto']];
const nav = computed(() => area.value === 'cliente' ? [['Resumen', '/cliente'], ['Solicitudes', '/cliente/solicitudes'], ['Publicaciones', '/cliente/publicaciones'], ['Propuestas', '/cliente/propuestas'], ['Presupuestos', '/cliente/presupuestos'], ['Proyectos', '/cliente/proyectos']]
    : area.value === 'profesional' ? [['Resumen', '/profesional'], ['Oportunidades', '/oportunidades'], ['Mis propuestas', '/profesional/propuestas'], ['Invitaciones', '/profesional/invitaciones'], ['Presupuestos', '/profesional/presupuestos'], ['Proyectos', '/profesional/proyectos'], ['Mi perfil', '/profesional/perfil']]
    : area.value === 'gestion' ? [['Resumen', '/gestion'], ['Solicitudes', '/gestion/solicitudes'], ['Presupuestos', '/gestion/presupuestos'], ['Proyectos', '/gestion/proyectos'], ['Portfolio', '/gestion/portfolio'], ['Administración', '/admin/servicios']]
    : publicNav);
</script>
<template>
    <v-app class="synaux-app">
        <!-- En la referencia se puede revisar cada estado sin modificar registros ni conceder permisos. -->
        <div v-if="$page.props.preview" class="reference-bar"><div class="shell"><span>Prototipo · Datos de ejemplo</span><label>Ver pantalla<select :value="$page.props.referenceKey" aria-label="Pantalla del prototipo" @change="$inertia.get(`/prototipo/${$event.target.value}`)"><option v-for="item in $page.props.referenceScreens" :key="item.key" :value="item.key">{{ item.name }}</option></select></label><a href="/plataforma">Abrir aplicación</a></div></div>
        <div v-else-if="$page.props.auth" class="account-bar"><div class="shell"><span>{{ $page.props.auth.name }}</span><nav aria-label="Áreas de mi cuenta"><a href="/cliente">Cliente</a><a href="/profesional">Profesional</a><a v-if="$page.props.auth.is_admin" href="/gestion">Gestión</a><a href="/plataforma">Sitio público</a><button @click="$inertia.post('/salir')">Salir</button></nav></div></div>
        <a class="skip-link" href="#contenido">Saltar al contenido</a>
        <!-- Las dimensiones nativas del PNG reservan el espacio del logo en el encabezado. -->
        <header class="header"><a :href="referenceHref(landing ? '/' : '/plataforma', $page.props)" class="logo-button" aria-label="Synaux: inicio"><img :src="navbarLogo" class="brand-logo" width="2000" height="423" alt="Synaux"></a><button class="mobile-toggle" :aria-expanded="mobile" aria-label="Abrir navegación" @click="mobile = !mobile">☰</button>
            <nav :class="['main-nav', { 'mobile-open': mobile }]" aria-label="Navegación principal">
                <template v-if="landing"><a href="#que-es-synaux">La propuesta</a><a href="#como-funciona">Cómo funciona</a><SynauxButton href="/plataforma">Entrar a Synaux</SynauxButton></template>
                <template v-else><a v-for="[label, href] in nav" :key="href" :href="referenceHref(href, $page.props)" :aria-current="$page.url.split('?')[0] === href ? 'page' : undefined">{{ label }}</a><div v-if="area === 'public'" class="nav-actions"><template v-if="!$page.props.auth"><SynauxButton href="/ingresar" variant="ghost">Ingresar</SynauxButton><SynauxButton href="/crear-cuenta">Crear cuenta</SynauxButton></template><SynauxButton v-else href="/cliente">Mi cuenta</SynauxButton></div></template>
            </nav>
        </header>
        <!-- El aviso de resultado y los errores de servidor son visibles también para lectores de pantalla. -->
        <div v-if="$page.props.notice || demoNotice" class="shell notice" role="status"><p>{{ demoNotice || $page.props.notice }}</p><button v-if="demoNotice" @click="demoNotice = ''" aria-label="Cerrar aviso">×</button></div>
        <div v-if="Object.keys($page.props.errors ?? {}).length" class="shell notice error-summary" role="alert"><div><strong>Revisá los datos antes de continuar.</strong><p v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</p></div></div>
        <v-main id="contenido" tabindex="-1"><slot /></v-main>
        <!-- La firma de cierre mantiene el fondo blanco del diseño y el original a color con eslogan. -->
        <!-- El pie de la landing aplica la guía de marca sobre el diseño compartido. -->
        <footer :class="{ 'landing-footer': landing }"><div class="shell footer-grid"><a :href="referenceHref('/', $page.props)" aria-label="Synaux: presentación"><BrandLogo /></a><div><strong>Conexión, transparencia y crecimiento digital.</strong><p>© {{ year }} Synaux.</p></div><nav aria-label="Navegación del pie"><a v-for="[label, href] in publicNav" :key="href" :href="referenceHref(href, $page.props)">{{ label }}</a></nav></div></footer>
    </v-app>
</template>
