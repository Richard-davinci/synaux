<script setup>
import AppIcon from '../UI/AppIcon.vue';
import {referenceHref} from '../../support/prototype';

// Los resúmenes usan el mismo conjunto de indicadores y cada tarjeta abre su listado.
defineProps({
    indicadores: {type: Array, required: true},
    rutaBase: {type: String, required: true},
    contexto: {type: Object, required: true},
});
</script>

<template>
    <ul class="indicadores-panel">
        <li v-for="[etiqueta, ruta, icono, cantidad] in indicadores" :key="ruta">
            <a class="workspace-metric" :href="referenceHref(rutaBase + '/' + ruta, contexto)">
                <AppIcon :name="icono"/>
                <span>{{ etiqueta }}</span>
                <strong>{{ cantidad }}</strong>
            </a>
        </li>
    </ul>
</template>
