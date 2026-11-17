<script setup>
import { computed, ref, watch } from 'vue';
import { VAutocomplete, VListItem } from 'vuetify/components';
import F from './SynauxField.vue';

// Clasifica el catálogo y comunica solo un identificador; los precios siempre se resuelven en Laravel.
const props = defineProps({ modelValue: [Number, String], services: Array, error: String, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
const selected = computed(() => props.services.find(item => item.database_id == props.modelValue));
const categories = computed(() => [...new Set(props.services.map(item => item.category))]);
const category = ref(selected.value?.category ?? categories.value[0] ?? '');
const search = ref('');
const available = computed(() => props.services.filter(item => item.category === category.value).sort((a,b) => a.name.localeCompare(b.name, 'es')));
const normalize = value => String(value ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
const filter = (value, query) => normalize(query).trim().split(/\s+/).every(word => normalize(value).includes(word));
watch(selected, item => { if (item) category.value = item.category; });
function changeCategory(value) {
    category.value = value;
    search.value = '';
    if (selected.value?.category !== value) emit('update:modelValue', null);
}
</script>
<template>
    <div class="service-picker">
        <F :model-value="category" label="Categoría" type="select" :options="categories" :disabled="disabled" required @update:model-value="changeCategory" />
        <p>{{ available.length }} {{ available.length === 1 ? 'servicio disponible' : 'servicios disponibles' }} en esta categoría.</p>
        <VAutocomplete :model-value="modelValue" v-model:search="search" :items="available" item-title="name" item-value="database_id" label="Servicio principal" placeholder="Buscá o elegí un servicio"
            variant="outlined" density="comfortable" color="primary" clearable :disabled="disabled || !available.length" :custom-filter="filter" :menu-props="{maxHeight:280}"
            :error-messages="error" hint="Podés buscar por nombre, con o sin tildes." persistent-hint no-data-text="No encontramos servicios con esa búsqueda."
            @update:model-value="emit('update:modelValue', $event)">
            <template #item="{props: itemProps, item}"><VListItem v-bind="itemProps" :subtitle="item.raw.mode" /></template>
        </VAutocomplete>
    </div>
</template>
<style scoped>
/* La lista tiene altura limitada para que el formulario no crezca con el catálogo. */
.service-picker { margin: 20px 0; }
.service-picker > p { margin: -4px 0 20px; font-size: .9rem; color: #446075; }
.service-picker :deep(.v-field) { border-radius: 10px; background: white; }
.service-picker :deep(.v-field input) { min-height: 0; border: 0; border-radius: 0; background: transparent; padding: 0; outline: none; }
.service-picker :deep(.v-messages) { font-size: .875rem; line-height: 1.45; color: #446075; opacity: 1; }
</style>
