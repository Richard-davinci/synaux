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
});
const emit = defineEmits(['click']);
// Los enlaces de la galería resuelven los mismos destinos que los botones de texto.
const page = usePage();
const destino = computed(() => referenceHref(props.href, page.props));
</script>

<template>
    <VTooltip :text="texto" location="top" open-on-focus>
        <template #activator="{props: activador}">
            <component :is="href ? 'a' : 'button'" v-bind="activador" class="accion-icono"
                       :href="destino" :type="href ? undefined : 'button'"
                       :disabled="!href && deshabilitada ? true : undefined"
                       :aria-label="etiqueta || texto" @click="emit('click', $event)">
                <AppIcon :name="icono"/>
            </component>
        </template>
    </VTooltip>
</template>

<style scoped>
/* El indicador de foco y el fondo al pasar el mouse usan los colores de los controles del panel. */
.accion-icono {
    display: inline-grid;
    place-items: center;
    width: 44px;
    height: 44px;
    padding: 0;
    border: 1px solid #1f6f7f33;
    border-radius: 8px;
    background: #fff;
    color: #153b64;
    box-shadow: var(--control-shadow);
    cursor: pointer;
}

.accion-icono:hover:not(:disabled) {
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
