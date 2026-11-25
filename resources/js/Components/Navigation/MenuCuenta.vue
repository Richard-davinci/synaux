<script setup>
import {computed, ref, watch} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import {VMenu} from 'vuetify/components';
import I from '../UI/AppIcon.vue';
import {initials} from '../../support/formatters';
import {demoNotice, referenceHref} from '../../support/prototype';

const props = defineProps({nombre: String, rol: String, showAreaSwitch: Boolean, accesoCliente: Boolean, gestionActive: Boolean, adminView: Boolean});
const page = usePage();
const abierto = ref(false);
const salida = useForm({});

// El menú cambia de sección con los permisos vigentes de la cuenta.
const areas = computed(() => {
    if (props.showAreaSwitch) return [
        {nombre: 'Gestión', ruta: '/gestion', icono: 'layout', activa: props.gestionActive},
        {nombre: 'Administración', ruta: '/admin/servicios', icono: 'grid', activa: props.adminView},
    ];
    if (props.accesoCliente) return [
        {nombre: 'Mi espacio', ruta: '/cliente', icono: 'layout', activa: page.url.split('?')[0] === '/cliente'},
    ];
    return [];
});
watch(() => page.url, () => { abierto.value = false; });

function cerrarSesion() {
    if (page.props.preview) {
        demoNotice.value = 'Vista de referencia: no se cierra ninguna sesión real.';
        abierto.value = false;
        return;
    }
    salida.post('/salir');
}
</script>

<template>
    <VMenu v-model="abierto" location="bottom end" :offset="10" :close-on-content-click="false"
            max-width="calc(100vw - 24px)">
        <template #activator="{props: activador}">
            <button v-bind="activador" type="button" class="cuenta-activador" aria-label="Menú de cuenta">
                <span class="cuenta-avatar" aria-hidden="true">{{ initials(nombre) }}</span>
                <span v-if="rol" class="cuenta-rol-navbar">{{ rol }}</span>
                <I name="chevron-down"/>
            </button>
        </template>
        <v-card class="menu-cuenta" aria-label="Opciones de la cuenta">
            <header class="cuenta-identidad">
                <span class="cuenta-avatar" aria-hidden="true">{{ initials(nombre) }}</span>
                <div>
                    <strong>{{ nombre }}</strong>
                    <p v-if="rol">{{ rol }}</p>
                </div>
            </header>
            <nav v-if="areas.length" class="cuenta-opciones" aria-label="Áreas de trabajo">
                <a v-for="area in areas" :key="area.ruta" :href="referenceHref(area.ruta, page.props)"
                   :aria-current="area.activa ? 'page' : undefined" @click="abierto = false">
                    <I :name="area.icono"/><span>{{ area.nombre }}</span>
                </a>
            </nav>
            <div class="cuenta-opciones cuenta-opciones-personales">
                <a :href="referenceHref('/mi-perfil', page.props)" @click="abierto = false"><I name="user"/>Mi perfil</a>
                <button type="button" :disabled="salida.processing" @click="cerrarSesion"><I name="logout"/>{{ salida.processing ? 'Cerrando sesión…' : 'Cerrar sesión' }}</button>
            </div>
        </v-card>
    </VMenu>
</template>

<style scoped>
/* El avatar identifica la cuenta con iniciales y el menú usa las superficies de synaux. */
.cuenta-activador {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 4px 6px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #153b64;
    font: inherit;
    font-size: .875rem;
    font-weight: 700;
    cursor: pointer;
}
.cuenta-avatar {
    display: inline-grid;
    flex: 0 0 auto;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #153b64;
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
}
.cuenta-activador .icon, .cuenta-opciones .icon {
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.cuenta-activador .icon { width: 15px; height: 15px; }
.cuenta-rol-navbar { white-space: nowrap; }
.menu-cuenta {
    width: min(300px, calc(100vw - 24px));
    border: 1px solid #1f6f7f33;
    border-radius: 12px;
    box-shadow: 0 8px 24px #153b6426;
    color: #153b64;
    font-family: 'Nunito', sans-serif;
}
.cuenta-identidad {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #f5f9fa;
    overflow-wrap: anywhere;
}
.cuenta-identidad p { margin: 2px 0 0; color: #446075; font-size: .85rem; }
.cuenta-opciones { display: grid; gap: 3px; padding: 7px; }
.cuenta-opciones-personales { border-top: 1px solid #1f6f7f26; }
.cuenta-opciones a, .cuenta-opciones button {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 8px 10px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: start;
    text-decoration: none;
    cursor: pointer;
}
.cuenta-opciones .icon { width: 19px; height: 19px; }
.cuenta-opciones [aria-current='page'] { background: #2db3a31a; font-weight: 700; }
.cuenta-activador:hover, .cuenta-opciones :is(a, button):hover { background: #e8f3f4; }
.cuenta-activador:focus-visible, .cuenta-opciones :is(a, button):focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 1px;
}
.cuenta-opciones button:disabled { opacity: .5; cursor: wait; }

@media (max-width: 620px) {
    .cuenta-rol-navbar { display: none; }
}
</style>
