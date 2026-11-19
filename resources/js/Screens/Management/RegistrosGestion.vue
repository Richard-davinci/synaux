<script setup>
import { computed, ref } from 'vue';
import RecordCard from '../../Components/Management/RecordCard.vue';
import RequestsTable from '../../Components/Management/TablaSolicitudes.vue';
import ProjectUpdate from '../../Components/Projects/ProjectUpdate.vue';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';

const props = defineProps({ data: { type: Object, required: true } });
const section = computed(() => props.data.section);

// La vista usa las mismas colecciones y estados que entrega Laravel a Gestión y al portfolio profesional.
const sections = {
    solicitudes: {
        key: 'requests', title: 'Solicitudes recibidas',
        description: 'Revisá el alcance de cada pedido y prepará su presupuesto.',
        search: 'Título, servicio o cliente', empty: 'Las solicitudes enviadas por clientes aparecerán acá.',
        statuses: [['open', 'Enviada'], ['closed', 'Cerrada'], ['cancelled', 'Cancelada']],
    },
    presupuestos: {
        key: 'quotes', icon: 'receipt', title: 'Presupuestos',
        description: 'Consultá borradores y versiones emitidas o enviadas.',
        search: 'Título, cliente o número', empty: 'Los presupuestos que prepares desde una solicitud o en forma directa aparecerán acá.',
        statuses: [['draft', 'Borrador'], ['sent', 'Enviado'], ['issued', 'Emitido'], ['accepted', 'Aceptado'], ['rejected', 'Rechazado'], ['expired', 'Vencido']],
    },
    proyectos: {
        key: 'projects', icon: 'folder', title: 'Seguimiento de proyectos',
        description: 'Actualizá el estado y los próximos pasos de cada proyecto.',
        search: 'Nombre o número del proyecto', empty: 'Los presupuestos aceptados generan proyectos en esta sección.',
        statuses: [['pending', 'Pendiente de inicio'], ['in_progress', 'En desarrollo'], ['completed', 'Completado'], ['cancelled', 'Cancelado']],
    },
    portfolio: {
        key: 'works', icon: 'palette', title: 'Portfolio',
        description: 'Editá los trabajos y decidí cuáles mostrar en el sitio público.',
        search: 'Título o categoría', empty: 'Agregá un trabajo para documentar un proyecto.',
    },
    contactos: {
        key: 'inquiries', icon: 'message', title: 'Consultas recibidas',
        description: 'Leé los mensajes que llegan desde el formulario de contacto.',
        search: 'Asunto, nombre o correo', empty: 'Las consultas del formulario de contacto aparecerán acá.',
    },
};
const listing = computed(() => sections[section.value]);
const statusOptions = computed(() => [
    { value: '', label: 'Todos' },
    ...(listing.value.statuses ?? []).map(([value, label]) => ({ value, label })),
]);
const query = ref('');
const status = ref('');
const project = ref(null);

const hasFilters = computed(() => Boolean(query.value || status.value));
const rows = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase('es');
    return (props.data[listing.value.key] ?? []).filter(row => {
        const searchText = [row.title, row.topic, row.name, row.email, row.category, row.service_name,
            row.contact?.name, row.contact?.company, row.client?.name, row.client?.company, row.budget_id, row.id, section.value === 'solicitudes' ? 'SOL-' + row.id : '',
        ].join(' ').toLocaleLowerCase('es');
        return searchText.includes(needle) && (!status.value || (row.display_status ?? row.status) === status.value);
    });
});

function clearFilters() {
    query.value = '';
    status.value = '';
}
// La lista ofrece edición solo al emisor y mientras la solicitud permita preparar otra versión.
function canRevise(budget) {
    return budget.issuer_id === props.data.auth?.id && budget.status !== 'accepted'
        && (budget.external || (props.data.budgetRequests ?? props.data.requests ?? []).some(item => item.id === budget.request_id && item.status === 'open'));
}
</script>

<template>
    <section class="page shell workspace-view">
        <T :eyebrow="data.screen === 'profesional' ? 'Área profesional' : 'Gestión'"
            :title="listing.title" :description="listing.description">
            <B v-if="section === 'portfolio'" compact href="/editor/trabajo" arrow>Nuevo trabajo</B>
            <B v-if="section === 'presupuestos'" compact href="/editor/presupuesto?directo=1" arrow>Crear presupuesto directo</B>
        </T>

        <div class="filters workspace-filters" :class="{ 'workspace-filters-single': statusOptions.length === 1 }">
            <F v-model="query" label="Buscar" type="search" :placeholder="listing.search" />
            <F v-if="statusOptions.length > 1" v-model="status" label="Estado" type="select" :options="statusOptions" />
            <B compact variant="secondary" :disabled="!hasFilters" @click="clearFilters">Limpiar filtros</B>
        </div>
        <RequestsTable v-if="section === 'solicitudes' && rows.length" :rows="rows"
            label="Solicitudes recibidas" :preview="Boolean(data.preview)" />
        <div v-else-if="rows.length" class="stack management-records">
            <RecordCard v-for="item in rows" :key="item.id" :item="item" :section="section" :icon="listing.icon"
                :can-revise="section === 'presupuestos' && canRevise(item)" @update-project="project = $event" />
        </div>
        <E v-else :title="hasFilters ? 'No encontramos resultados' : 'Todavía no hay registros'"
            :text="hasFilters ? 'Probá con otro texto o limpiá los filtros.' : listing.empty" />
        <ProjectUpdate v-if="project" :project="project" @close="project = null" />
    </section>
</template>
