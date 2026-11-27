<script setup>
import {computed} from 'vue';
import AppButton from '../UI/AppButton.vue';
import AppIcon from '../UI/AppIcon.vue';
import CatalogServicePrice from './CatalogServicePrice.vue';
import {categoryIcons} from '../../Screens/Public/serviceCategoryIcons';

const props = defineProps({
    service: {type: Object, required: true},
    reference: Object,
    headingTag: {type: String, default: 'h2'},
});

// La selección se comparte con el precio y se conserva al aplicar filtros en el catálogo.
const segment = defineModel({type: String, default: 'PyME'});
const detailHref = computed(() => {
    const path = '/servicios/' + encodeURIComponent(props.service.id);
    const available = props.reference?.segments ?? [];
    return available.some(item => item.label === segment.value)
        ? path + '?cliente=' + encodeURIComponent(segment.value)
        : path;
});

// La descripción común prioriza el beneficio y deja los límites del paquete en el alcance.
const description = computed(() => props.service.id === 'sitio-institucional'
&& props.service.summary === 'Sitio institucional en WordPress de hasta 7 secciones.'
    ? 'Presentá tu organización y facilitá el contacto con tus clientes.'
    : props.service.summary);
</script>

<template>
    <!-- Inicio y catálogo usan el mismo orden de categoría, alcance, precio y acción. -->
    <v-card class="card service-card" tag="article">
        <div class="service-category">
            <AppIcon :name="categoryIcons[service.category] ?? 'folder'" class="catalog-service-icon"/>
            <p class="eyebrow">{{ service.category }}</p>
        </div>
        <component :is="headingTag" class="catalog-service-title">{{ service.name }}</component>
        <p class="service-description">{{ description }}</p>
        <ul v-if="service.includes?.length" class="service-deliverables" aria-label="Incluye">
            <li v-for="item in service.includes.slice(0, 3)" :key="item">
                <AppIcon name="check"/>
                <span>{{ item }}</span>
            </li>
        </ul>
        <CatalogServicePrice v-model="segment" :reference="reference" :service-name="service.name"/>
        <div class="card-link">
            <AppButton compact :href="detailHref" :aria-label="'Ver detalles de ' + service.name" arrow>Ver detalles
            </AppButton>
        </div>
    </v-card>
</template>

<style scoped>
/* Cada tarjeta mantiene categoría, alcance y precio en un orden común para facilitar la comparación. */
.service-card.card {
    min-height: 0;
    padding: 1.25rem;
    border: 1px solid #1f6f7f26;
    border-top: 4px solid #2db3a3;
    box-shadow: 0 2px 5px #153b640a, 0 8px 18px #153b6412;
    border-radius: 18px;
}

.service-card.card:focus-within {
    border-color: #1f6f7f;
    box-shadow: 0 4px 14px #153b641a;
}

@media (hover: hover) {
    .service-card.card:hover {
        box-shadow: 0 4px 14px #153b641a;
        border-color: #1f6f7f66;
        border-top-color: #2db3a3;
    }
}

.service-category {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding-bottom: 12px;
    border-bottom: 1px solid #1f6f7f1f;
    margin-bottom: 12px;
}

.catalog-service-icon {
    box-sizing: content-box;
    width: 22px;
    height: 22px;
    padding: 9px;
    border-radius: 10px;
    color: #153b64;
    background: #2db3a326;
    flex-shrink: 0;
}

.service-category .eyebrow {
    margin: 0;
    color: #1f6f7f;
    font-size: .75rem;
    letter-spacing: .08em;
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

.service-card .card-link {
    margin-top: .75rem;
}

.service-card .card-link :deep(.btn) {
    width: 100%;
    padding-inline: .9rem;
}

.service-card .catalog-service-title {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.25;
    margin: 0 0 8px;
    text-wrap: balance;
}

.service-card .service-description {
    font-size: 1rem;
    line-height: 1.55;
    margin-bottom: 12px;
    text-wrap: pretty;
}

@media (max-width: 600px) {
    .service-card.card {
        padding: 1.15rem;
    }
}
</style>
