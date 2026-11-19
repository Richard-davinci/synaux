<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import I from '../UI/AppIcon.vue';
import { referenceHref, demoNotice } from '../../support/prototype';

// Este panel organiza enlaces ya autorizados; mostrar un enlace no concede permisos de acceso.
const props = defineProps({ items: Array, area: String, current: String, accountName: String, title: String });
const emit = defineEmits(['navigate']);
const page = usePage();
// Los contadores se derivan de los mismos avisos sin leer que los badges de las tarjetas.
function unreadFor(label) {
    const type = { Solicitudes: 'solicitud', Presupuestos: 'presupuesto', Proyectos: 'proyecto', Consultas: 'consulta' }[label];
    return page.props.preview ? 0 : (page.props.notifications?.unread_entities?.[type]?.length ?? 0);
}
// Administración reemplaza las secciones de gestión dentro del mismo panel para evitar una lista desplegada que obligue a desplazarse.
const isAdminView = computed(() => props.area === 'gestion' && props.current === 'administracion');
const icons = { Resumen: 'layout', Estimaciones: 'edit', Solicitudes: 'file', Presupuestos: 'receipt', Proyectos: 'folder', Portfolio: 'palette', Consultas: 'message', Oportunidades: 'web', 'Mis propuestas': 'file', Propuestas: 'file', Publicaciones: 'web', Invitaciones: 'user' };
// Los grupos separan la información comercial de los recursos que calculan estimaciones orientativas.
const administration = [
    { title: 'Catálogo', items: [['Servicios', 'servicios', 'grid'], ['Categorías', 'categorias', 'tag']] },
    { title: 'Precios', items: [['Tarifarios', 'tarifario', 'calculator']] },
];
const sectionFor = href => href.split('/').filter(Boolean).slice(1).join('/') || 'resumen';
function isActive(href) {
    return props.current === sectionFor(href);
}
// El cierre real invalida la sesión en Laravel; la galería solo muestra un aviso de demostración.
const logoutForm = useForm({});
function logout() {
    if (page.props.preview) {
        demoNotice.value = 'Vista de referencia: no se cierra ninguna sesión real.';
        emit('navigate');
        return;
    }
    if (page.props.auth && !logoutForm.processing) {
        logoutForm.post('/salir', { onSuccess: () => emit('navigate') });
    }
}
</script>

<template>
    <div class="workspace-navigation" :data-role="area">
        <!-- Las secciones recibidas ocupan el área desplazable y dejan la cuenta al pie del panel. -->
        <div class="workspace-sections">
        <p class="workspace-eyebrow">{{ area === 'gestion' ? 'Gestión y administración' : area === 'profesional' ? 'Área profesional' : 'Área cliente' }}</p><h2>{{ title }}</h2><p v-if="accountName" class="workspace-user">{{ accountName }}</p>
        <nav v-if="!isAdminView" :aria-label="'Secciones de ' + title.toLowerCase()">
            <a v-for="[label, href] in items.filter(([label]) => label !== 'Mi perfil')" :key="href" :href="referenceHref(href, page.props)" :aria-current="isActive(href) ? 'page' : undefined" @click="emit('navigate')"><I :name="icons[label] ?? 'folder'" /><span>{{ label }}</span><span v-if="unreadFor(label)" class="workspace-unread" :aria-label="`${unreadFor(label)} sin leer`">{{ unreadFor(label) }}</span></a>
        </nav>
        <!-- En administración solo se muestran sus dos grupos; el navbar devuelve al panel de gestión. -->
        <div v-else class="workspace-admin">
            <!-- Los grupos conservan rutas y permisos existentes; la vista actual marca una sola sección activa. -->
            <nav v-for="group in administration" :key="group.title" :aria-label="group.title" class="workspace-group">
                <h3 class="workspace-group-title">{{ group.title }}</h3>
                <a v-for="[label, section, icon] in group.items" :key="section" :href="referenceHref('/admin/' + section, page.props)"
                    :aria-current="page.props.screen === 'admin' && (page.props.section || 'servicios') === section ? 'page' : undefined"
                    @click="emit('navigate')">
                    <I :name="icon" /><span>{{ label }}</span>
                </a>
            </nav>
        </div>
        </div>
        <!-- Los accesos de cuenta permanecen al pie sin cambiar sus rutas ni el cierre de sesión. -->
        <nav class="workspace-account" aria-label="Opciones de la cuenta"><a :href="referenceHref('/mi-perfil', page.props)" :aria-current="current === 'mi-perfil' ? 'page' : undefined" @click="emit('navigate')"><I name="user" /><span>Mi perfil</span></a><button type="button" class="workspace-logout" :disabled="logoutForm.processing" @click="logout"><I name="arrow" /><span>{{ logoutForm.processing ? 'Cerrando sesión…' : 'Cerrar sesión' }}</span></button></nav>
    </div>
