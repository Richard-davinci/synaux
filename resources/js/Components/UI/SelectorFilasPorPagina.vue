<script setup>
import F from './FormField.vue';

// Unifica la cantidad de filas ofrecida por las tablas de catálogo, equipo y clientes.
defineProps({modelValue: {type: String, required: true}});
const emit = defineEmits(['update:modelValue']);
const opciones = ['5', '10', '15', '20'].map(valor => ({value: valor, label: valor}));
opciones.push({value: 'todos', label: 'Todos'});
</script>

<template>
    <div class="selector-filas">
        <F :model-value="modelValue" label="Filas por página" type="select" :options="opciones"
           @update:model-value="emit('update:modelValue', String($event))"/>
    </div>
</template>

<style scoped>
/* La etiqueta y el campo comparten una línea para alinear el selector con la navegación. */
.selector-filas {
    width: auto;
    flex: 0 0 auto;
}

.selector-filas :deep(.field) {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.selector-filas :deep(.field > label) {
    margin: 0;
    white-space: nowrap;
    font-size: .875rem;
}

.selector-filas :deep(.synaux-field-control) {
    width: 94px;
    flex: 0 0 94px;
}
</style>
