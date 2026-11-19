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
import Login from '../Screens/Auth/Login.vue';
import Register from '../Screens/Auth/Register.vue';
import ForgotPassword from '../Screens/Auth/ForgotPassword.vue';
import ResetPassword from '../Screens/Auth/ResetPassword.vue';
import EstimatePreview from '../Screens/Estimates/EstimatePreview.vue';
import SavedEstimate from '../Screens/Estimates/SavedEstimate.vue';
import ClientOverview from '../Screens/Client/Overview.vue';
import ClientRequestDetail from '../Screens/Client/RequestDetail.vue';
import RecordList from '../Screens/Workspace/RecordList.vue';
import ClientPublications from '../Screens/Client/Publications.vue';
import Directory from '../Screens/Marketplace/Directory.vue';
import OpportunityList from '../Screens/Marketplace/OpportunityList.vue';
import OpportunityDetail from '../Screens/Marketplace/OpportunityDetail.vue';
import MarketplaceProfileDetail from '../Screens/Marketplace/ProfileDetail.vue';
import Proposals from '../Screens/Marketplace/Proposals.vue';
import Invitations from '../Screens/Marketplace/Invitations.vue';
import ProfessionalOverview from '../Screens/Professional/Overview.vue';
import ProfessionalProfileEdit from '../Screens/Professional/ProfileEdit.vue';
import NewRequest from '../Screens/Requests/NewRequest.vue';
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
const authScreens = {
    ingreso: Login,
    registro: Register,
    recuperar: ForgotPassword,
    restablecer: ResetPassword,
};
const publicScreens = {
    servicios: ServiceCatalog,
    servicio: ServiceDetail,
    portfolio: PortfolioList,
    trabajo: PortfolioDetail,
    perfil: About,
    contacto: Contact,
    muro: OpportunityList,
    oportunidad: OpportunityDetail,
    profesionales: Directory,
    'perfil-profesional': MarketplaceProfileDetail,
    publicar: NewRequest,
};
const professionalScreens = {
    resumen: ProfessionalOverview,
    perfil: ProfessionalProfileEdit,
    propuestas: Proposals,
    invitaciones: Invitations,
    presupuestos: RecordList,
    proyectos: RecordList,
    portfolio: ManagementRecords,
};
const clientScreens = {
    resumen: ClientOverview,
    publicaciones: ClientPublications,
    propuestas: Proposals,
    invitaciones: Invitations,
    estimaciones: RecordList,
    solicitudes: RecordList,
    presupuestos: RecordList,
    proyectos: RecordList,
};
const component = computed(() => {
    const { screen, area, section, preview } = data.value;
    if (Object.hasOwn(publicScreens, screen)) return publicScreens[screen];
    if (Object.hasOwn(authScreens, screen)) return authScreens[screen];
    if (screen === 'estimador') return EstimatePreview;
    if (screen === 'estimacion') return SavedEstimate;
    if (screen === 'admin') {
        if (section === 'tarifario') return AdminTariffs;
        if (preview && ['preguntas', 'componentes', 'reglas'].includes(section)) return AdminConfigurationPreview;
        return CatalogAdmin;
    }
    if (screen === 'editor-presupuesto') return BudgetEditor;
    if (screen === 'editor-trabajo') return PortfolioWorkEditor;
    if (screen === 'solicitud' && area === 'gestion' && data.value.selected?.status !== 'draft') return ManagementRequestDetail;
    if (screen === 'gestion') return !section || section === 'resumen' ? ManagementOverview : ManagementRecords;
    if (screen === 'profesional') return professionalScreens[section || 'resumen'] ?? null;
    if (screen === 'solicitud') return ClientRequestDetail;
    if (screen === 'presupuesto') return BudgetDetail;
    if (screen === 'proyecto') return ProjectDetail;
    if (screen === 'cliente') return clientScreens[section || 'resumen'] ?? null;
    return null;
});
</script>
<template><Head :title="data.selected?.title ?? 'synaux'" /><PublicLayout><component v-if="component" :is="component" :key="`${page.url}:${data.referenceKey ?? ''}`" :data="data" /><section v-else class="page shell" /></PublicLayout></template>
