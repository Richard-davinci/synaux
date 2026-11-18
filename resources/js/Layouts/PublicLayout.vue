<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BrandLogo from '../Components/BrandLogo.vue';
import SynauxNotifications from '../Components/SynauxNotifications.vue';
// La campana de actividad persiste separada de los avisos transitorios de formularios.
import SynauxNotificationCenter from '../Components/SynauxNotificationCenter.vue';
import WorkspaceNavigation from '../Components/WorkspaceNavigation.vue';
import I from '../Components/SynauxIcon.vue';
// El navbar usa el SVG horizontal entregado por el usuario, sin modificar sus trazos ni colores.
import navbarLogo from '../../images/brand/synaux-horizontal-sin-slogan-color.svg';
import SynauxButton from '../Components/SynauxButton.vue';
import { demoNotice, referenceHref } from '../support/synaux';
// El navbar mantiene los enlaces públicos; el panel lateral organiza el área privada de la sesión.
defineProps({ homeUrl: { type: String, default: '/' }, platformUrl: { type: String, default: '/plataforma' }, year: { type: Number, default: 2026 }, landing: Boolean });
const page = usePage();
const mobile = ref(false);
const workspaceOpen = ref(false);
const workspaceToggle = ref(null);
// Se mide el encabezado real para compensar cambios de ancho, menú móvil y barra de referencia.
const fixedHeader = ref(null);
const headerHeight = ref(76);
// La altura real del menú del panel permite ubicar también los pasos fijos sin superponer barras en móvil.
const workspaceBar = ref(null);
const workspaceBarHeight = ref(0);
let headerObserver;
onMounted(() => {
    headerObserver = new ResizeObserver(entries => {
        for (const entry of entries) {
            const height = entry.target.getBoundingClientRect().height;
            if (entry.target === fixedHeader.value) headerHeight.value = height;
            if (entry.target === workspaceBar.value) workspaceBarHeight.value = height;
        }
    });
    if (fixedHeader.value) headerObserver.observe(fixedHeader.value);
    if (workspaceBar.value) headerObserver.observe(workspaceBar.value);
});
onBeforeUnmount(() => headerObserver?.disconnect());
watch(workspaceBar, (bar, previous) => {
    if (previous) headerObserver?.unobserve(previous);
    workspaceBarHeight.value = bar?.getBoundingClientRect().height ?? 0;
    if (bar) headerObserver?.observe(bar);
});
function restoreWorkspaceFocus() { workspaceToggle.value?.focus(); }
const area = computed(() => page.props.area ?? (page.url.startsWith('/gestion') || page.url.startsWith('/admin') ? 'gestion' : page.url.startsWith('/profesional') ? 'profesional' : page.url.startsWith('/cliente') ? 'cliente' : 'public'));
const publicNav = [['Catálogo', '/plataforma'], ['Portfolio', '/portfolio'], ['Nosotros', '/nosotros'], ['Contacto', '/contacto']];
const allNav = computed(() => area.value === 'cliente' ? [['Resumen', '/cliente'], ['Estimaciones', '/cliente/estimaciones'], ['Solicitudes', '/cliente/solicitudes'], ['Publicaciones', '/cliente/publicaciones'], ['Propuestas', '/cliente/propuestas'], ['Presupuestos', '/cliente/presupuestos'], ['Proyectos', '/cliente/proyectos']]
    : area.value === 'profesional' ? [['Resumen', '/profesional'], ['Oportunidades', '/oportunidades'], ['Mis propuestas', '/profesional/propuestas'], ['Invitaciones', '/profesional/invitaciones'], ['Presupuestos', '/profesional/presupuestos'], ['Proyectos', '/profesional/proyectos'], ['Mi perfil', '/profesional/perfil']]
    : area.value === 'gestion' ? [['Resumen', '/gestion'], ['Solicitudes', '/gestion/solicitudes'], ['Presupuestos', '/gestion/presupuestos'], ['Proyectos', '/gestion/proyectos'], ['Consultas', '/gestion/contactos'], ['Portfolio', '/gestion/portfolio']]
    : publicNav);
