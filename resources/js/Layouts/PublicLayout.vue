<script setup>
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {usePage} from '@inertiajs/vue3';
import FlashMessages from '../Components/Notifications/FlashMessages.vue';
import SiteHeader from '../Components/Navigation/SiteHeader.vue';
import SiteFooter from '../Components/Navigation/SiteFooter.vue';
import {useNavigationContext} from '../support/useNavigationContext';
import WorkspaceNavigation from '../Components/Navigation/WorkspaceNavigation.vue';
import MigasNavegacion from '../Components/Navigation/MigasNavegacion.vue';
import I from '../Components/UI/AppIcon.vue';
import {demoNotice} from '../support/prototype';
// El navbar mantiene los enlaces públicos; el panel lateral organiza el área privada de la sesión.
const props = defineProps({
    homeUrl: {type: String, default: '/'},
    platformUrl: {type: String, default: '/plataforma'},
    year: {type: Number, default: 2026},
    landing: Boolean
});
const page = usePage();
// La landing real empieza directamente con la marca; el prototipo conserva su selector de pantallas.
const showSiteHeader = computed(() => !props.landing || Boolean(page.props.preview));
const workspaceOpen = ref(false);
const anchoPanel = 340;
let accesoAlPanel;
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

function abrirPanel(event) {
    accesoAlPanel = event.currentTarget;
    workspaceOpen.value = true;
}

function restoreWorkspaceFocus() {
    accesoAlPanel?.focus();
}

const {
    area,
    nav,
    hasWorkspace,
    paginaDePanel,
    navbarRole,
    accountName,
    accountRole,
    currentSection,
    migasCliente,
    adminView,
    workspaceTitle,
    showAreaSwitch
} = useNavigationContext();

// El panel lateral pertenece a las páginas privadas. El menú de cuenta también se ofrece en el sitio público.
const mostrarPanel = computed(() => !props.landing && hasWorkspace.value && paginaDePanel.value);
watch(() => page.url, () => {
    workspaceOpen.value = false;
});
</script>
<template>
    <v-app class="synaux-app" :data-workspace-role="mostrarPanel ? area : undefined"
           :data-navbar-role="navbarRole || undefined"
           :style="{ '--navbar-height': (showSiteHeader ? headerHeight : 0) + 'px', '--workspace-bar-height': workspaceBarHeight + 'px', '--workspace-panel-width': anchoPanel + 'px' }">
        <!-- Las páginas con encabezado reservan su altura; la landing conserva el salto por teclado. -->
        <template v-if="showSiteHeader">
            <div ref="fixedHeader" class="fixed-header">
                <SiteHeader :gestion-active="paginaDePanel && area === 'gestion' && !adminView"
                            :admin-view="adminView" :show-area-switch="showAreaSwitch"
                            :account-name="hasWorkspace ? accountName : ''" :account-role="accountRole"/>
            </div>
            <div class="navbar-space" aria-hidden="true"></div>
        </template>
        <a v-else class="skip-link" href="#contenido">Saltar al contenido</a>
        <!-- El componente compartido anuncia resultados y errores sin perderse al desplazar la página. -->
        <FlashMessages :message="demoNotice || $page.props.notice" :errors="$page.props.errors" :page-key="$page.url"
                       @close-message="demoNotice = ''"/>
        <!-- El panel reserva espacio únicamente dentro del área privada. -->
        <div v-if="mostrarPanel" ref="workspaceBar" class="workspace-mobile-bar"><span>{{ workspaceTitle }}</span>
            <button type="button" :aria-expanded="workspaceOpen" aria-haspopup="dialog"
                    aria-controls="workspace-mobile-panel" @click="abrirPanel"><I name="menu"/>Menú del panel
            </button>
        </div>
        <div :class="['workspace-layout', { 'with-workspace': mostrarPanel }]">
            <aside v-if="mostrarPanel" class="workspace-sidebar">
                <div class="workspace-panel-surface workspace-sidebar-sticky">
                    <WorkspaceNavigation :items="nav" :area="area" :current="currentSection"
                                         :title="workspaceTitle"/>
                </div>
            </aside>
            <v-main id="contenido" class="workspace-content" tabindex="-1">
                <MigasNavegacion v-if="mostrarPanel && migasCliente.length" :niveles="migasCliente"/>
                <slot/>
            </v-main>
        </div>
        <!-- En pantallas privadas angostas, el mismo panel se abre desde el menú de la sección. -->
        <v-dialog v-if="mostrarPanel" v-model="workspaceOpen" class="workspace-mobile-dialog" :max-width="anchoPanel"
                  :style="{ '--navbar-height': headerHeight + 'px', '--workspace-panel-width': anchoPanel + 'px' }"
                  @after-leave="restoreWorkspaceFocus" aria-labelledby="workspace-mobile-title">
            <div id="workspace-mobile-panel" class="synaux-app workspace-panel-surface">
                <WorkspaceNavigation :items="nav" :area="area" :current="currentSection"
                                     :title="workspaceTitle" titulo-id="workspace-mobile-title" @navigate="workspaceOpen = false">
                    <template #accion-encabezado>
                        <button type="button" class="workspace-close" aria-label="Cerrar menú del panel"
                                @click="workspaceOpen = false"><I name="close"/></button>
                    </template>
                </WorkspaceNavigation>
            </div>
        </v-dialog>
        <SiteFooter :year="year" :landing="landing"/>
    </v-app>
