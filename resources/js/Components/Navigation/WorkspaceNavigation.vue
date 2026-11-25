<script setup>
import {computed} from 'vue';
import {usePage} from '@inertiajs/vue3';
import I from '../UI/AppIcon.vue';
import {referenceHref} from '../../support/prototype';

// Este panel organiza enlaces ya autorizados; mostrar un enlace no concede permisos de acceso.
const props = defineProps({items: Array, area: String, current: String, accountName: String, title: String});
const emit = defineEmits(['navigate']);
const page = usePage();

// Los contadores se derivan de los mismos avisos sin leer que los badges de las tarjetas.
function unreadFor(label) {
    const type = {
        Solicitudes: 'solicitud',
        Presupuestos: 'presupuesto',
        Proyectos: 'proyecto',
        Consultas: 'consulta'
    }[label];
    return page.props.preview ? 0 : (page.props.notifications?.unread_entities?.[type]?.length ?? 0);
}

// Administración reemplaza las secciones de gestión dentro del mismo panel para evitar una lista desplegada que obligue a desplazarse.
const isAdminView = computed(() => props.area === 'gestion' && props.current === 'administracion');
const activeAdminSection = computed(() => page.props.section || page.url.split(/[?#]/)[0].split('/')[2] || 'servicios');

// El nombre de rol refleja la cuenta y su membresía, independientemente de la sección abierta.
const roles = {administrador: 'Administrador de la organización', supervisor: 'Supervisor', profesional: 'Profesional'};
const rolCuenta = computed(() => {
    const auth = page.props.auth;
    if (auth?.is_admin || (page.props.preview && props.area === 'gestion')) return 'Administrador';
    return roles[auth?.rol_organizacion] ?? (props.area === 'profesional' ? 'Profesional' : 'Cliente');
});
const icons = {
    Resumen: 'layout',
    Administración: 'grid',
    Estimaciones: 'edit',
    Solicitudes: 'file',
    Presupuestos: 'receipt',
    Proyectos: 'folder',
    Portfolio: 'palette',
    Consultas: 'message',
    Clientes: 'user',
    Oportunidades: 'web',
    'Mis propuestas': 'file',
    Propuestas: 'file',
    Publicaciones: 'web',
    Invitaciones: 'user'
};
// Los grupos organizan el catálogo, los precios y las cuentas; el prototipo solo muestra sus secciones de referencia.
const administration = computed(() => [
    {title: 'Catálogo', items: [['Servicios', 'servicios', 'grid'], ['Categorías', 'categorias', 'tag']]},
    {title: 'Precios', items: [['Tarifarios', 'tarifario', 'calculator']]},
    ...(page.props.preview ? [] : [{title: 'Organización', items: [['Datos de la organización', 'organizacion', 'file'], ['Equipo', 'usuarios', 'user']]}]),
]);
const sectionFor = href => href.split('/').filter(Boolean).slice(1).join('/') || 'resumen';

function isActive(href) {
    return props.current === sectionFor(href) || props.current === 'clientes' && href === '/gestion/clientes';
}

</script>

<template>
    <div class="workspace-navigation" :data-role="isAdminView ? 'admin' : area">
        <!-- Las secciones recibidas ocupan el área desplazable y dejan la cuenta al pie del panel. -->
        <div class="workspace-sections">
            <div v-if="accountName" class="workspace-identity">
                <I name="user"/>
                <div>
                    <p class="workspace-user">{{ accountName }}</p>
                    <p class="workspace-role">{{ rolCuenta }}</p>
                </div>
            </div>
            <h2>{{ title }}</h2>
            <nav v-if="!isAdminView" :aria-label="'Secciones de ' + title.toLowerCase()">
                <a v-for="[label, href] in items.filter(([label]) => label !== 'Mi perfil')" :key="href"
                   :href="referenceHref(href, page.props)" :aria-current="isActive(href) ? 'page' : undefined"
                   @click="emit('navigate')"><I :name="icons[label] ?? 'folder'"/><span>{{ label }}</span><span
                    v-if="unreadFor(label)" class="workspace-unread"
                    :aria-label="`${unreadFor(label)} sin leer`">{{ unreadFor(label) }}</span></a>
            </nav>
            <!-- El panel muestra los grupos de Administración y el navbar devuelve a Gestión. -->
            <div v-else class="workspace-admin">
                <!-- Los grupos conservan rutas y permisos existentes; la vista actual marca una sola sección activa. -->
                <nav v-for="group in administration" :key="group.title" :aria-label="group.title"
                     class="workspace-group">
                    <h3 class="workspace-group-title">{{ group.title }}</h3>
                    <a v-for="[label, section, icon] in group.items" :key="section"
                       :href="referenceHref('/admin/' + section, page.props)"
                       :aria-current="activeAdminSection === section ? 'page' : undefined"
                       @click="emit('navigate')">
                        <I :name="icon"/><span>{{ label }}</span>
                    </a>
                </nav>
            </div>
        </div>
        <!-- El enlace al perfil se mantiene al pie del panel. -->
        <nav class="workspace-account" aria-label="Opciones de la cuenta"><a
            :href="referenceHref('/mi-perfil', page.props)" :aria-current="current === 'mi-perfil' ? 'page' : undefined"
            @click="emit('navigate')"><I name="user"/><span>Mi perfil</span></a>
        </nav>
    </div>
</template>

<style scoped>
/* El panel conserva la paleta de synaux y ofrece objetivos de al menos 44 px para teclado y tacto. */
/* El encabezado dibuja la línea superior; solo las secciones del panel se desplazan cuando falta espacio. */
/* Cada área usa su acento y un color de texto con contraste en el enlace activo. */
.workspace-navigation {
    --role-accent: #153b64;
    --role-selection: #153b64;
    --role-tint: #153b640a;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
    padding: 26px 18px;
    color: #153b64;
}

.workspace-navigation[data-role='profesional'] {
    --role-accent: #2db3a3;
    --role-selection: #153b64;
    --role-tint: #2db3a31a;
}

/* Gestión usa turquesa azulado. Administración conserva el turquesa claro como acento. */
.workspace-navigation[data-role='gestion'] {
    --role-accent: #1f6f7f;
    --role-selection: #1f6f7f;
    --role-tint: #1f6f7f12;
}

.workspace-navigation[data-role='admin'] {
    --role-accent: #2db3a3;
    --role-tint: #2db3a31a;
}

.workspace-sections {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 2px 4px 12px;
    margin-inline: -4px;
}

/* La ficha reúne nombre y rol con el acento del área actual. */
.workspace-identity {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 20px;
    padding: 12px;
    border-left: 3px solid var(--role-accent);
    border-radius: 8px;
    background: var(--role-tint);
}

.workspace-user, .workspace-role {
    margin: 0;
    overflow-wrap: anywhere;
}

.workspace-user {
    font-size: .95rem;
    font-weight: 700;
}

.workspace-role {
    margin-top: 2px;
    color: #446075;
    font-size: .8rem;
}

.workspace-navigation h2 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.3rem;
    font-weight: 700;
    line-height: 1.25;
    margin: 0 0 14px;
}

.workspace-navigation nav {
    display: grid;
    gap: 5px;
}

.workspace-navigation a {
    display: flex;
    gap: 11px;
    align-items: center;
    min-height: 46px;
    padding: 12px;
    border-radius: 10px;
    color: #153b64;
    text-decoration: none;
    font-weight: 600;
    line-height: 1.3;
    cursor: pointer;
}

.workspace-navigation a:hover {
    background: var(--role-tint);
}

.workspace-navigation a[aria-current='page'] {
    color: #fff;
    background: var(--role-selection);
    box-shadow: inset 3px 0 0 var(--role-accent), var(--button-shadow);
}

.workspace-navigation a:focus-visible {
    outline: 3px solid var(--role-accent);
    outline-offset: 2px;
}

.workspace-navigation .icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* Los grupos administrativos se muestran al mismo nivel dentro del panel. */
.workspace-admin {
    display: grid;
    gap: 16px;
}

.workspace-admin nav {
    margin: 0;
    padding: 0;
    border: 0;
}

.workspace-admin nav a {
    font-size: .95rem;
}

/* Los rótulos en mayúsculas y la línea entre grupos distinguen las secciones de los enlaces. */
.workspace-admin .workspace-group + .workspace-group {
    border-top: 1px solid #1f6f7f26;
    padding-top: 16px;
}

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
.workspace-unread {
    margin-left: auto;
    min-width: 22px;
    padding: 2px 6px;
    border-radius: 12px;
    color: #153b64;
    background: #2db3a333;
    font-size: .75rem;
    text-align: center;
}

.workspace-navigation a[aria-current='page'] .workspace-unread {
    color: #fff;
    background: #ffffff33;
}

/* El pie reserva espacio para perfil y salida aunque crezca la lista de secciones. */
.workspace-account {
    flex-shrink: 0;
    margin-top: 22px;
    padding-top: 16px;
    border-top: 1px solid #1f6f7f33;
}
</style>