</template>

<style scoped>
/* El panel conserva la paleta de synaux y ofrece objetivos de al menos 44 px para teclado y tacto. */
/* El contenedor aporta la altura; solo las secciones se desplazan cuando falta espacio. */
/* Cada área usa su acento y un color de texto con contraste en el enlace activo. */
.workspace-navigation { --role-accent: #153b64; --role-on-accent: #fff; --role-tint: #153b640a; border-top: 3px solid var(--role-accent); display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; height: 100%; padding: 26px 18px; color: #153b64; }
.workspace-navigation[data-role='profesional'] { --role-accent: #2db3a3; --role-on-accent: #153b64; --role-tint: #2db3a31a; }
/* Gestión comparte el turquesa del navbar con texto oscuro en los enlaces activos. */
.workspace-navigation[data-role='gestion'] { --role-accent: #2db3a3; --role-on-accent: #153b64; --role-tint: #2db3a31a; }
.workspace-sections { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.workspace-eyebrow { border-left: 3px solid var(--role-accent); padding-left: 8px; text-wrap: balance; margin: 0 0 8px; color: #446075; font-size: .75rem; letter-spacing: .12em; font-weight: 700; text-transform: uppercase; }
.workspace-user { margin: -12px 0 22px; color: #446075; font-size: .95rem; overflow-wrap: anywhere; }
.workspace-navigation h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.3rem; font-weight: 700; line-height: 1.25; margin: 0 10px 24px 0; }
.workspace-navigation nav { display: grid; gap: 5px; }
.workspace-navigation a, .workspace-logout { display: flex; gap: 11px; align-items: center; min-height: 46px; padding: 12px; border-radius: 10px; color: #153b64; text-decoration: none; font-weight: 600; line-height: 1.3; cursor: pointer; }
.workspace-navigation a:hover, .workspace-logout:hover { background: var(--role-tint); }
.workspace-navigation a[aria-current='page'] { color: var(--role-on-accent); background: var(--role-accent); }
.workspace-navigation a:focus-visible, .workspace-logout:focus-visible { outline: 3px solid var(--role-accent); outline-offset: 2px; }
.workspace-logout { width: 100%; border: 0; background: transparent; font: inherit; text-align: left; }
.workspace-logout:disabled { opacity: .5; cursor: wait; }
.workspace-navigation .icon { width: 20px; height: 20px; flex-shrink: 0; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
/* Los módulos administrativos ocupan el ancho del panel y dos grupos compactos, sin anidación ni lista extra. */
.workspace-admin { display: grid; gap: 16px; }
.workspace-admin nav { margin: 0; padding: 0; border: 0; }
.workspace-admin nav a { font-size: .95rem; }
/* Los rótulos en mayúsculas y la línea entre grupos distinguen las secciones de los enlaces. */
.workspace-admin .workspace-group + .workspace-group { border-top: 1px solid #1f6f7f26; padding-top: 16px; }
.workspace-admin .workspace-group-title {
    margin: 0 12px 4px;
    color: #446075;
    font-family: 'Space Grotesk', sans-serif;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .12em;
    line-height: 1.4;
    text-transform: uppercase;
}
/* El contador acompaña la sección sin reemplazar su nombre ni el estado de navegación activo. */
.workspace-unread { margin-left: auto; min-width: 22px; padding: 2px 6px; border-radius: 12px; color: #153b64; background: #2db3a333; font-size: .75rem; text-align: center; }
.workspace-navigation a[aria-current='page'] .workspace-unread { color: var(--role-on-accent); background: #ffffff33; }
/* El pie reserva espacio para perfil y salida aunque crezca la lista de secciones. */
.workspace-account { flex-shrink: 0; margin-top: 22px; padding-top: 16px; border-top: 1px solid #1f6f7f33; }
</style>
