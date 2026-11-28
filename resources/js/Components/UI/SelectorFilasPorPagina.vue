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
/* El selector mantiene una anchura constante y no añade margen al pie de la tabla. */
.selector-filas {
    width: 148px;
    flex: 0 0 auto;
}

.selector-filas :deep(.field) {
    margin: 0;
}
</style>
