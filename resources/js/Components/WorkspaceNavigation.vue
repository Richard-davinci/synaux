<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import I from './SynauxIcon.vue';
import { referenceHref } from '../support/synaux';

// Este panel organiza enlaces ya autorizados; mostrar un enlace no concede permisos de acceso.
const props = defineProps({ items: Array, area: String, current: String });
const emit = defineEmits(['navigate']);
const page = usePage();
const title = computed(() => props.area === 'gestion' ? 'Panel de gestión' : props.area === 'profesional' ? 'Área profesional' : 'Mi espacio');
const icons = { Resumen: 'layout', Estimaciones: 'edit', Solicitudes: 'file', Presupuestos: 'file', Proyectos: 'folder', Portfolio: 'palette', Consultas: 'megaphone', Oportunidades: 'web', 'Mis propuestas': 'file', Propuestas: 'file', Publicaciones: 'web', Invitaciones: 'user' };
const administration = [['Servicios', 'servicios'], ['Preguntas', 'preguntas'], ['Componentes', 'componentes'], ['Reglas', 'reglas'], ['Tarifario', 'tarifario']];
const sectionFor = href => href.split('/').filter(Boolean).slice(1).join('/') || 'resumen';
function isActive(href) {
    return props.current === sectionFor(href);
}
</script>

<template>
    <div class="workspace-navigation">
        <p class="workspace-eyebrow">Mi cuenta</p><h2>{{ title }}</h2>
        <nav :aria-label="'Secciones de ' + title.toLowerCase()">
            <a v-for="[label, href] in items.filter(([label]) => label !== 'Administración' && label !== 'Mi perfil')" :key="href" :href="referenceHref(href, page.props)" :aria-current="isActive(href) ? 'page' : undefined" @click="emit('navigate')"><I :name="icons[label] ?? 'folder'" /><span>{{ label }}</span></a>
        </nav>
        <!-- La configuración administrativa se despliega dentro del mismo panel, sin otro menú en el contenido. -->
        <details v-if="area === 'gestion'" :open="current === 'administracion'" class="workspace-admin">
            <summary><I name="layout" /><span>Administración</span></summary>
            <nav aria-label="Configuración administrativa"><a v-for="[label, section] in administration" :key="section" :href="referenceHref('/admin/' + section, page.props)" :aria-current="current === 'administracion' && (page.props.section || 'servicios') === section ? 'page' : undefined" @click="emit('navigate')">{{ label }}</a></nav>
        </details>
        <nav class="workspace-account" aria-label="Perfil de mi cuenta"><a :href="referenceHref('/mi-perfil', page.props)" :aria-current="current === 'mi-perfil' ? 'page' : undefined" @click="emit('navigate')"><I name="user" /><span>Mi perfil</span></a></nav>
    </div>
</template>

<style scoped>
/* El panel conserva la paleta de Synaux y ofrece objetivos de al menos 44 px para teclado y tacto. */
.workspace-navigation { padding: 26px 18px; color: #153b64; }
.workspace-eyebrow { margin: 0 0 8px; color: #446075; font-size: .75rem; letter-spacing: .12em; font-weight: 700; text-transform: uppercase; }
.workspace-navigation h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.3rem; font-weight: 700; line-height: 1.25; margin: 0 10px 24px 0; }
.workspace-navigation nav { display: grid; gap: 5px; }
.workspace-navigation a, .workspace-navigation summary { display: flex; gap: 11px; align-items: center; min-height: 46px; padding: 12px; border-radius: 10px; color: #153b64; text-decoration: none; font-weight: 600; line-height: 1.3; cursor: pointer; }
.workspace-navigation a:hover, .workspace-navigation summary:hover { background: #153b640a; }
.workspace-navigation a[aria-current='page'] { color: #fff; background: #153b64; }
.workspace-navigation a:focus-visible, .workspace-navigation summary:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 2px; }
.workspace-navigation .icon { width: 20px; height: 20px; }
.workspace-admin { margin-top: 14px; }
.workspace-admin summary { list-style: none; }
.workspace-admin summary::-webkit-details-marker { display: none; }
.workspace-admin summary::after { content: '+'; margin-left: auto; font-size: 1.2rem; }
.workspace-admin[open] summary::after { content: '−'; }
.workspace-admin nav { margin: 6px 0 8px 20px; padding-left: 12px; border-left: 1px solid #1f6f7f33; }
.workspace-admin nav a { font-size: .95rem; }
.workspace-account { margin-top: 22px; padding-top: 16px; border-top: 1px solid #1f6f7f33; }
</style>
