<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FlashMessages from '../Components/Notifications/FlashMessages.vue';
import SiteHeader from '../Components/Navigation/SiteHeader.vue';
import SiteFooter from '../Components/Navigation/SiteFooter.vue';
import { useNavigationContext } from '../support/useNavigationContext';
import WorkspaceNavigation from '../Components/Navigation/WorkspaceNavigation.vue';
import I from '../Components/UI/AppIcon.vue';
import { demoNotice } from '../support/synaux';
// El navbar mantiene los enlaces públicos; el panel lateral organiza el área privada de la sesión.
defineProps({ homeUrl: { type: String, default: '/' }, platformUrl: { type: String, default: '/plataforma' }, year: { type: Number, default: 2026 }, landing: Boolean });
const page = usePage();
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
const { area, nav, hasWorkspace, navbarRole, accountName, currentSection, adminView, workspaceTitle, showAreaSwitch } = useNavigationContext();
watch(() => page.url, () => { workspaceOpen.value = false; });
</script>
<template>
    <v-app class="synaux-app" :data-workspace-role="hasWorkspace ? area : undefined" :data-navbar-role="navbarRole || undefined" :style="{ '--navbar-height': headerHeight + 'px', '--workspace-bar-height': workspaceBarHeight + 'px' }">
        <!-- El encabezado fijo conserva su espacio mediante una altura medida, sin tapar el contenido. -->
        <div ref="fixedHeader" class="fixed-header">
            <SiteHeader :area="area" :admin-view="adminView" :has-workspace="hasWorkspace" :show-area-switch="showAreaSwitch" />
        </div>
        <div class="navbar-space" aria-hidden="true"></div>
        <!-- El componente compartido anuncia resultados y errores sin perderse al desplazar la página. -->
        <FlashMessages :message="demoNotice || $page.props.notice" :errors="$page.props.errors" :page-key="$page.url" @close-message="demoNotice = ''" />
        <!-- El panel es adicional al sitio y solo acompaña las pantallas privadas. -->
        <div v-if="hasWorkspace" ref="workspaceBar" class="workspace-mobile-bar"><span>{{ workspaceTitle }}</span><button ref="workspaceToggle" type="button" :aria-expanded="workspaceOpen" aria-controls="workspace-mobile-panel" @click="workspaceOpen = true"><I name="menu" />Menú del panel</button></div>
        <div :class="['workspace-layout', { 'with-workspace': hasWorkspace }]">
            <aside v-if="hasWorkspace" class="workspace-sidebar"><div class="workspace-sidebar-sticky"><WorkspaceNavigation :items="nav" :area="area" :current="currentSection" :account-name="accountName" :title="workspaceTitle" /></div></aside>
            <v-main id="contenido" class="workspace-content" tabindex="-1"><slot /></v-main>
        </div>
        <!-- Vuetify mantiene el foco en el panel móvil y permite cerrarlo con Escape o fuera de él. -->
        <v-dialog v-if="hasWorkspace" v-model="workspaceOpen" class="workspace-mobile-dialog" max-width="340" @after-leave="restoreWorkspaceFocus" aria-labelledby="workspace-mobile-title">
            <div id="workspace-mobile-panel" class="synaux-app workspace-mobile-panel"><div class="workspace-mobile-heading"><strong id="workspace-mobile-title">Menú del panel</strong><button type="button" aria-label="Cerrar menú del panel" @click="workspaceOpen = false"><I name="close" /></button></div><WorkspaceNavigation :items="nav" :area="area" :current="currentSection" :account-name="accountName" :title="workspaceTitle" @navigate="workspaceOpen = false" /></div>
        </v-dialog>
        <SiteFooter :year="year" :landing="landing" :has-workspace="hasWorkspace" />
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
.synaux-app[data-navbar-role] :deep(.header) { border-bottom: 3px solid var(--navbar-accent); }
.fixed-header { position: fixed; inset: 0 0 auto; z-index: 100; background: #fff; }
.navbar-space { flex: 0 0 var(--navbar-height); height: var(--navbar-height); }
.workspace-content { scroll-margin-top: calc(var(--navbar-height) + 24px); }
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
@media (max-width: 1100px) {
    .with-workspace { display: block; }
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
