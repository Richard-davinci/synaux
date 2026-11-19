<script setup>
import { computed } from 'vue';
import RequestsTable from '../../Components/Management/RequestsTable.vue';
import B from '../../Components/UI/AppButton.vue';
import I from '../../Components/UI/AppIcon.vue';
import T from '../../Components/UI/PageTitle.vue';
import { referenceHref } from '../../support/synaux';

const props = defineProps({ data: { type: Object, required: true } });

// El resumen muestra los pedidos abiertos sin revisión y enlaza los indicadores a sus listados.
const openRequests = computed(() => (props.data.requests ?? []).filter(item => item.status === 'open'));
const pendingRequests = computed(() => openRequests.value.filter(item => !item.reviewed_at));
const summary = computed(() => [
    ['Solicitudes abiertas', 'solicitudes', 'file', openRequests.value.length],
    ['Presupuestos', 'presupuestos', 'receipt', props.data.quotes?.length ?? 0],
    ['Proyectos activos', 'proyectos', 'folder', (props.data.projects ?? []).filter(item => ['pending', 'in_progress'].includes(item.status)).length],
    ['Consultas', 'contactos', 'message', props.data.inquiryCount ?? props.data.inquiries?.length ?? 0],
]);
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Gestión" title="Resumen de gestión"
            description="Revisá los pedidos que requieren atención y accedé a los presupuestos y proyectos." />

        <ul class="management-metrics">
            <li v-for="[label, key, icon, count] in summary" :key="key">
                <a class="workspace-metric" :href="referenceHref('/gestion/' + key, data)">
                    <I :name="icon" /><span>{{ label }}</span><strong>{{ count }}</strong>
                </a>
            </li>
        </ul>
        <section class="card management-queue" aria-labelledby="management-queue-title">
            <div class="section-head">
                <h2 id="management-queue-title">Solicitudes por revisar</h2>
                <B compact variant="secondary" href="/gestion/solicitudes">Ver solicitudes</B>
            </div>
            <RequestsTable v-if="pendingRequests.length" :rows="pendingRequests.slice(0, 5)"
                label="Solicitudes por revisar" :preview="Boolean(data.preview)" />
            <p v-if="!pendingRequests.length">No hay solicitudes pendientes de revisión.</p>
        </section>
    </section>
</template>
