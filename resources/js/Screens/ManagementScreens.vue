<script setup>
import { computed, ref } from 'vue';
import B from '../Components/UI/AppButton.vue';
import F from '../Components/UI/FormField.vue';
import T from '../Components/UI/PageTitle.vue';
import S from '../Components/UI/StatusBadge.vue';
import E from '../Components/UI/EmptyState.vue';
import I from '../Components/UI/AppIcon.vue';
import NewBadge from '../Components/Notifications/NewBadge.vue';
import ProjectUpdate from '../Components/Projects/ProjectUpdate.vue';
import RequestsTable from '../Components/Management/RequestsTable.vue';
import { money, date, referenceHref } from '../support/synaux';
// Gestión reutiliza las lecturas, mientras cada edición vuelve a verificar el emisor en Laravel.
const props = defineProps({data:Object});
const section = computed(() => props.data.section || 'resumen');
// Los estados del filtro corresponden al recurso que Gestión está consultando.
const statusOptions = computed(() => [{ value: '', label: 'Todos' }, ...({
    solicitudes: [['open', 'Enviada'], ['closed', 'Cerrada'], ['cancelled', 'Cancelada']],
    presupuestos: [['draft', 'Borrador'], ['sent', 'Enviado'], ['issued', 'Emitido'], ['accepted', 'Aceptado'], ['rejected', 'Rechazado'], ['expired', 'Vencido']],
    proyectos: [['pending', 'Pendiente de inicio'], ['in_progress', 'En desarrollo'], ['completed', 'Completado'], ['cancelled', 'Cancelado']],
}[section.value] ?? []).map(([value, label]) => ({ value, label }))]);
const query = ref('');
const status = ref('');
const project = ref(null);
// Cada listado mantiene sus estados y usa textos propios para el encabezado y la búsqueda.
const sections = {
    solicitudes: { key: 'requests', icon: 'file', title: 'Solicitudes recibidas', description: 'Revisá el alcance de cada pedido y prepará su presupuesto.', search: 'Título, servicio o cliente', empty: 'Las solicitudes enviadas por clientes aparecerán acá.' },
    presupuestos: { key: 'quotes', icon: 'receipt', title: 'Presupuestos', description: 'Consultá borradores y versiones emitidas o enviadas.', search: 'Título, cliente o número', empty: 'Los presupuestos que prepares desde una solicitud o en forma directa aparecerán acá.' },
    proyectos: { key: 'projects', icon: 'folder', title: 'Seguimiento de proyectos', description: 'Actualizá el estado y los próximos pasos de cada proyecto.', search: 'Nombre o número del proyecto', empty: 'Los presupuestos aceptados generan proyectos en esta sección.' },
    portfolio: { key: 'works', icon: 'palette', title: 'Portfolio', description: 'Editá los trabajos y decidí cuáles mostrar en el sitio público.', search: 'Título o categoría', empty: 'Agregá un trabajo para documentar un proyecto.' },
    contactos: { key: 'inquiries', icon: 'message', title: 'Consultas recibidas', description: 'Leé los mensajes que llegan desde el formulario de contacto.', search: 'Asunto, nombre o correo', empty: 'Las consultas del formulario de contacto aparecerán acá.' },
};
const listing = computed(() => sections[section.value]);
const hasFilters = computed(() => Boolean(query.value || status.value));
const rows = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase('es');
    return (props.data[listing.value?.key] ?? []).filter(row => {
        const searchText = [row.title, row.topic, row.name, row.email, row.category, row.service_name,
            row.contact?.name, row.contact?.company, row.client?.name, row.client?.company, row.budget_id, row.id, section.value === 'solicitudes' ? 'SOL-' + row.id : '',
        ].join(' ').toLocaleLowerCase('es');
        return searchText.includes(needle) && (!status.value || (row.display_status ?? row.status) === status.value);
    });
});
const pendingRequests = computed(() => (props.data.requests ?? []).filter(item => item.status === 'open' && !item.reviewed_at));
// Los indicadores son accesos a los listados y conservan el alcance del panel.
const summary = computed(() => [
    ['Solicitudes abiertas', 'solicitudes', (props.data.requests ?? []).filter(item => item.status === 'open').length],
    ['Presupuestos', 'presupuestos', props.data.quotes?.length ?? 0],
    ['Proyectos activos', 'proyectos', (props.data.projects ?? []).filter(item => ['pending', 'in_progress'].includes(item.status)).length],
    ['Consultas', 'contactos', props.data.inquiryCount ?? props.data.inquiries?.length ?? 0],
]);
function clearFilters() {
    query.value = '';
    status.value = '';
}
// La lista ofrece edición solo al emisor y mientras la solicitud permita preparar otra versión.
function canRevise(budget) {
    return budget.issuer_id === props.data.auth?.id && budget.status !== 'accepted'
        && (budget.external || (props.data.budgetRequests ?? props.data.requests ?? []).some(item => item.id === budget.request_id && item.status === 'open'));
}
function editProject(p) { project.value = p; }
</script>

