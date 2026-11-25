<script setup>
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import SidePanel from '../UI/SidePanel.vue';
import {useSubmit} from '../../support/useSubmit';

const props = defineProps({catalog: {type: Object, required: true}});
const submit = useSubmit();

// El panel guarda la selección por orden y solo ofrece servicios activos de categorías activas.
const featuredOpen = ref(false);
const featuredCategory = ref('');
const featuredForm = useForm({service_ids: []});
const featuredLimit = computed(() => props.catalog.featuredServiceLimit ?? 3);
const activeCategories = computed(() => new Map(props.catalog.categories.filter(row => row.active).map(row => [Number(row.id), row.name])));
const featuredOptions = computed(() => props.catalog.services
    .filter(row => row.active && activeCategories.value.has(Number(row.service_category_id)))
    .sort((a, b) => a.name.localeCompare(b.name, 'es')));
const featuredIds = computed(() => (props.catalog.featuredServiceIds ?? []).map(Number));
const featuredNames = computed(() => featuredIds.value.map(id => props.catalog.services.find(row => Number(row.id) === id)?.name).filter(Boolean));
const featuredError = computed(() => featuredForm.errors.service_ids ?? Object.values(featuredForm.errors)[0]);

// El filtro acota la lista sin perder selecciones de otras categorías.
const featuredCategories = computed(() => {
    const ids = new Set(featuredOptions.value.map(row => Number(row.service_category_id)));
    return [...activeCategories.value].filter(([id]) => ids.has(id)).map(([value, label]) => ({value, label}));
});
const visibleFeaturedOptions = computed(() => featuredOptions.value.filter(service =>
    !featuredCategory.value || Number(service.service_category_id) === Number(featuredCategory.value)));

watch(featuredOpen, open => {
    if (!open) return;
    featuredForm.defaults({service_ids: [...featuredIds.value]});
    featuredForm.reset();
    featuredForm.clearErrors();
    featuredCategory.value = '';
});

function saveFeatured() {
    submit(featuredForm, '/admin/guardar/servicios-destacados', {
        onSuccess: () => { featuredOpen.value = false; },
    });
}
</script>

<template>
    <section class="featured-overview" aria-labelledby="featured-overview-title">
        <div>
            <h2 id="featured-overview-title">Destacados del Inicio</h2>
            <p v-if="featuredNames.length">{{ featuredNames.join(' · ') }}</p>
            <p v-else>Actualmente no hay servicios destacados.</p>
        </div>
        <!-- El contador y los límites se aplican al conjunto, incluso si cambia el filtro de la tabla. -->
        <SidePanel v-model="featuredOpen" title="Servicios destacados" max-width="520"
                   :persistent="featuredForm.processing">
            <template #activator="{ props: activatorProps }">
                <B v-bind="activatorProps" compact variant="secondary">Elegir destacados</B>
            </template>
            <form class="featured-form" @submit.prevent="saveFeatured">
                <p>Elegí hasta {{ featuredLimit }} servicios activos. Se muestran en el Inicio en el orden de selección.</p>
                <p class="featured-count" aria-live="polite">{{ featuredForm.service_ids.length }} de {{ featuredLimit }} seleccionados</p>
                <F v-if="featuredCategories.length" v-model="featuredCategory" label="Categoría" type="select"
                   :options="[{ value: '', label: 'Todas las categorías' }, ...featuredCategories]"
                   :disabled="featuredForm.processing"/>
                <fieldset v-if="visibleFeaturedOptions.length" class="featured-options">
                    <legend>Servicios disponibles</legend>
                    <label v-for="service in visibleFeaturedOptions" :key="service.id">
                        <input v-model="featuredForm.service_ids" type="checkbox" :value="Number(service.id)"
                               :disabled="featuredForm.processing || (featuredForm.service_ids.length >= featuredLimit && !featuredForm.service_ids.includes(Number(service.id)))">
                        <span>{{ service.name }}<small>{{ activeCategories.get(Number(service.service_category_id)) }}</small></span>
                        <span v-if="featuredForm.service_ids.includes(Number(service.id))" class="featured-position">
                            {{ featuredForm.service_ids.indexOf(Number(service.id)) + 1 }}
                        </span>
                    </label>
                </fieldset>
                <p v-else>No hay servicios activos disponibles.</p>
                <p v-if="featuredError" role="alert">{{ featuredError }}</p>
                <div class="form-actions">
                    <B variant="secondary" :disabled="featuredForm.processing" @click="featuredOpen = false">Cancelar</B>
                    <B type="submit" :disabled="featuredForm.processing || !featuredForm.isDirty">
                        {{ featuredForm.processing ? 'Guardando…' : 'Guardar destacados' }}
                    </B>
                </div>
            </form>
        </SidePanel>
    </section>
</template>

<style scoped>
/* La selección se destaca dentro de Administración y mantiene controles nativos accesibles. */
.featured-overview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
    padding: 16px 20px;
    border: 1px solid #1f6f7f26;
    border-radius: 12px;
    background: #f5f9fa;
}

.featured-overview h2 {
    margin: 0 0 4px;
    font-size: 1.15rem;
}

.featured-overview p {
    margin: 0;
    overflow-wrap: anywhere;
}

.featured-form {
    padding: 24px;
}

.featured-count {
    margin-bottom: 12px;
    font-weight: 700;
}

.featured-options {
    padding: 0;
    border: 0;
    min-width: 0;
}

.featured-options legend {
    margin-bottom: 8px;
    font-weight: 700;
}

.featured-options label {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 48px;
    padding: 8px 0;
    border-bottom: 1px solid #1f6f7f26;
}

.featured-options label small {
    display: block;
    color: #446075;
}

.featured-options input {
    accent-color: #1f6f7f;
}

.featured-position {
    margin-inline-start: auto;
    color: #1f6f7f;
    font-weight: 700;
}
</style>
