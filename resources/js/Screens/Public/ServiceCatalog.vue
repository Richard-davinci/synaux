<script setup>
import './public-screen.css';
import {computed, ref, useId} from 'vue';
import {VAutocomplete} from 'vuetify/components';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';
import Empty from '../../Components/UI/EmptyState.vue';
import CatalogServicePrice from '../../Components/Catalog/CatalogServicePrice.vue';
import {categoryIcons} from './serviceCategoryIcons';

// El catálogo filtra los servicios publicados y conserva el segmento seleccionado en cada tarjeta.
const props = defineProps({data: Object});
const query = ref('');
const category = ref('Todos');
const categorySearch = ref('');
const categoryInputId = useId();
// El selector mantiene altura fija aunque crezca el catálogo; las categorías publicadas se ordenan por nombre.
const categories = computed(() => [{value: 'Todos', label: 'Todas las categorías'},
    ...[...new Set(props.data.services.map(service => service.category))].sort((a, b) => a.localeCompare(b, 'es'))
        .map(name => ({value: name, label: name})),
]);
// La búsqueda admite palabras en cualquier orden, con o sin tildes, y respeta el filtro activo.
const normalizeSearch = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
const filterCategory = (value, query) => normalizeSearch(query).trim().split(/\s+/).every(word => normalizeSearch(String(value)).includes(word));
const searchWords = computed(() => normalizeSearch(query.value).trim().split(/\s+/).filter(Boolean));
const services = computed(() => props.data.services.filter(service => {
    if (category.value !== 'Todos' && service.category !== category.value) return false;

    const searchableText = normalizeSearch([service.name, service.category, service.summary].join(' '));
    return searchWords.value.every(word => searchableText.includes(word));
}));
// El enlace del catálogo conserva el segmento elegido y solo transmite su etiqueta, nunca un importe.
const catalogSegments = ref({});

function serviceHref(service) {
    const segment = catalogSegments.value[service.id] ?? 'PyME';
    const available = props.data.references?.[service.id]?.segments ?? [];
    return '/servicios/' + service.id + (available.some(item => item.label === segment) ? '?cliente=' + encodeURIComponent(segment) : '');
}

// El texto inicial destaca el beneficio; los límites del paquete quedan en los entregables y el detalle.
function catalogBenefit(service) {
    if (service.id === 'sitio-institucional' && service.summary === 'Sitio institucional en WordPress de hasta 7 secciones.') {
        return 'Presentá tu organización y facilitá el contacto con tus clientes.';
    }
    return service.summary;
}

const clear = () => {
    query.value = '';
    category.value = 'Todos';
    categorySearch.value = '';
};
</script>

<template>
    <!-- Los filtros se generan a partir de los servicios publicados y no alteran sus precios. -->
    <section data-public-screen class="page shell catalog-page public-typography">
        <!-- La introducción orienta sin desplazar los servicios por debajo del primer pantallazo. -->
        <header class="catalog-intro">
            <T class="public-page-heading" eyebrow="Catálogo de servicios" title="Dale forma a tu próximo proyecto."
               description="Encontrá el servicio que necesitás, explorá su alcance y prepará tu solicitud de presupuesto."/>
        </header>
        <div class="catalog-tools">
            <div class="catalog-tools-row">
                <F v-model="query" type="search" label="Buscar un servicio"
                   placeholder="Ej. tienda online o auditoría SEO"/>
                <!-- La lista buscable evita filas de chips cuando hay muchas categorías. -->
                <div class="category-picker"><label :for="categoryInputId">Categoría</label>
                    <VAutocomplete :id="categoryInputId" :model-value="category" v-model:search="categorySearch"
                                   :items="categories" item-title="label" item-value="value" aria-label="Categoría"
                                   variant="outlined" density="compact" color="primary" hide-details clearable
                                   :custom-filter="filterCategory" :menu-props="{ maxHeight: 260 }"
                                   no-data-text="No encontramos esa categoría."
                                   @update:model-value="category = $event ?? 'Todos'"/>
                </div>
                <button type="button" class="clear-catalog" :disabled="!query && category === 'Todos'" @click="clear">
                    Limpiar filtros
                </button>
            </div>
        </div>
        <div v-if="services.length && data.referenceKey !== 'catalogo-vacio'" class="service-grid">
            <v-card v-for="service in services" :key="service.id" class="card service-card" tag="article">
                <div class="service-category">
                    <I :name="categoryIcons[service.category] ?? 'folder'" class="catalog-service-icon"/>
                    <p class="eyebrow">{{ service.category }}</p>
                </div>
                <h2>{{ service.name }}</h2>
                <p class="service-description">{{ catalogBenefit(service) }}</p>
                <ul v-if="service.includes?.length" class="service-deliverables" aria-label="Incluye">
                    <li v-for="item in service.includes.slice(0, 3)" :key="item"><I name="check"/><span>{{
                            item
                        }}</span></li>
                </ul>
                <CatalogServicePrice v-model="catalogSegments[service.id]" :reference="data.references?.[service.id]"
                                     :service-name="service.name"/>
                <div class="card-link"><B compact :href="serviceHref(service)"
                                          :aria-label="'Ver detalles de ' + service.name" arrow>Ver detalles</B></div>
            </v-card>
        </div>
        <Empty v-else title="No encontramos servicios con esos filtros."
               text="Probá otra búsqueda o volvé a ver todas las propuestas."><B @click="clear">Limpiar filtros</B>
        </Empty>
        <div class="notice"><I name="info"/>
            <p>Cada proyecto tiene su propio alcance. Los entregables, el precio y las condiciones se definen en una
                propuesta profesional.</p></div>
    </section>
