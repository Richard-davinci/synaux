<script setup>
import {computed} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';

const props = defineProps({
    buscar: {type: String, default: ''},
    estado: {type: String, default: ''},
    placeholder: {type: String, default: 'Título o identificador'},
    etiquetaEstado: {type: String, default: 'Estado'},
    opcionesEstado: {type: Array, default: () => []},
    tipoBusqueda: {type: String, default: 'text'},
    compacto: {type: Boolean, default: false},
    tarjeta: {type: Boolean, default: false},
});
const emit = defineEmits(['update:buscar', 'update:estado']);
const buscar = computed({get: () => props.buscar, set: value => emit('update:buscar', value)});
const estado = computed({get: () => props.estado, set: value => emit('update:estado', value)});
const filtrosActivos = computed(() => Boolean(props.buscar.trim() || props.estado));

function limpiarFiltros() {
    buscar.value = '';
    estado.value = '';
}
</script>

<template>
    <div class="filters workspace-filters filtros-registros"
         :class="{card: tarjeta, 'workspace-filters-single': opcionesEstado.length <= 1}">
        <F v-model="buscar" label="Buscar" :type="tipoBusqueda" :placeholder="placeholder"/>
        <F v-if="opcionesEstado.length > 1" v-model="estado" :label="etiquetaEstado" type="select"
           :options="opcionesEstado"/>
        <B :compact="compacto" variant="secondary" :disabled="!filtrosActivos" @click="limpiarFiltros">
            Limpiar filtros
        </B>
    </div>
</template>

<style scoped>
/* Unifica la búsqueda y el filtro por estado en los listados de Gestión y Mi espacio. */
.filtros-registros {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
    align-items: end;
}

.filtros-registros > :deep(.field) {
    margin-bottom: 0;
}

@media (max-width: 700px) {
    .filtros-registros {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
