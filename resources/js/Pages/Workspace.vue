<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import PublicScreens from '../Screens/PublicScreens.vue';
import AuthScreens from '../Screens/AuthScreens.vue';
import EstimatorScreens from '../Screens/EstimatorScreens.vue';
import ClientScreens from '../Screens/ClientScreens.vue';
import MarketplaceScreens from '../Screens/MarketplaceScreens.vue';
import ManagementScreens from '../Screens/ManagementScreens.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';
// Cada familia conserva un componente propio. La clave reinicia los formularios al cambiar de registro.
const page=usePage();
const data=computed(()=>page.props);
const component=computed(()=>['servicios','servicio','portfolio','trabajo','perfil','contacto'].includes(data.value.screen)?PublicScreens
    :['ingreso','registro','recuperar','restablecer'].includes(data.value.screen)?AuthScreens
    :['estimador','estimacion'].includes(data.value.screen)?EstimatorScreens
    :['gestion','admin','editor-presupuesto','editor-trabajo'].includes(data.value.screen) || (data.value.screen==='profesional' && data.value.section==='portfolio')?ManagementScreens
    :['muro','oportunidad','profesionales','perfil-profesional','publicar','profesional'].includes(data.value.screen) || (data.value.screen==='cliente' && ['publicaciones','propuestas','invitaciones'].includes(data.value.section))?MarketplaceScreens:ClientScreens);
</script>
<template><Head :title="data.selected?.title ?? 'Synaux'" /><PublicLayout><component :is="component" :key="`${page.url}:${data.referenceKey ?? ''}`" :data="data" /></PublicLayout></template>
