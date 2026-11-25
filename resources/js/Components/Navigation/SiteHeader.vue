<script setup>
import {ref, watch} from 'vue';
import {usePage} from '@inertiajs/vue3';
import NotificationCenter from '../Notifications/NotificationCenter.vue';
import AppButton from '../UI/AppButton.vue';
import MenuCuenta from './MenuCuenta.vue';
import navbarLogo from '../../../images/brand/synaux-horizontal-sin-slogan-color.svg';
import {referenceHref} from '../../support/prototype';
import {publicNav} from '../../support/useNavigationContext';

defineProps({
    gestionActive: Boolean,
    adminView: Boolean,
    showAreaSwitch: Boolean,
    accountName: String,
    accountRole: String,
});

const page = usePage();
const mobileOpen = ref(false);
watch(() => page.url, () => {
    mobileOpen.value = false;
});
</script>

<template>
    <!-- El selector del prototipo abre vistas de referencia sin modificar la sesión. -->
    <div v-if="page.props.preview" class="reference-bar">
        <div class="shell">
            <span>Prototipo · Datos de ejemplo</span>
            <label>Ver pantalla
                <select :value="page.props.referenceKey" aria-label="Pantalla del prototipo"
                        @change="$inertia.get('/prototipo/' + $event.target.value)">
                    <option v-for="item in page.props.referenceScreens" :key="item.key" :value="item.key">{{
                            item.name
                        }}
                    </option>
                </select>
            </label>
            <a href="/plataforma">Abrir aplicación</a>
        </div>
    </div>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <header class="header">
        <a :href="referenceHref('/inicio', page.props)" class="logo-button" aria-label="synaux: inicio">
            <img :src="navbarLogo" class="brand-logo" width="405" height="140" alt="synaux">
        </a>
        <button class="mobile-toggle" :aria-expanded="mobileOpen" aria-label="Abrir navegación"
                @click="mobileOpen = !mobileOpen">☰
        </button>
        <nav :class="['main-nav', { 'mobile-open': mobileOpen }]" aria-label="Navegación principal">
            <a v-for="[label, href] in publicNav" :key="href" :href="referenceHref(href, page.props)"
               :aria-current="page.url.split('?')[0] === href ? 'page' : undefined">{{ label }}</a>
            <div v-if="!page.props.auth && !page.props.preview" class="nav-actions mobile-auth">
                <AppButton compact href="/ingresar" variant="ghost">Ingresar</AppButton>
                <AppButton compact href="/crear-cuenta">Crear cuenta</AppButton>
            </div>
        </nav>

        <!-- Los accesos de la cuenta y sus notificaciones comparten el grupo de la derecha. -->
        <div v-if="!page.props.preview || accountName" class="header-account"
             :class="{ 'guest-account': !page.props.auth && !page.props.preview }">
            <template v-if="!page.props.preview && !page.props.auth">
                <AppButton compact href="/ingresar" variant="ghost">Ingresar</AppButton>
                <AppButton compact href="/crear-cuenta">Crear cuenta</AppButton>
            </template>
            <template v-else-if="accountName">
                <slot name="acceso-panel"/>
                <div class="header-session-actions">
                    <NotificationCenter v-if="!page.props.preview"/>
                    <MenuCuenta :nombre="accountName" :rol="accountRole" :show-area-switch="showAreaSwitch"
                                :gestion-active="gestionActive" :admin-view="adminView"/>
                </div>
            </template>
        </div>
    </header>
</template>

<style scoped>
/* Tres bloques mantienen el menú centrado independientemente del ancho del logo o de las acciones. */
.header {
    display: grid;
    grid-template-columns: minmax(150px, 1fr) auto minmax(150px, 1fr);
}

.header .logo-button {
    grid-column: 1;
    grid-row: 1;
    justify-self: start;
}

.header .main-nav {
    grid-column: 2;
    grid-row: 1;
    justify-self: center;
}

.header-account {
    grid-column: 3;
    grid-row: 1;
    justify-self: end;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-session-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.header .mobile-toggle, .header .mobile-auth {
    display: none;
}

@media (max-width: 1100px) {
    .header {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .header .logo-button {
        flex: 1 1 0;
        min-width: 0;
    }

    .header .logo-button img {
        max-width: 100%;
    }

    .header-account {
        order: 1;
        gap: 8px;
    }

    .header .guest-account {
        display: none;
    }

    .header .mobile-toggle {
        order: 2;
        display: block;
        margin-left: 0;
    }

    /* En tablet, el menú público se despliega igual que en móvil para dejar espacio a la cuenta. */
    .header .main-nav {
        display: none;
        position: absolute;
        inset: 100% 0 auto;
        order: 3;
        flex-direction: column;
        align-items: stretch;
        gap: 4px;
        padding: 18px 20px 24px;
        background: #fff;
        box-shadow: var(--navbar-shadow);
    }

    .header .main-nav.mobile-open {
        display: flex;
    }

    .header .mobile-auth {
        display: grid;
    }

}
</style>
