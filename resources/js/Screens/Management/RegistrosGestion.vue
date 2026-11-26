<script setup>
import {computed, ref} from 'vue';
import RecordCard from '../../Components/Management/RecordCard.vue';
import RequestsTable from '../../Components/Management/TablaSolicitudes.vue';
import ProjectUpdate from '../../Components/Projects/ProjectUpdate.vue';
import HistorialPresupuesto from '../../Components/Presupuestos/HistorialPresupuesto.vue';
import {presupuestosSinProyecto} from '../../support/agrupacionPresupuestos';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {etapaSolicitud} from '../../support/etapasSolicitudes';

const props = defineProps({data: {type: Object, required: true}});
const section = computed(() => props.data.section);

// La vista usa las mismas colecciones y estados que entrega Laravel a Gestión y al portfolio profesional.
const sections = {
    solicitudes: {
        key: 'requests', title: 'Solicitudes recibidas',
        description: 'Revisá el alcance de cada pedido y prepará su presupuesto.',
        search: 'Título, servicio o cliente', empty: 'Las solicitudes enviadas por clientes aparecerán acá.',
        statuses: [
            ['pendiente_revision', 'Pendiente de revisión'],
            ['pendiente_presupuesto', 'Pendiente de presupuesto'],
            ['presupuesto_en_preparacion', 'Presupuesto en preparación'],
            ['con_presupuesto', 'Con presupuesto asociado'],
            ['closed', 'Cerrada'],
            ['cancelled', 'Cancelada'],
        ],
    },
    presupuestos: {
        key: 'quotes',
        icon: 'receipt',
        title: 'Presupuestos',
        description: 'Consultá borradores y versiones emitidas o enviadas. Encontrá los presupuestos aceptados en Proyectos.',
        search: 'Título, cliente o número',
        empty: 'Los presupuestos que prepares desde una solicitud o en forma directa aparecerán acá.',
        statuses: [['draft', 'Borrador'], ['sent', 'Enviado'], ['issued', 'Emitido'], ['rejected', 'Rechazado'], ['expired', 'Vencido']],
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
    {value: '', label: 'Todos'},
    ...(listing.value.statuses ?? []).map(([value, label]) => ({value, label})),
]);
const query = ref('');
const status = ref('');
const project = ref(null);

const hasFilters = computed(() => Boolean(query.value || status.value));
// El listado interno agrupa las versiones y conserva la búsqueda por sus números anteriores.
const tienePresupuestosEnProyectos = computed(() => section.value === 'presupuestos'
    && (props.data.quotes ?? []).some(version => version.status === 'accepted'));
const registros = computed(() => {
    const disponibles = props.data[listing.value.key] ?? [];
    return section.value === 'presupuestos' ? presupuestosSinProyecto(disponibles) : disponibles;
});
const rows = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase('es');
    return registros.value.filter(row => {
        const searchText = [row, ...(row.historial ?? [])].flatMap(item => [item.title, item.topic, item.name, item.email,
            item.category, item.service_name, item.contact?.name, item.contact?.company, item.client?.name, item.client?.company,
            item.budget_id && 'PRE-' + item.budget_id, item.budget_id, item.id,
            section.value === 'solicitudes' ? 'SOL-' + item.id : item.request_id && 'SOL-' + item.request_id,
        ]).filter(Boolean).join(' ').toLocaleLowerCase('es');
        const estadoVisible = section.value === 'solicitudes'
            ? etapaSolicitud(row, props.data.quotes ?? []).filtro
            : (row.display_status ?? row.status);
        return searchText.includes(needle) && (!status.value || estadoVisible === status.value);
    });
});

function clearFilters() {
    query.value = '';
    status.value = '';
}

// Solo el emisor puede continuar el borrador actual o preparar la próxima versión si la solicitud sigue abierta.
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
            <B v-if="section === 'presupuestos'" compact href="/editor/presupuesto?directo=1" arrow>Crear presupuesto
                directo</B>
        </T>

        <div class="filters workspace-filters" :class="{ 'workspace-filters-single': statusOptions.length === 1 }">
            <F v-model="query" label="Buscar" type="search" :placeholder="listing.search"/>
            <F v-if="statusOptions.length > 1" v-model="status"
               :label="section === 'solicitudes' ? 'Etapa de solicitud' : section === 'presupuestos' ? 'Estado actual' : 'Estado'"
               type="select" :options="statusOptions"/>
            <B compact variant="secondary" :disabled="!hasFilters" @click="clearFilters">Limpiar filtros</B>
        </div>
        <RequestsTable v-if="section === 'solicitudes' && rows.length" :rows="rows"
                       :quotes="data.quotes ?? []" label="Solicitudes recibidas" :preview="Boolean(data.preview)"/>
        <div v-else-if="rows.length" class="stack management-records">
            <RecordCard v-for="item in rows" :key="item.claveGrupo ?? item.id" :item="item" :section="section" :icon="listing.icon"
                        :can-revise="section === 'presupuestos' && canRevise(item)" @update-project="project = $event">
                <!-- El control de edición vive una sola vez en la versión más reciente; el historial permite abrir las anteriores. -->
                <HistorialPresupuesto v-if="item.historial?.length" class="historial-en-gestion"
                                      :versiones="item.historial"/>
            </RecordCard>
        </div>
        <E v-else-if="!hasFilters && tienePresupuestosEnProyectos" title="No hay presupuestos para revisar"
           text="Los presupuestos aceptados y sus versiones están disponibles en Proyectos."
           href="/gestion/proyectos" action="Ir a Proyectos"/>
        <E v-else :title="hasFilters ? 'No encontramos resultados' : 'Todavía no hay registros'"
           :text="hasFilters ? 'Probá con otro texto o limpiá los filtros.' : listing.empty"/>
        <ProjectUpdate v-if="project" :project="project" @close="project = null"/>
    </section>
</template>

<style scoped>
/* El historial comparte el ancho de la ficha y mantiene visibles las acciones de cada versión. */
.historial-en-gestion {
    grid-column: 1 / -1;
    min-width: 0;
}
</style>
