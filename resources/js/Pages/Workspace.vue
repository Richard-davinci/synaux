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
import AdminTariffs from '../Screens/AdminTariffs.vue';
import AdminConfigurationPreview from '../Screens/AdminConfigurationPreview.vue';
import PortfolioWorkEditor from '../Screens/PortfolioWorkEditor.vue';
import SynauxCatalogAdmin from '../Components/SynauxCatalogAdmin.vue';
import SynauxBudgetEditor from '../Components/SynauxBudgetEditor.vue';
import RequestDetail from '../Components/Management/RequestDetail.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';
// La selección de pantalla vive en el punto de entrada y respeta el área autorizada por Laravel.
const page = usePage();
const data = computed(() => page.props);
const component = computed(() => {
    const { screen, area, section, preview } = data.value;
    if (['servicios', 'servicio', 'portfolio', 'trabajo', 'perfil', 'contacto'].includes(screen)) return PublicScreens;
    if (['ingreso', 'registro', 'recuperar', 'restablecer'].includes(screen)) return AuthScreens;
    if (['estimador', 'estimacion'].includes(screen)) return EstimatorScreens;
    if (screen === 'admin') {
        if (section === 'tarifario') return AdminTariffs;
        if (preview && ['preguntas', 'componentes', 'reglas'].includes(section)) return AdminConfigurationPreview;
        return SynauxCatalogAdmin;
    }
    if (screen === 'editor-presupuesto') return SynauxBudgetEditor;
    if (screen === 'editor-trabajo') return PortfolioWorkEditor;
    if (screen === 'solicitud' && area === 'gestion' && data.value.selected?.status !== 'draft') return RequestDetail;
    if (screen === 'gestion' || (screen === 'profesional' && section === 'portfolio')) return ManagementScreens;
    if (['muro', 'oportunidad', 'profesionales', 'perfil-profesional', 'publicar', 'profesional'].includes(screen)
        || (screen === 'cliente' && ['publicaciones', 'propuestas', 'invitaciones'].includes(section))) return MarketplaceScreens;
    return ClientScreens;
});
</script>
<template><Head :title="data.selected?.title ?? 'synaux'" /><PublicLayout><component :is="component" :key="`${page.url}:${data.referenceKey ?? ''}`" :data="data" /></PublicLayout></template>
