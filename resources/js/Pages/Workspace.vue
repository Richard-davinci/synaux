<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import ServiceCatalog from '../Screens/Public/ServiceCatalog.vue';
import ServiceDetail from '../Screens/Public/ServiceDetail.vue';
import PortfolioList from '../Screens/Public/PortfolioList.vue';
import PortfolioDetail from '../Screens/Public/PortfolioDetail.vue';
import About from '../Screens/Public/About.vue';
import Contact from '../Screens/Public/Contact.vue';
import AuthScreens from '../Screens/AuthScreens.vue';
import EstimatorScreens from '../Screens/EstimatorScreens.vue';
import ClientOverview from '../Screens/Client/Overview.vue';
import ClientRequestDetail from '../Screens/Client/RequestDetail.vue';
import RecordList from '../Screens/Workspace/RecordList.vue';
import MarketplaceScreens from '../Screens/MarketplaceScreens.vue';
import ManagementOverview from '../Screens/Management/Overview.vue';
import ManagementRecords from '../Screens/Management/Records.vue';
import AdminTariffs from '../Screens/AdminTariffs.vue';
import AdminConfigurationPreview from '../Screens/AdminConfigurationPreview.vue';
import PortfolioWorkEditor from '../Screens/PortfolioWorkEditor.vue';
import CatalogAdmin from '../Components/Admin/CatalogAdmin.vue';
import BudgetEditor from '../Components/Budgets/BudgetEditor.vue';
import BudgetDetail from '../Components/Budgets/BudgetDetail.vue';
import ProjectDetail from '../Components/Projects/ProjectDetail.vue';
import ManagementRequestDetail from '../Components/Management/RequestDetail.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';
// La selección de pantalla vive en el punto de entrada y respeta el área autorizada por Laravel.
const page = usePage();
const data = computed(() => page.props);
const publicScreens = {
    servicios: ServiceCatalog,
    servicio: ServiceDetail,
    portfolio: PortfolioList,
    trabajo: PortfolioDetail,
    perfil: About,
    contacto: Contact,
};
const component = computed(() => {
    const { screen, area, section, preview } = data.value;
    if (Object.hasOwn(publicScreens, screen)) return publicScreens[screen];
    if (['ingreso', 'registro', 'recuperar', 'restablecer'].includes(screen)) return AuthScreens;
    if (['estimador', 'estimacion'].includes(screen)) return EstimatorScreens;
    if (screen === 'admin') {
        if (section === 'tarifario') return AdminTariffs;
        if (preview && ['preguntas', 'componentes', 'reglas'].includes(section)) return AdminConfigurationPreview;
        return CatalogAdmin;
    }
    if (screen === 'editor-presupuesto') return BudgetEditor;
    if (screen === 'editor-trabajo') return PortfolioWorkEditor;
    if (screen === 'solicitud' && area === 'gestion' && data.value.selected?.status !== 'draft') return ManagementRequestDetail;
    if (screen === 'gestion') return !section || section === 'resumen' ? ManagementOverview : ManagementRecords;
    if (screen === 'profesional' && section === 'portfolio') return ManagementRecords;
    if (['muro', 'oportunidad', 'profesionales', 'perfil-profesional', 'publicar', 'profesional'].includes(screen)
        || (screen === 'cliente' && ['publicaciones', 'propuestas', 'invitaciones'].includes(section))) return MarketplaceScreens;
    if (screen === 'solicitud') return ClientRequestDetail;
    if (screen === 'presupuesto') return BudgetDetail;
    if (screen === 'proyecto') return ProjectDetail;
    if (screen === 'cliente') return !section || section === 'resumen' ? ClientOverview : RecordList;
    return null;
});
</script>
<template><Head :title="data.selected?.title ?? 'synaux'" /><PublicLayout><component v-if="component" :is="component" :key="`${page.url}:${data.referenceKey ?? ''}`" :data="data" /><section v-else class="page shell" /></PublicLayout></template>
