<script setup>
import {computed, ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import F from '../../Components/UI/FormField.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import {date} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});

// La búsqueda y el orden se aplican a las oportunidades públicas recibidas.
const query = ref('');
const category = ref('Todas');
const order = ref('recent');
const categories = computed(() => [
    'Todas',
    ...new Set((props.data.opportunities ?? []).map(record => record.category).filter(Boolean)),
]);
const opportunities = computed(() => {
    const search = query.value.toLowerCase();
    return (props.data.opportunities ?? [])
        .filter(record => (category.value === 'Todas' || record.category === category.value)
            && `${record.title} ${record.description} ${record.skills.join(' ')}`.toLowerCase().includes(search))
        .sort((a, b) => order.value === 'proposals'
            ? b.proposal_count - a.proposal_count
            : new Date(b.published_at) - new Date(a.published_at));
});

function clearFilters() {
    query.value = '';
    category.value = 'Todas';
}
</script>

<template>
    <section class="page shell">
        <T eyebrow="Conectamos proyectos y talento" title="Proyectos que buscan talento digital"
           description="Explorá necesidades publicadas por clientes. Solo se muestran datos públicos; la información de contacto permanece privada.">
            <B href="/solicitudes/nueva" arrow>Publicar solicitud</B></T>
        <div class="filters wall-filters">
            <F v-model="query" label="Buscar oportunidades" placeholder="Proyecto, habilidad o palabra clave"/>
            <F v-model="category" label="Categoría" type="select" :options="categories"/>
            <F v-model="order" label="Ordenar por" type="select"
               :options="[{value:'recent',label:'Más recientes'},{value:'proposals',label:'Más propuestas'}]"/>
        </div>
        <div class="notice">
            <p>Las publicaciones son solicitudes de proyectos. Una propuesta no reemplaza el presupuesto formal ni
                confirma una contratación.</p>
        </div>
        <div v-if="data.referenceKey === 'muro-cargando'" class="opportunity-grid" role="status"
             aria-label="Cargando oportunidades">
            <v-card v-for="n in 3" :key="n" class="card skeleton-card">
                <div class="skeleton"/>
                <div class="skeleton"/>
                <div class="skeleton"/>
            </v-card>
        </div>
        <div v-else-if="opportunities.length && !['muro-vacio','muro-sin-resultados'].includes(data.referenceKey)"
             class="opportunity-grid">
            <v-card v-for="r in opportunities" :key="r.id" class="card opportunity-card">
                <div class="section-head">
                    <span class="eyebrow">{{ r.category }}</span>
                    <S :status="r.status"/>
                </div>
                <h2>{{ r.title }}</h2>
                <p>{{ r.description }}</p>
                <div class="tags">
                    <span v-for="tag in r.skills" :key="tag">{{ tag }}</span>
                </div>
                <dl class="data-list">
                    <div>
                        <dt>Referencia</dt>
                        <dd>{{ r.budget_reference || 'A definir' }}</dd>
                    </div>
                    <div>
                        <dt>Plazo</dt>
                        <dd>{{ r.desired_deadline || 'A coordinar' }}</dd>
                    </div>
                </dl>
                <div class="section-head">
                    <small>{{ date(r.published_at) }} · {{ r.proposal_count }} propuestas</small>
                    <B :href="`/oportunidades/${r.id}`" variant="ghost" arrow>Ver oportunidad</B>
                </div>
            </v-card>
        </div>
        <E v-else
           :title="query || category !== 'Todas' || data.referenceKey === 'muro-sin-resultados' ? 'No encontramos oportunidades con esos filtros' : 'Todavía no hay oportunidades publicadas'"
           text="Probá una nueva búsqueda o volvé más adelante.">
            <B variant="secondary" @click="clearFilters">Limpiar filtros</B></E>
    </section>
</template>