// Las entradas del marketplace solo aparecen para quien tiene acceso a la segunda etapa.
const stageTwoPaths = ['/cliente/publicaciones', '/cliente/propuestas', '/cliente/invitaciones'];
// La navegación habitual del cliente muestra la etapa 1 incluso para una cuenta administradora.
// Los enlaces del marketplace aparecen al abrir expresamente una de sus secciones desde el panel.
const inStageTwoClientArea = computed(() => stageTwoPaths.includes(page.url.split('?')[0]));
const nav = computed(() => allNav.value.filter(([, href]) =>
    !stageTwoPaths.includes(href) || (page.props.stageTwo && inStageTwoClientArea.value)
));

// El contexto de trabajo identifica también los detalles y formularios abiertos desde cada área.
const hasWorkspace = computed(() => area.value !== 'public' && !!(page.props.auth || page.props.preview));
// La línea del navbar recibe el rol de la sesión en páginas reales; la galería usa el área mostrada solo como referencia visual.
const navbarRole = computed(() => {
    if (page.props.preview) return hasWorkspace.value ? (area.value === 'gestion' ? 'admin' : area.value) : null;
    if (page.props.auth?.is_admin) return 'admin';
    return page.props.auth ? (area.value === 'profesional' ? 'profesional' : 'cliente') : null;
});
const accountName = computed(() => page.props.auth?.name ?? (page.props.preview ? 'Cuenta de ejemplo' : ''));
const currentSection = computed(() => {
    if (page.url.split('?')[0].split('#')[0] === '/mi-perfil' || ['cuenta-admin', 'cuenta-cliente'].includes(page.props.referenceKey)) return 'mi-perfil';
    if (page.props.screen === 'admin' || page.url.startsWith('/admin')) return 'administracion';
    return ({ solicitud: 'solicitudes', publicar: 'solicitudes', presupuesto: 'presupuestos', 'editor-presupuesto': 'presupuestos',
        proyecto: 'proyectos', estimacion: 'estimaciones', 'editor-trabajo': 'portfolio' })[page.props.screen]
        ?? (page.props.section || 'resumen');
});
// Los dos destinos superiores separan gestión de administración; la sección actual determina el enlace destacado y el título móvil.
const adminView = computed(() => area.value === 'gestion' && currentSection.value === 'administracion');
const workspaceTitle = computed(() => adminView.value ? 'Administración' : area.value === 'gestion' ? 'Panel de gestión' : area.value === 'profesional' ? 'Área profesional' : 'Mi espacio');
// Los enlaces de áreas aparecen para administradores reales y, con rutas aisladas, en las pantallas de gestión de la galería.
const showAreaSwitch = computed(() => page.props.preview ? area.value === 'gestion' : !!page.props.auth?.is_admin);
watch(() => page.url, () => { mobile.value = false; workspaceOpen.value = false; });
</script>
<template>
    <v-app class="synaux-app" :data-workspace-role="hasWorkspace ? area : undefined" :data-navbar-role="navbarRole || undefined" :style="{ '--navbar-height': headerHeight + 'px', '--workspace-bar-height': workspaceBarHeight + 'px' }">
        <!-- El encabezado fijo conserva su espacio mediante una altura medida, sin tapar el contenido. -->
        <div ref="fixedHeader" class="fixed-header">
        <!-- En la referencia se puede revisar cada estado sin modificar registros ni conceder permisos. -->
        <div v-if="$page.props.preview" class="reference-bar"><div class="shell"><span>Prototipo · Datos de ejemplo</span><label>Ver pantalla<select :value="$page.props.referenceKey" aria-label="Pantalla del prototipo" @change="$inertia.get(`/prototipo/${$event.target.value}`)"><option v-for="item in $page.props.referenceScreens" :key="item.key" :value="item.key">{{ item.name }}</option></select></label><a href="/plataforma">Abrir aplicación</a></div></div>
        <a class="skip-link" href="#contenido">Saltar al contenido</a>
        <!-- Las dimensiones nativas del SVG reservan su proporción antes de cargar el logo horizontal. -->
        <header class="header"><a :href="referenceHref('/', $page.props)" class="logo-button" aria-label="synaux: inicio"><img :src="navbarLogo" class="brand-logo" width="405" height="140" alt="synaux"></a><button class="mobile-toggle" :aria-expanded="mobile" aria-label="Abrir navegación" @click="mobile = !mobile">☰</button>
            <nav :class="['main-nav', { 'mobile-open': mobile }]" aria-label="Navegación principal">
                <a v-for="[label, href] in publicNav" :key="href" :href="referenceHref(href, $page.props)" :aria-current="$page.url.split('?')[0] === href ? 'page' : undefined">{{ label }}</a>
                <!-- En móvil, los accesos de visitantes acompañan al menú desplegado. -->
                <div v-if="!$page.props.auth && !$page.props.preview" class="nav-actions mobile-auth"><SynauxButton compact href="/ingresar" variant="ghost">Ingresar</SynauxButton><SynauxButton compact href="/crear-cuenta">Crear cuenta</SynauxButton></div>
                <!-- La cuenta administradora puede cambiar de área también desde el menú superior móvil. -->
                <div v-if="showAreaSwitch" class="mobile-area-links"><a :href="referenceHref('/gestion', $page.props)" :aria-current="area === 'gestion' && !adminView ? 'page' : undefined">Ir al panel</a><a :href="referenceHref('/admin/servicios', $page.props)" :aria-current="adminView ? 'page' : undefined">Ir a administración</a></div>
            </nav>
            <!-- Cuenta y notificaciones comparten un grupo alineado al extremo derecho. -->
            <div v-if="!$page.props.preview || showAreaSwitch" class="header-account" :class="{ 'guest-account': !$page.props.auth && !$page.props.preview }">
                <!-- La galería convierte estos destinos en referencias; en la sesión real las rutas mantienen autorización del servidor. -->
                <nav v-if="showAreaSwitch" class="header-area-links" aria-label="Áreas de la cuenta"><a :href="referenceHref('/gestion', $page.props)" :aria-current="area === 'gestion' && !adminView ? 'page' : undefined">Panel</a><a :href="referenceHref('/admin/servicios', $page.props)" :aria-current="adminView ? 'page' : undefined">Administración</a></nav>
                <template v-if="!$page.props.preview && !$page.props.auth"><SynauxButton compact href="/ingresar" variant="ghost">Ingresar</SynauxButton><SynauxButton compact href="/crear-cuenta">Crear cuenta</SynauxButton></template>
                <template v-else-if="!$page.props.preview"><SynauxButton v-if="!hasWorkspace && !$page.props.auth.is_admin" compact class="panel-access" href="/cliente">Ir a mi panel</SynauxButton><SynauxNotificationCenter /></template>
            </div>
        </header>
        </div>
        <div class="navbar-space" aria-hidden="true"></div>
        <!-- El componente compartido anuncia resultados y errores sin perderse al desplazar la página. -->
        <SynauxNotifications :message="demoNotice || $page.props.notice" :errors="$page.props.errors" :page-key="$page.url" @close-message="demoNotice = ''" />
        <!-- El panel es adicional al sitio y solo acompaña las pantallas privadas. -->
        <div v-if="hasWorkspace" ref="workspaceBar" class="workspace-mobile-bar"><span>{{ workspaceTitle }}</span><button ref="workspaceToggle" type="button" :aria-expanded="workspaceOpen" aria-controls="workspace-mobile-panel" @click="workspaceOpen = true"><I name="menu" />Menú del panel</button></div>
        <div :class="['workspace-layout', { 'with-workspace': hasWorkspace }]">
            <aside v-if="hasWorkspace" class="workspace-sidebar"><div class="workspace-sidebar-sticky"><WorkspaceNavigation :items="nav" :area="area" :current="currentSection" :account-name="accountName" /></div></aside>
            <v-main id="contenido" class="workspace-content" tabindex="-1"><slot /></v-main>
        </div>
        <!-- Vuetify mantiene el foco en el panel móvil y permite cerrarlo con Escape o fuera de él. -->
        <v-dialog v-if="hasWorkspace" v-model="workspaceOpen" class="workspace-mobile-dialog" max-width="340" @after-leave="restoreWorkspaceFocus" aria-labelledby="workspace-mobile-title">
            <div id="workspace-mobile-panel" class="synaux-app workspace-mobile-panel"><div class="workspace-mobile-heading"><strong id="workspace-mobile-title">Menú del panel</strong><button type="button" aria-label="Cerrar menú del panel" @click="workspaceOpen = false"><I name="close" /></button></div><WorkspaceNavigation :items="nav" :area="area" :current="currentSection" :account-name="accountName" @navigate="workspaceOpen = false" /></div>
        </v-dialog>
        <!-- La firma de cierre mantiene el fondo blanco del diseño y el original a color con eslogan. -->
        <!-- El pie de la landing aplica la guía de marca sobre el diseño compartido. -->
        <!-- Todos los pies de navegación comparten la trama transparente y su escala de marca. -->
        <footer class="site-footer" :class="{ 'landing-footer': landing, 'workspace-footer': hasWorkspace }"><div class="synaux-brand-pattern site-footer-pattern" aria-hidden="true"></div><div class="shell footer-grid"><a :href="referenceHref('/', $page.props)" aria-label="synaux: presentación"><BrandLogo /></a><div class="footer-copy"><strong>Conexión, transparencia y crecimiento digital.</strong><p>© {{ year }} synaux.</p></div><nav aria-label="Navegación del pie"><span class="footer-nav-title">Explorá</span><div class="footer-nav-links"><a v-for="[label, href] in publicNav" :key="href" :href="referenceHref(href, $page.props)">{{ label }}</a></div></nav></div></footer>
    </v-app>
