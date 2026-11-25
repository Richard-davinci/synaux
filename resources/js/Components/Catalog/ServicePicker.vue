<script setup>
import {computed, ref, useId, watch} from 'vue';
import {VAutocomplete, VListItem} from 'vuetify/components';
import F from '../UI/FormField.vue';

// Clasifica el catálogo y comunica solo un identificador; los precios siempre se resuelven en Laravel.
const props = defineProps({
    modelValue: [Number, String],
    services: {type: Array, required: true},
    label: {type: String, default: 'Servicio principal'},
    valueKey: {type: String, default: 'database_id'},
    // Permite personalizar la opción inicial de categoría en los servicios adicionales.
    categoryPlaceholder: String,
    error: String,
    disabled: Boolean,
});
const emit = defineEmits(['update:modelValue']);
// Usa el identificador de categoría cuando está disponible y conserva los datos de ejemplos antiguos.
const categoryKey = item => item.service_category_id ?? item.category;
const selected = computed(() => props.services.find(item => item[props.valueKey] == props.modelValue));
const categories = computed(() => [...new Map(props.services.map(item => [
    categoryKey(item), {value: categoryKey(item), label: item.category},
])).values()]);
const category = ref(selected.value ? categoryKey(selected.value) : '');
const search = ref('');
const selectionInvalid = ref(false);
const serviceControl = ref(null);
const serviceInputId = useId();
const available = computed(() => props.services.filter(item => categoryKey(item) === category.value)
    .sort((a, b) => a.name.localeCompare(b.name, 'es')));
const selectionError = computed(() => props.error || (selectionInvalid.value ? 'Elegí un servicio.' : ''));
const normalize = value => String(value ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
const filter = (value, query) => normalize(query).trim().split(/\s+/).every(word => normalize(value).includes(word));
watch(selected, item => {
    selectionInvalid.value = false;
    if (item) category.value = categoryKey(item);
});

function changeCategory(value) {
    category.value = value;
    search.value = '';
    selectionInvalid.value = false;
    if (selected.value && categoryKey(selected.value) !== value) emit('update:modelValue', null);
}

// El navegador valida que haya un servicio seleccionado, incluso si se escribió una búsqueda.
function invalidSelection() {
    selectionInvalid.value = true;
    serviceControl.value?.focus();
}
</script>
<template>
    <div class="service-picker">
        <!-- Categoría y servicio comparten una fila; en móvil se apilan para conservar espacio de escritura. -->
        <F :model-value="category" label="Categoría" type="select" :options="categories" :placeholder="categoryPlaceholder"
           :disabled="disabled" required
           @update:model-value="changeCategory"/>
        <div class="service-picker-main"><label :for="serviceInputId">{{ label }} <span aria-hidden="true">*</span></label>
            <!-- La búsqueda consulta el catálogo y evita que el navegador sugiera búsquedas guardadas. -->
            <VAutocomplete autocomplete="suppress" name="buscar-servicio-catalogo" ref="serviceControl" :id="serviceInputId" :model-value="modelValue" v-model:search="search" :items="available"
                           item-title="name" :item-value="valueKey" :aria-label="label" aria-required="true"
                           placeholder="Buscá o elegí un servicio"
                           variant="outlined" density="compact" color="primary" clearable
                           :disabled="disabled || !available.length" :custom-filter="filter"
                           :menu-props="{maxHeight:280}"
                           :error-messages="selectionError" hide-details="auto"
                           no-data-text="No encontramos servicios con esa búsqueda."
                           @update:model-value="emit('update:modelValue', $event)">
                <template #item="{props: itemProps, item}">
                    <VListItem v-bind="itemProps" :subtitle="item.raw.mode"/>
                </template>
            </VAutocomplete>
            <input type="text" class="service-validity" tabindex="-1" aria-hidden="true" required
                   :value="selected ? 'selected' : ''" :disabled="disabled || !available.length" @invalid.prevent="invalidSelection">
        </div>
        <p v-if="category">{{ available.length }} {{ available.length === 1 ? 'servicio disponible' : 'servicios disponibles' }} · Podés
            buscar por nombre.</p>
    </div>
</template>
<style scoped>
/* La lista tiene altura limitada para que el formulario no crezca con el catálogo. */
.service-picker {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 16px;
    align-items: start;
    margin: 16px 0;
}

.service-picker :deep(.field) {
    margin-bottom: 0;
}

.service-picker-main {
    min-width: 0;
    display: grid;
    gap: 8px;
}

/* El campo de validación no altera el diseño ni recibe foco. */
.service-picker-main .service-validity {
    position: absolute;
    width: 1px;
    height: 1px;
    min-height: 0;
    padding: 0;
    border: 0;
    opacity: 0;
    pointer-events: none;
}

.service-picker-main > label {
    color: #153b64;
    font-size: .9rem;
    font-weight: 600;
}

.service-picker > p {
    grid-column: 1 / -1;
    margin: 0;
    font-size: .85rem;
    color: #446075;
}

@media (max-width: 600px) {
    .service-picker {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
    }
}

.service-picker :deep(.v-field) {
    border-radius: 10px;
    background: white;
}

/* Vuetify posiciona la búsqueda sobre el valor elegido: debe heredar el margen interno para no tocar el borde. */
.service-picker :deep(.v-field input) {
    min-height: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    padding: 0;
    padding-inline: inherit;
    outline: none;
}

.service-picker :deep(.v-messages) {
    font-size: .875rem;
    line-height: 1.45;
    color: #446075;
    opacity: 1;
}
</style>
