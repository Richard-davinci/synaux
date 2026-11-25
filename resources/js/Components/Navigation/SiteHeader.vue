<script setup>
import {ref, watch} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import NotificationCenter from '../Notifications/NotificationCenter.vue';
import AppButton from '../UI/AppButton.vue';
import AccionIcono from '../UI/AccionIcono.vue';
import navbarLogo from '../../../images/brand/synaux-horizontal-sin-slogan-color.svg';
import {referenceHref} from '../../support/prototype';
import {publicNav} from '../../support/useNavigationContext';

defineProps({
    gestionActive: Boolean,
    adminView: Boolean,
    showAreaSwitch: Boolean,
});

const page = usePage();
const mobileOpen = ref(false);
// El botón del navbar usa la misma salida autenticada de Laravel.
const salida = useForm({});
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
            <div v-if="showAreaSwitch" class="mobile-area-links">
                <a :href="referenceHref('/gestion', page.props)"
                   :aria-current="gestionActive ? 'page' : undefined">Ir a Gestión</a>
                <a :href="referenceHref('/admin/servicios', page.props)" :aria-current="adminView ? 'page' : undefined">Ir
                    a administración</a>
            </div>
        </nav>

        <!-- Los accesos de la cuenta y sus notificaciones comparten el grupo de la derecha. -->
        <div v-if="!page.props.preview || showAreaSwitch" class="header-account"
             :class="{ 'guest-account': !page.props.auth && !page.props.preview }">
            <nav v-if="showAreaSwitch && !$slots['acceso-panel']" class="header-area-links" aria-label="Áreas de la cuenta">
                <a :href="referenceHref('/gestion', page.props)"
                   :aria-current="gestionActive ? 'page' : undefined">Gestión</a>
                <a :href="referenceHref('/admin/servicios', page.props)" :aria-current="adminView ? 'page' : undefined">Administración</a>
            </nav>
            <template v-if="!page.props.preview && !page.props.auth">
                <AppButton compact href="/ingresar" variant="ghost">Ingresar</AppButton>
                <AppButton compact href="/crear-cuenta">Crear cuenta</AppButton>
            </template>
            <template v-else-if="!page.props.preview">
                <slot name="acceso-panel"/>
                <div class="header-session-actions">
                    <NotificationCenter/>
                    <AccionIcono icono="logout" texto="Cerrar sesión" :deshabilitada="salida.processing"
                                 @click="salida.post('/salir')"/>
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

/* Los accesos de Gestión y Administración usan el acento de la sección actual. */
.header-area-links {
    display: flex;
    align-items: center;
    gap: 4px;
}

.header-area-links a {
    display: inline-flex;
    align-items: center;
    min-height: 40px;
    padding: 8px 10px;
    border-radius: 8px;
    color: #153b64;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.header-area-links a:hover {
    background: #2db3a31a;
}

.header-area-links a[aria-current='page'] {
    box-shadow: inset 0 -3px 0 var(--navbar-accent, #2db3a3);
}

/* El cambio de área conserva una referencia visible para navegación con teclado en escritorio y móvil. */
.header-area-links a:focus-visible, .mobile-area-links a:focus-visible {
    outline: 2px solid #2db3a3;
    outline-offset: 2px;
}

.header .mobile-toggle, .header .mobile-auth, .header .mobile-area-links {
    display: none;
}

@media (max-width: 900px) {
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

    .header .header-area-links {
        display: none;
    }

    .header .guest-account {
        display: none;
    }

    .header .mobile-toggle {
        order: 2;
        display: block;
        margin-left: 0;
    }

    .header .main-nav {
        order: 3;
        top: 100%;
    }

    .header .main-nav.mobile-open {
        display: flex;
    }

    .header .mobile-auth {
        display: grid;
    }

    /* En pantallas angostas el cambio de área aparece dentro del menú desplegable y mantiene objetivos táctiles amplios. */
    .header .mobile-area-links {
        display: grid;
        gap: 4px;
        padding-top: 12px;
        border-top: 1px solid #2db3a344;
    }

    .mobile-area-links a {
        display: flex;
        align-items: center;
        min-height: 44px;
        padding: 10px 12px;
        color: #153b64;
        font-weight: 700;
        text-decoration: none;
    }

    .mobile-area-links a[aria-current='page'] {
        box-shadow: inset 3px 0 0 var(--navbar-accent, #2db3a3);
        background: #2db3a31a;
    }
}
</style>