</template>

<style scoped>
/* El navbar queda sobre la página; la compensación usa su altura real incluso en el prototipo. */
/* El panel conserva su acento y la línea del navbar identifica la sesión sin recolorear el logo ni alterar el acceso. */
.synaux-app[data-workspace-role='cliente'] {
    --workspace-accent: #153b64;
}

.synaux-app[data-workspace-role='profesional'] {
    --workspace-accent: #2db3a3;
}

.synaux-app[data-workspace-role='gestion'] {
    --workspace-accent: #1f6f7f;
}

.synaux-app[data-navbar-role='admin'] {
    --workspace-accent: #2db3a3;
}

/* El acento del navbar sigue el área abierta, con azul de marca para Cliente. */
.synaux-app[data-navbar-role='cliente'] {
    --navbar-accent: #153b64;
}

.synaux-app[data-navbar-role='gestion'] {
    --navbar-accent: #1f6f7f;
}

.synaux-app[data-navbar-role='admin'], .synaux-app[data-navbar-role='profesional'] {
    --navbar-accent: #2db3a3;
}

.synaux-app[data-navbar-role] :deep(.header) {
    border-bottom: 3px solid var(--navbar-accent);
}

.fixed-header {
    position: fixed;
    inset: 0 0 auto;
    z-index: 100;
    background: #fff;
    box-shadow: var(--navbar-shadow);
}

.navbar-space {
    flex: 0 0 var(--navbar-height);
    height: var(--navbar-height);
}

.workspace-content {
    scroll-margin-top: calc(var(--navbar-height) + 24px);
}

/* El panel ocupa su columna bajo el encabezado y el contenido se adapta al ancho restante. */
.workspace-layout {
    flex: 1 1 auto;
    min-width: 0;
}

.with-workspace {
    display: grid;
    grid-template-columns: var(--workspace-panel-width) minmax(0, 1fr);
    align-items: start;
}

.workspace-content {
    min-width: 0;
    width: 100%;
}

/* El fondo de la columna llega al pie. El menú se mantiene debajo del navbar al hacer scroll. */
.workspace-sidebar {
    align-self: stretch;
    min-width: 0;
    border-inline-end: 1px solid #1f6f7f26;
    background: #f5f9fa;
}

.workspace-sidebar-sticky {
    position: sticky;
    top: var(--navbar-height);
    border-inline-end: 0;
}

.with-workspace :deep(.page) {
    padding-block: 40px 72px;
}

.with-workspace :deep(.migas-navegacion + .page) {
    padding-top: 12px;
}

.with-workspace :deep(.admin-layout) {
    grid-template-columns: minmax(0, 1fr);
}

.workspace-mobile-bar {
    display: none;
}

@media (max-width: 1100px) {
    .with-workspace {
        display: block;
    }

    .workspace-sidebar {
        display: none;
    }

    .workspace-mobile-bar {
        position: sticky;
        top: var(--navbar-height);
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 24px;
        background: #f5f9fa;
        border-bottom: 1px solid #1f6f7f22;
        color: #153b64;
        font-weight: 700;
    }

    .workspace-mobile-bar button {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #1f6f7f44;
        border-radius: 8px;
        color: #153b64;
        background: #fff;
        cursor: pointer;
    }

    .workspace-mobile-bar button:focus-visible {
        outline: 3px solid #1f6f7f;
        outline-offset: 3px;
    }
}

@media (max-width: 600px) {
    .workspace-mobile-bar {
        padding-inline: 16px;
        font-size: .9rem;
    }
}
</style>
<style>
/* El panel toma todo el alto disponible desde el navbar hasta el borde inferior. */
.workspace-panel-surface {
    display: flex;
    flex-direction: column;
    height: calc(100dvh - var(--navbar-height));
    overflow: hidden;
    border-inline-end: 1px solid #1f6f7f26;
    background: #f5f9fa;
    color: #153b64;
    font-family: 'Nunito', sans-serif;
}

.workspace-mobile-dialog .v-overlay__content {
    position: fixed;
    inset: var(--navbar-height) auto 0 0;
    margin: 0 !important;
    width: min(var(--workspace-panel-width), calc(100vw - 24px)) !important;
    max-height: calc(100dvh - var(--navbar-height)) !important;
}

.workspace-mobile-dialog .v-overlay__scrim {
    top: var(--navbar-height);
}

.workspace-close {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #153b64;
    cursor: pointer;
}

.workspace-close:focus-visible {
    outline: 3px solid #1f6f7f;
}
</style>