</template>

<style scoped>
/* La búsqueda, las categorías y la limpieza comparten un panel con estados de foco visibles. */
/* El encabezado queda liviano: la profundidad visual se reserva para las tarjetas de servicio. */
.catalog-page {
    padding-top: 32px;
}

.catalog-intro {
    padding: 0;
    margin-bottom: 20px;
}

.catalog-intro :deep(.page-title) {
    display: block;
    margin-bottom: 0;
}

.catalog-intro :deep(.page-title > div) {
    width: 100%;
}

.catalog-page .catalog-tools {
    background: #f5f9fa;
    padding: 14px 18px;
    margin-bottom: 24px;
    border-color: #1f6f7f22;
    box-shadow: 0 2px 8px #153b640a;
}

.catalog-page .catalog-tools-row {
    grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
    align-items: end;
    gap: 1rem;
}

/* El buscador y el autocompletado comparten altura y etiquetas alineadas. */
.category-picker {
    display: grid;
    gap: 5px;
    min-width: 0;
}

.category-picker > label {
    font-size: .8rem;
    font-weight: 600;
    color: #153b64;
}

.category-picker :deep(.v-field) {
    border-radius: 8px;
    background: white;
}

.category-picker :deep(.v-field input) {
    min-height: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    padding: 0;
    padding-inline: inherit;
    outline: none;
}

.catalog-page .clear-catalog {
    min-height: 40px;
    align-self: end;
    white-space: nowrap;
}

/* El filtro ocupa menos alto; el buscador y las categorías conservan sus etiquetas y foco visible. */
.catalog-page .catalog-tools :deep(.field) {
    gap: 5px;
    font-size: .8rem;
}

.catalog-page .catalog-tools :deep(input[type='search']) {
    min-height: 40px;
    padding: 7px 10px;
    font-size: .875rem;
}

@media (pointer: coarse) {
    .catalog-page .clear-catalog, .catalog-page .catalog-tools :deep(input[type='search']) {
        min-height: 44px;
    }
}

.clear-catalog {
    min-height: 32px;
    padding: .3rem .65rem;
    border: 1px solid #1f6f7f;
    border-radius: .5rem;
    background: #fff;
    color: #1f6f7f;
    font: inherit;
    font-size: .875rem;
    font-weight: 700;
    cursor: pointer;
}

/* La acción mantiene su lugar para evitar desplazamientos al aplicar o quitar filtros. */
.clear-catalog:hover:not(:disabled) {
    background: #f5f9fa;
}

.clear-catalog:disabled {
    opacity: .5;
    cursor: default;
}

.catalog-page button:focus-visible {
    outline: 2px solid #153b64;
    outline-offset: 3px;
}

/* Cada tarjeta mantiene categoría, alcance y precio en un orden común para facilitar la comparación. */
.catalog-page .service-card {
    min-height: 0;
    padding: 1.25rem;
    border: 1px solid #1f6f7f26;
    border-top: 4px solid #2db3a3;
    box-shadow: 0 2px 5px #153b640a, 0 8px 18px #153b6412;
    border-radius: 18px;
}

.catalog-page .service-card:focus-within {
    border-color: #1f6f7f;
    box-shadow: 0 4px 14px #153b641a;
}

@media (hover: hover) {
    .catalog-page .service-card:hover {
        box-shadow: 0 4px 14px #153b641a;
        border-color: #1f6f7f66;
        border-top-color: #2db3a3;
    }
}

.catalog-page .service-category {
    padding-bottom: 12px;
    border-bottom: 1px solid #1f6f7f1f;
    margin-bottom: 12px;
}

.catalog-page .catalog-service-icon {
    box-sizing: content-box;
    width: 22px;
    height: 22px;
    padding: 9px;
    border-radius: 10px;
    color: #153b64;
    background: #2db3a326;
}

.service-category {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1.25rem;
}

.catalog-page .service-category .eyebrow {
    margin: 0;
    color: #1f6f7f;
    font-size: .75rem;
    letter-spacing: .08em;
}

.catalog-service-icon {
    width: 2rem;
    height: 2rem;
    color: #1f6f7f;
    flex-shrink: 0;
}

.service-description {
    color: #446075;
}

.service-deliverables {
    display: grid;
    gap: .3rem;
    margin: .25rem 0 .5rem;
    padding: 0;
    list-style: none;
}

.service-deliverables li {
    display: flex;
    align-items: start;
    gap: .5rem;
    font-size: .875rem;
    color: #446075;
}

.service-deliverables .icon {
    width: 1.1rem;
    height: 1.1rem;
    margin-top: .15rem;
    color: #1f6f7f;
    flex-shrink: 0;
}

.catalog-page .service-card .card-link {
    margin-top: .75rem;
}

.catalog-page .service-card .card-link .btn {
    width: 100%;
    padding-inline: .9rem;
}

@media (max-width: 1000px) {
    .catalog-page .catalog-tools-row {
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
    }

    .catalog-page .clear-catalog {
        justify-self: end;
    }
}

@media (max-width: 600px) {
    .catalog-page .catalog-tools {
        padding: 14px;
    }

    .catalog-page .service-card {
        padding: 1.15rem;
    }
}

.catalog-page .service-description {
    margin-bottom: 12px;
}
</style>
