<script setup>
import {computed} from 'vue';
import {usePage} from '@inertiajs/vue3';
import {VTooltip} from 'vuetify/components';
import AppIcon from './AppIcon.vue';
import {referenceHref} from '../../support/prototype';

// La acción mantiene un nombre accesible y un área de clic de 44 píxeles.
const props = defineProps({
    icono: {type: String, required: true},
    texto: {type: String, required: true},
    etiqueta: String,
    href: String,
    deshabilitada: Boolean,
    variante: String,
});
const emit = defineEmits(['click']);
// Los enlaces de la galería resuelven los mismos destinos que los botones de texto.
const page = usePage();
const destino = computed(() => referenceHref(props.href, page.props));
// El color sigue el propósito de la acción y permite personalizar casos nuevos desde la tabla.
const coloresPorIcono = {eye: 'consulta', file: 'consulta', edit: 'edicion', trash: 'eliminar', share: 'compartir', copy: 'duplicar'};
const varianteVisual = computed(() => props.variante || coloresPorIcono[props.icono] || 'base');
</script>

<template>
    <VTooltip :text="texto" location="top" open-on-focus>
        <template #activator="{props: activador}">
            <component :is="href ? 'a' : 'button'" v-bind="activador" class="accion-icono"
                       :class="'accion-icono--' + varianteVisual"
                       :href="destino" :type="href ? undefined : 'button'"
                       :disabled="!href && deshabilitada ? true : undefined"
                       :aria-label="etiqueta || texto" @click="emit('click', $event)">
                <AppIcon :name="icono"/>
            </component>
        </template>
    </VTooltip>
</template>

<style scoped>
/* El tono suave identifica la función sin competir con el contenido de cada fila. */
.accion-icono {
    display: inline-grid;
    place-items: center;
    width: 44px;
    height: 44px;
    padding: 0;
    border: 1px solid #1f6f7f40;
    border-radius: 8px;
    background: #fff;
    color: #153b64;
    box-shadow: var(--control-shadow);
    cursor: pointer;
}

.accion-icono--consulta {
    color: #1f6f7f;
    background: #1f6f7f0d;
    border-color: #1f6f7f55;
}

.accion-icono--edicion {
    color: #153b64;
    background: #153b640d;
    border-color: #153b644d;
}

.accion-icono--eliminar {
    color: #a13b42;
    background: #a13b420d;
    border-color: #a13b4255;
}

.accion-icono--compartir,
.accion-icono--duplicar {
    color: #1f6f7f;
    background: #2db3a314;
    border-color: #2db3a355;
}

.accion-icono--consulta:hover:not(:disabled) {
    background: #1f6f7f1a;
    border-color: #1f6f7f;
}

.accion-icono--edicion:hover:not(:disabled) {
    background: #153b641a;
    border-color: #153b64;
}

.accion-icono--eliminar:hover:not(:disabled) {
    background: #a13b421a;
    border-color: #a13b42;
}

.accion-icono--compartir:hover:not(:disabled),
.accion-icono--duplicar:hover:not(:disabled) {
    background: #2db3a326;
    border-color: #1f6f7f;
}

.accion-icono--base:hover:not(:disabled) {
    background: #f5f9fa;
    border-color: #1f6f7f;
}

.accion-icono:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

.accion-icono:disabled {
    opacity: .5;
    box-shadow: none;
    cursor: wait;
}
</style>
