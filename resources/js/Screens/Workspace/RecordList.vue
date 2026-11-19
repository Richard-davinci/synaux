<script setup>
import { computed, ref } from 'vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import S from '../../Components/UI/StatusBadge.vue';
import EmptyState from '../../Components/UI/EmptyState.vue';
import DeleteEstimateButton from '../../Components/Estimates/DeleteEstimateButton.vue';
import NewBadge from '../../Components/Notifications/NewBadge.vue';
import { date, money } from '../../support/synaux';

const props = defineProps({ data: { type: Object, required: true } });
const section = computed(() => props.data.section);

// Cada listado usa la colección, los estados y el identificador de su sección.
const listings = {
    estimaciones: {
        collection: 'estimates', title: 'Mis estimaciones', prefix: 'EST',
        statuses: [['calculated', 'Calculada'], ['manual', 'Revisión profesional']],
        create: { href: '/estimador', label: 'Nueva estimación', emptyLabel: 'Crear una estimación' },
    },
    solicitudes: {
        collection: 'requests', title: 'Mis solicitudes', prefix: 'SOL', notification: 'solicitud',
        statuses: [['draft', 'Borrador'], ['open', 'Enviada'], ['closed', 'Cerrada'], ['cancelled', 'Cancelada']],
        create: { href: '/solicitudes/nueva', label: 'Nueva solicitud', emptyLabel: 'Crear una nueva solicitud' },
    },
    presupuestos: {
        collection: 'quotes', title: 'Mis presupuestos', prefix: 'PRE', notification: 'presupuesto',
        statuses: [['draft', 'Borrador'], ['sent', 'Enviado'], ['accepted', 'Aceptado'], ['rejected', 'Rechazado'], ['expired', 'Vencido']],
    },
    proyectos: {
        collection: 'projects', title: 'Mis proyectos', prefix: 'PRY', notification: 'proyecto',
        statuses: [['pending', 'Pendiente de inicio'], ['in_progress', 'En desarrollo'], ['completed', 'Completado'], ['cancelled', 'Cancelado']],
    },
};
const listing = computed(() => listings[section.value]);
const search = ref('');
const status = ref('');
const statusOptions = computed(() => [
    { value: '', label: 'Todos' },
    ...listing.value.statuses.map(([value, label]) => ({ value, label })),
]);

// Los filtros solo recorren los registros que el servidor autorizó para esta sección.
const rows = computed(() => {
    const query = search.value.toLowerCase();

    return (props.data[listing.value.collection] ?? []).filter(record => {
        const matchesText = !query || `${record.title ?? record.summary} ${record.id}`.toLowerCase().includes(query);
        const matchesStatus = !status.value || (record.display_status ?? record.status) === status.value;

        return matchesText && matchesStatus;
    });
});

function description(record) {
    if (record.service_name != null) return record.service_name;
    if (section.value === 'presupuestos') return `Versión ${record.version_number} · ${money(record.total, record.currency_code)}`;
    if (section.value !== 'estimaciones') return '';

    const minimum = money(record.price_min, record.currency_snapshot);
    return record.price_max != null ? `${minimum} – ${money(record.price_max, record.currency_snapshot)}` : minimum;
}

function clearFilters() {
    search.value = '';
    status.value = '';
}
</script>

<template>
    <section class="page shell">
        <T eyebrow="Área cliente" :title="listing.title" description="Consultá el estado y los próximos pasos de cada proyecto.">
            <B v-if="listing.create" :href="listing.create.href" arrow>{{ listing.create.label }}</B>
        </T>
        <div class="filters">
            <F v-model="search" label="Buscar" placeholder="Título o identificador" />
            <F v-model="status" label="Estado" type="select" :options="statusOptions" />
        </div>
        <div class="stack">
            <v-card v-for="record in rows" :key="record.id" class="card list-row">
                <div>
                    <small>{{ listing.prefix }}-{{ record.id }} · {{ date(record.created_at) }}</small>
                    <div class="record-heading">
                        <h3>{{ record.title ?? record.summary }}</h3>
                        <NewBadge :type="listing.notification" :id="record.id" />
                    </div>
                    <p>{{ description(record) }}</p>
                    <S :status="record.display_status ?? record.status" />
                    <template v-if="section === 'proyectos'">
                        <p>Avance informado: {{ record.progress }}%</p>
                        <progress max="100" :value="record.progress" :aria-label="'Avance de ' + record.title" />
                        <p v-if="record.next_steps">{{ record.next_steps }}</p>
                    </template>
                </div>
                <div class="estimate-row-actions">
                    <B :href="'/' + section + '/' + record.id" variant="secondary">
                        {{ record.status === 'draft' && section === 'solicitudes' ? 'Continuar borrador' : 'Ver detalle' }}
                    </B>
                    <DeleteEstimateButton v-if="section === 'estimaciones'" :estimate="record" />
                </div>
            </v-card>
        </div>

        <template v-if="!rows.length && (search || status)">
            <EmptyState title="No encontramos resultados"
                text="Probá con otro texto o estado, o quitá los filtros para ver todos los registros." />
            <B variant="secondary" @click="clearFilters">Limpiar filtros</B>
        </template>
        <EmptyState v-else-if="!rows.length" :title="'Todavía no tenés ' + section"
            text="Cuando haya actividad, aparecerá acá con su estado actualizado."
            :href="listing.create?.href ?? '/solicitudes/nueva'"
            :action="listing.create?.emptyLabel ?? 'Crear una nueva solicitud'" />
    </section>
</template>

<style scoped>
/* Las acciones de cada registro se mantienen juntas y se apilan cuando falta espacio. */
.estimate-row-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; flex-shrink: 0; }
@media (max-width: 600px) { .estimate-row-actions { width: 100%; } }
</style>
