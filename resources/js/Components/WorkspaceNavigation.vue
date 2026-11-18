<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import I from './SynauxIcon.vue';
import { referenceHref, demoNotice } from '../support/synaux';

// Este panel organiza enlaces ya autorizados; mostrar un enlace no concede permisos de acceso.
const props = defineProps({ items: Array, area: String, current: String, accountName: String });
const emit = defineEmits(['navigate']);
const page = usePage();
// Los contadores se derivan de los mismos avisos sin leer que los badges de las tarjetas.
function unreadFor(label) {
    const type = { Solicitudes: 'solicitud', Presupuestos: 'presupuesto', Proyectos: 'proyecto', Consultas: 'consulta' }[label];
    return page.props.preview ? 0 : (page.props.notifications?.unread_entities?.[type]?.length ?? 0);
}
const title = computed(() => props.area === 'gestion' ? 'Panel de gestión' : props.area === 'profesional' ? 'Área profesional' : 'Mi espacio');
const icons = { Resumen: 'layout', Estimaciones: 'edit', Solicitudes: 'file', Presupuestos: 'file', Proyectos: 'folder', Portfolio: 'palette', Consultas: 'megaphone', Oportunidades: 'web', 'Mis propuestas': 'file', Propuestas: 'file', Publicaciones: 'web', Invitaciones: 'user' };
// Los grupos separan la información comercial de los recursos que calculan estimaciones orientativas.
const administration = [
    { title: 'Catálogo', items: [['Servicios', 'servicios'], ['Categorías', 'categorias']] },
    { title: 'Presupuestador', items: [['Preguntas y opciones', 'preguntas'], ['Componentes', 'componentes'], ['Reglas de cálculo', 'reglas'], ['Tarifarios', 'tarifario']] },
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
        <nav :aria-label="'Secciones de ' + title.toLowerCase()">
            <a v-for="[label, href] in items.filter(([label]) => label !== 'Administración' && label !== 'Mi perfil')" :key="href" :href="referenceHref(href, page.props)" :aria-current="isActive(href) ? 'page' : undefined" @click="emit('navigate')"><I :name="icons[label] ?? 'folder'" /><span>{{ label }}</span><span v-if="unreadFor(label)" class="workspace-unread" :aria-label="`${unreadFor(label)} sin leer`">{{ unreadFor(label) }}</span></a>
        </nav>
        <!-- La configuración administrativa se despliega dentro del mismo panel, sin otro menú en el contenido. -->
        <details v-if="area === 'gestion'" :open="current === 'administracion'" class="workspace-admin">
            <summary><I name="layout" /><span>Administración</span></summary>
            <!-- Los grupos usan rutas administrativas existentes y no conceden permisos adicionales. -->
            <nav v-for="group in administration" :key="group.title" :aria-label="group.title"><p class="workspace-group-title">{{ group.title }}</p><a v-for="[label, section] in group.items" :key="section" :href="referenceHref('/admin/' + section, page.props)" :aria-current="current === 'administracion' && (page.props.section || 'servicios') === section ? 'page' : undefined" @click="emit('navigate')">{{ label }}</a></nav>
        </details>
        </div>
        <!-- Los accesos de cuenta permanecen al pie sin cambiar sus rutas ni el cierre de sesión. -->
        <nav class="workspace-account" aria-label="Opciones de la cuenta"><a :href="referenceHref('/mi-perfil', page.props)" :aria-current="current === 'mi-perfil' ? 'page' : undefined" @click="emit('navigate')"><I name="user" /><span>Mi perfil</span></a><button type="button" class="workspace-logout" :disabled="logoutForm.processing" @click="logout"><I name="arrow" /><span>{{ logoutForm.processing ? 'Cerrando sesión…' : 'Cerrar sesión' }}</span></button></nav>
    </div>
</template>

<style scoped>
/* El panel conserva la paleta de synaux y ofrece objetivos de al menos 44 px para teclado y tacto. */
/* El contenedor aporta la altura; solo las secciones se desplazan cuando falta espacio. */
/* El color identifica el área; el texto y la selección activa mantienen contraste en los tres roles. */
.workspace-navigation { --role-accent: #153b64; --role-on-accent: #fff; --role-tint: #153b640a; border-top: 3px solid var(--role-accent); display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; height: 100%; padding: 26px 18px; color: #153b64; }
.workspace-navigation[data-role='profesional'] { --role-accent: #2db3a3; --role-on-accent: #153b64; --role-tint: #2db3a31a; }
.workspace-navigation[data-role='gestion'] { --role-accent: #1f6f7f; --role-tint: #1f6f7f12; }
.workspace-sections { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.workspace-eyebrow { border-left: 3px solid var(--role-accent); padding-left: 8px; text-wrap: balance; margin: 0 0 8px; color: #446075; font-size: .75rem; letter-spacing: .12em; font-weight: 700; text-transform: uppercase; }
.workspace-user { margin: -12px 0 22px; color: #446075; font-size: .95rem; overflow-wrap: anywhere; }
.workspace-navigation h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.3rem; font-weight: 700; line-height: 1.25; margin: 0 10px 24px 0; }
.workspace-navigation nav { display: grid; gap: 5px; }
.workspace-navigation a, .workspace-navigation summary, .workspace-logout { display: flex; gap: 11px; align-items: center; min-height: 46px; padding: 12px; border-radius: 10px; color: #153b64; text-decoration: none; font-weight: 600; line-height: 1.3; cursor: pointer; }
.workspace-navigation a:hover, .workspace-navigation summary:hover, .workspace-logout:hover { background: var(--role-tint); }
.workspace-navigation a[aria-current='page'] { color: var(--role-on-accent); background: var(--role-accent); }
.workspace-navigation a:focus-visible, .workspace-navigation summary:focus-visible, .workspace-logout:focus-visible { outline: 3px solid var(--role-accent); outline-offset: 2px; }
.workspace-logout { width: 100%; border: 0; background: transparent; font: inherit; text-align: left; }
.workspace-logout:disabled { opacity: .5; cursor: wait; }
.workspace-navigation .icon { width: 20px; height: 20px; }
.workspace-admin { margin-top: 14px; }
.workspace-admin summary { list-style: none; }
.workspace-admin summary::-webkit-details-marker { display: none; }
.workspace-admin summary::after { content: '+'; margin-left: auto; font-size: 1.2rem; }
.workspace-admin[open] summary::after { content: '−'; }
.workspace-admin nav { margin: 6px 0 8px 20px; padding-left: 12px; border-left: 1px solid #1f6f7f33; }
.workspace-admin nav a { font-size: .95rem; }
/* Los títulos permiten reconocer catálogo y presupuestador sin agregar otro nivel desplegable. */
.workspace-group-title { margin: 12px 0 4px; color: #446075; font-size: .8rem; font-weight: 700; }
/* El contador acompaña la sección sin reemplazar su nombre ni el estado de navegación activo. */
.workspace-unread { margin-left: auto; min-width: 22px; padding: 2px 6px; border-radius: 12px; color: #153b64; background: #2db3a333; font-size: .75rem; text-align: center; }
.workspace-navigation a[aria-current='page'] .workspace-unread { color: var(--role-on-accent); background: #ffffff33; }
/* El pie reserva espacio para perfil y salida aunque crezca la lista de secciones. */
.workspace-account { flex-shrink: 0; margin-top: 22px; padding-top: 16px; border-top: 1px solid #1f6f7f33; }
</style>