<template>
    <!-- Gestión comparte encabezados, filtros y acciones entre sus listados. -->
    <section class="page shell workspace-view">
        <T :eyebrow="data.screen === 'profesional' ? 'Área profesional' : 'Gestión'"
            :title="listing?.title ?? 'Resumen de gestión'"
            :description="listing?.description ?? 'Revisá los pedidos que requieren atención y accedé a los presupuestos y proyectos.'">
            <B v-if="section === 'portfolio'" compact href="/editor/trabajo" arrow>Nuevo trabajo</B>
            <B v-if="section === 'presupuestos'" compact href="/editor/presupuesto?directo=1" arrow>Crear presupuesto directo</B>
        </T>

        <template v-if="section === 'resumen'">
            <ul class="management-metrics">
                <li v-for="[label, key, count] in summary" :key="key">
                    <a class="workspace-metric" :href="referenceHref('/gestion/' + key, data)">
                        <I :name="sections[key].icon" /><span>{{ label }}</span><strong>{{ count }}</strong>
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
        </template>

        <template v-else>
            <div class="filters workspace-filters" :class="{ 'workspace-filters-single': statusOptions.length === 1 }">
                <F v-model="query" label="Buscar" type="search" :placeholder="listing?.search" />
                <F v-if="statusOptions.length > 1" v-model="status" label="Estado" type="select" :options="statusOptions" />
                <B compact variant="secondary" :disabled="!hasFilters" @click="clearFilters">Limpiar filtros</B>
            </div>
            <RequestsTable v-if="section === 'solicitudes' && rows.length" :rows="rows"
                label="Solicitudes recibidas" :preview="Boolean(data.preview)" />
            <div v-else-if="rows.length" class="stack management-records">
                <v-card v-for="item in rows" :key="item.id" :id="section === 'contactos' ? 'consulta-' + item.id : undefined" class="card management-record">
                    <I :name="listing.icon" class="workspace-record-icon" />
                    <div class="management-record-copy">
                        <div class="management-record-meta">
                            <small>{{ date(item.created_at) }}</small>
                            <S v-if="item.status" :status="item.display_status ?? item.status" />
                            <S v-else-if="section === 'portfolio'" :status="item.is_public ? 'published' : 'unpublished'" />
                        </div>
                        <div class="record-heading">
                            <h3>{{ item.title ?? item.topic }}</h3>
                            <NewBadge :type="{ presupuestos: 'presupuesto', proyectos: 'proyecto', contactos: 'consulta' }[section]" :id="item.id" />
                        </div>
                        <template v-if="section === 'contactos'">
                            <p>{{ item.name }} · {{ item.email }}</p>
                            <p class="preserve-lines">{{ item.message }}</p>
                        </template>
                        <p v-if="section === 'presupuestos'">Versión {{ item.version_number }} · {{ money(item.total, item.currency_code) }}</p>
                        <p v-if="section === 'portfolio'">{{ item.category }}</p>
                        <template v-if="section === 'proyectos'">
                            <p>PRY-{{ item.id }} · Avance informado {{ item.progress }}%</p>
                            <progress max="100" :value="item.progress" :aria-label="'Avance de ' + item.title" />
                        </template>
                    </div>
                    <div v-if="section !== 'contactos'" class="management-record-actions">
                        <template v-if="section === 'presupuestos'">
                            <B compact :href="'/presupuestos/' + item.id" variant="secondary">Ver versión</B>
                            <B v-if="canRevise(item)" compact :href="'/editor/presupuesto/' + item.id">{{ item.status === 'draft' ? 'Continuar borrador' : 'Preparar nueva versión' }}</B>
                        </template>
                        <template v-else-if="section === 'proyectos'">
                            <B compact :href="'/proyectos/' + item.id" variant="secondary">Ver seguimiento</B>
                            <B compact @click="editProject(item)">Actualizar avance</B>
                        </template>
                        <B v-else-if="section === 'portfolio'" compact :href="'/editor/trabajo/' + item.id" variant="secondary">Editar trabajo</B>
                    </div>
                </v-card>
            </div>
            <E v-else :title="hasFilters ? 'No encontramos resultados' : 'Todavía no hay registros'"
                :text="hasFilters ? 'Probá con otro texto o limpiá los filtros.' : listing?.empty" />
        </template>
        <ProjectUpdate v-if="project" :project="project" @close="project = null" />
    </section>
</template>