</template>

<style scoped>
/* El navbar queda sobre la página; la compensación usa su altura real incluso en el prototipo. */
/* El panel conserva su acento y la línea del navbar identifica la sesión sin recolorear el logo ni alterar el acceso. */
.synaux-app[data-workspace-role='cliente'] { --workspace-accent: #153b64; }
.synaux-app[data-workspace-role='profesional'] { --workspace-accent: #2db3a3; }
.synaux-app[data-workspace-role='gestion'] { --workspace-accent: #2db3a3; }
/* Azul para cliente y turquesa para administración o profesionales; el visitante mantiene el borde discreto del sitio. */
.synaux-app[data-navbar-role='cliente'] { --navbar-accent: #153b64; }
.synaux-app[data-navbar-role='admin'], .synaux-app[data-navbar-role='profesional'] { --navbar-accent: #2db3a3; }
.synaux-app[data-navbar-role] .header { border-bottom: 3px solid var(--navbar-accent); }
.fixed-header { position: fixed; inset: 0 0 auto; z-index: 100; background: #fff; }
.navbar-space { flex: 0 0 var(--navbar-height); height: var(--navbar-height); }
.workspace-content { scroll-margin-top: calc(var(--navbar-height) + 24px); }
/* El pie ocupa el mismo ancho del contenido para no quedar detrás del panel fijo. */
.workspace-footer { margin-left: 240px; }
/* El contenido usa el ancho restante del panel; la landing y el catálogo conservan su composición pública. */
.workspace-layout { flex: 1 1 auto; min-width: 0; }
.with-workspace { display: grid; grid-template-columns: 240px minmax(0, 1fr); align-items: start; }
.workspace-content { min-width: 0; width: 100%; }
.workspace-sidebar { align-self: stretch; min-width: 0; background: #f5f9fa; border-right: 1px solid #1f6f7f22; }
/* El panel ocupa el alto bajo el navbar; la navegación interna conserva su pie de cuenta visible. */
.workspace-sidebar-sticky { position: fixed; top: var(--navbar-height); bottom: 0; left: 0; width: 240px; background: #f5f9fa; border-right: 1px solid #1f6f7f22; z-index: 40; }
.with-workspace :deep(.page) { padding-block: 40px 72px; }
.with-workspace :deep(.admin-layout) { grid-template-columns: minmax(0, 1fr); }
.workspace-mobile-bar { display: none; }
/* Tres bloques mantienen el menú centrado independientemente del ancho del logo o de las acciones. */
.header { display: grid; grid-template-columns: minmax(150px, 1fr) auto minmax(150px, 1fr); }
.header .logo-button { grid-column: 1; grid-row: 1; justify-self: start; }
.header .main-nav { grid-column: 2; grid-row: 1; justify-self: center; }
.header-account { grid-column: 3; grid-row: 1; justify-self: end; display: flex; align-items: center; gap: 12px; }
.header-account :deep(.panel-access) { box-shadow: none; }
/* Los accesos de la cuenta administradora usan la misma línea turquesa para señalar el área abierta. */
.header-area-links { display: flex; align-items: center; gap: 4px; }
.header-area-links a { display: inline-flex; align-items: center; min-height: 40px; padding: 8px 10px; border-radius: 8px; color: #153b64; font-weight: 700; text-decoration: none; white-space: nowrap; }
.header-area-links a:hover { background: #2db3a31a; }
.header-area-links a[aria-current='page'] { box-shadow: inset 0 -3px 0 #2db3a3; }
/* El cambio de área conserva una referencia visible para navegación con teclado en escritorio y móvil. */
.header-area-links a:focus-visible, .mobile-area-links a:focus-visible { outline: 2px solid #2db3a3; outline-offset: 2px; }
.header .mobile-toggle, .header .mobile-auth, .header .mobile-area-links { display: none; }
@media (max-width: 900px) {
    .header { display: flex; flex-wrap: wrap; gap: 12px; }
    .header .logo-button { flex: 1 1 0; min-width: 0; }
    .header .logo-button img { max-width: 100%; }
    .header-account { order: 1; gap: 8px; }
    .header .header-area-links { display: none; }
    .header .guest-account { display: none; }
    .header .mobile-toggle { order: 2; display: block; margin-left: 0; }
    .header .main-nav { order: 3; top: 100%; }
    .header .main-nav.mobile-open { display: flex; }
    .header .mobile-auth { display: grid; }
    /* En pantallas angostas el cambio de área aparece dentro del menú desplegable y mantiene objetivos táctiles amplios. */
    .header .mobile-area-links { display: grid; gap: 4px; padding-top: 12px; border-top: 1px solid #2db3a344; }
    .mobile-area-links a { display: flex; align-items: center; min-height: 44px; padding: 10px 12px; color: #153b64; font-weight: 700; text-decoration: none; }
    .mobile-area-links a[aria-current='page'] { box-shadow: inset 3px 0 0 #2db3a3; background: #2db3a31a; }
}
@media (max-width: 1100px) {
    .with-workspace { display: block; }
    .workspace-footer { margin-left: 0; }
    .workspace-sidebar { display: none; }
    .workspace-mobile-bar { position: sticky; top: var(--navbar-height); z-index: 50; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 24px; background: #f5f9fa; border-bottom: 1px solid #1f6f7f22; color: #153b64; font-weight: 700; }
    .workspace-mobile-bar button { display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 10px 12px; border: 1px solid #1f6f7f44; border-radius: 8px; color: #153b64; background: #fff; cursor: pointer; }
    .workspace-mobile-bar button:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 3px; }
}
@media (max-width: 600px) { .workspace-mobile-bar { padding-inline: 16px; font-size: .9rem; } }
</style>
<style>
/* El diálogo móvil se presenta como un panel lateral y conserva el foco y el cierre de Vuetify. */
.workspace-mobile-dialog .v-overlay__content { position: fixed; top: 0; left: 0; margin: 0 !important; width: min(340px, calc(100vw - 24px)) !important; max-height: 100dvh !important; height: 100dvh; }
/* En móvil, la navegación ocupa el alto restante debajo del encabezado hasta el pie. */
.workspace-mobile-panel { display: flex; flex-direction: column; height: 100%; overflow: hidden; background: #f5f9fa; color: #153b64; font-family: 'Nunito', sans-serif; }
.workspace-mobile-heading { flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid #1f6f7f33; }
.workspace-mobile-heading button { display: grid; place-items: center; width: 44px; height: 44px; border: 0; border-radius: 8px; background: transparent; color: #153b64; cursor: pointer; }
.workspace-mobile-heading button:focus-visible { outline: 3px solid #1f6f7f; }
</style>
