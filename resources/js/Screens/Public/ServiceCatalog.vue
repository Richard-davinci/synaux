<script setup>
import './public-screen.css';
import {computed, ref, useId} from 'vue';
import {VAutocomplete} from 'vuetify/components';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';
import Empty from '../../Components/UI/EmptyState.vue';
import TarjetaServicio from '../../Components/Catalog/TarjetaServicio.vue';

// El catálogo filtra los servicios publicados y conserva el segmento seleccionado en cada tarjeta.
const props = defineProps({data: Object});
const query = ref('');
const category = ref('Todos');
const categorySearch = ref('');
const categoryInputId = useId();
const displayCurrency = ref('ARS');
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
const hasCclReference = computed(() => Object.values(props.data.references ?? {}).some(reference => Number(reference.ccl_rate) > 0));
// La selección por servicio se conserva al aplicar los filtros.
const catalogSegments = ref({});

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
            <div v-if="hasCclReference" class="currency-picker" role="group" aria-label="Moneda principal de los precios">
                <span>Mostrar precios en</span>
                <button v-for="currency in ['ARS', 'USD']" :key="currency" type="button"
                        :aria-pressed="displayCurrency === currency" @click="displayCurrency = currency">{{ currency }}</button>
            </div>
        </div>
        <div v-if="services.length && data.referenceKey !== 'catalogo-vacio'" class="service-grid">
            <TarjetaServicio v-for="service in services" :key="service.id" v-model="catalogSegments[service.id]"
                             :service="service" :reference="data.references?.[service.id]" :display-currency="displayCurrency"/>
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

/* El selector cambia la moneda principal y conserva visible el equivalente calculado con el CCL de la versión. */
.currency-picker { display: flex; align-items: center; justify-content: flex-end; gap: 6px; margin-top: 12px; color: #446075; font-size: .8rem; }
.currency-picker button { min-width: 54px; min-height: 32px; border: 1px solid #1f6f7f44; border-radius: 7px; background: white; color: #153b64; font: inherit; font-weight: 700; cursor: pointer; }
.currency-picker button[aria-pressed='true'] { border-color: #1f6f7f; background: #1f6f7f; color: white; }
.catalog-page .price-summary small { color: #446075; font-size: .78rem; }

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
}
</style>
