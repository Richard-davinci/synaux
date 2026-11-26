<script setup>
import {computed} from 'vue';
import RequestsTable from '../../Components/Management/TablaSolicitudes.vue';
import IndicadoresPanel from '../../Components/Navigation/IndicadoresPanel.vue';
import T from '../../Components/UI/PageTitle.vue';
import {presupuestosSinProyecto} from '../../support/agrupacionPresupuestos';

const props = defineProps({data: {type: Object, required: true}});

// El resumen muestra los pedidos abiertos sin revisión y enlaza los indicadores a sus listados.
const openRequests = computed(() => (props.data.requests ?? []).filter(item => item.status === 'open'));
const pendingRequests = computed(() => openRequests.value.filter(item => !item.reviewed_at));
const summary = computed(() => [
    ['Solicitudes abiertas', 'solicitudes', 'file', openRequests.value.length],
    ['Presupuestos', 'presupuestos', 'receipt', presupuestosSinProyecto(props.data.quotes ?? []).length],
    ['Proyectos activos', 'proyectos', 'folder', (props.data.projects ?? []).filter(item => ['pending', 'in_progress'].includes(item.status)).length],
    ['Consultas', 'contactos', 'message', props.data.inquiryCount ?? props.data.inquiries?.length ?? 0],
]);
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Gestión" title="Resumen de gestión"
           description="Revisá los pedidos que requieren atención y accedé a los presupuestos y proyectos."/>

        <IndicadoresPanel :indicadores="summary" ruta-base="/gestion" :contexto="data"/>
        <section class="card management-queue" aria-labelledby="management-queue-title">
            <h2 id="management-queue-title">Solicitudes por revisar</h2>
            <RequestsTable v-if="pendingRequests.length" :rows="pendingRequests.slice(0, 5)"
                           label="Solicitudes por revisar" :preview="Boolean(data.preview)"/>
            <p v-if="!pendingRequests.length">No hay solicitudes pendientes de revisión.</p>
        </section>
    </section>
</template>
