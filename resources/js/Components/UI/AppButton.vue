<script setup>
import {computed} from 'vue';
import {usePage} from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import {referenceHref} from '../../support/prototype';
// El mismo control conserva el aspecto del prototipo y adapta los enlaces de la galería de referencia.
const props = defineProps({
    href: String,
    variant: {type: String, default: 'primary'},
    arrow: Boolean,
    compact: Boolean,
    type: {type: String, default: 'button'},
    disabled: Boolean
});
const page = usePage();
const destination = computed(() => referenceHref(props.href, page.props));
</script>
<template>
    <v-btn :href="destination" :type="type" :disabled="disabled"
           :variant="variant === 'ghost' ? 'text' : ['secondary', 'neutro'].includes(variant) ? 'outlined' : 'flat'"
           :class="['btn', `btn-${variant}`, { 'btn-compact': compact }]">
        <slot/>
        <AppIcon v-if="arrow" name="arrow"/>
    </v-btn>
</template>

<style scoped>
/* Los tamaños compartidos son fijos para que las acciones mantengan la misma altura entre pantallas. */
.btn.v-btn {
    min-height: 44px;
    height: 44px;
    padding-block: 0;
}

/* El texto conserva el contraste aunque el botón sea un enlace y reciba estilos generales de navegación. */
.btn-primary.v-btn,
.btn-primary.v-btn:hover,
.btn-primary.v-btn:focus-visible {
    color: #fff;
    background: #153b64;
}

/* Los botones conservan distintos niveles de profundidad según su jerarquía. */
.btn-primary.v-btn {
    box-shadow: var(--primary-button-shadow);
}

.btn-primary.v-btn:hover {
    background: #1f6f7f;
}

.btn-secondary.v-btn {
    color: #153b64;
    background: #fff;
    border-color: #153b64;
    box-shadow: var(--button-shadow);
}

.btn-secondary.v-btn:hover {
    color: #153b64;
    background: #f5f9fa;
}

/* La acción de cancelar queda visible con un tono neutro, sin competir con el envío ni parecer destructiva. */
.btn-neutro.v-btn,
.btn-neutro.v-btn:hover,
.btn-neutro.v-btn:focus-visible {
    color: #446075;
    border-color: #44607566;
    background: #f5f9fa;
    box-shadow: var(--button-shadow);
}

.btn-neutro.v-btn:hover {
    color: #153b64;
    border-color: #1f6f7f80;
}

.btn-ghost.v-btn {
    color: #153b64;
    background: transparent;
    box-shadow: var(--control-shadow);
}

.btn-ghost.v-btn:hover {
    color: #1f6f7f;
    background: #f5f9fa;
}

/* El rojo queda reservado para acciones que cierran o descartan un flujo sin retorno directo. */
.btn-danger.v-btn,
.btn-danger.v-btn:hover,
.btn-danger.v-btn:focus-visible {
    color: #fff;
    background: #a13b42;
}

.btn-danger.v-btn {
    box-shadow: var(--button-shadow);
}

.btn-danger.v-btn:hover {
    background: #842f36;
}

/* El tamaño compacto se reserva para tarjetas y accesos secundarios; en pantallas táctiles mantiene área de toque. */
.btn.v-btn.btn-compact {
    min-height: 36px;
    height: 36px;
    padding: 0 12px;
    font-size: .875rem;
    line-height: 1.4;
    border-radius: 8px;
}

@media (pointer: coarse) {
    .btn.v-btn.btn-compact {
        min-height: 44px;
        height: 44px;
    }
}

.btn.v-btn:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

.btn.v-btn.v-btn--disabled {
    opacity: .5;
    box-shadow: none;
    transform: none;
    cursor: default;
}

@media (prefers-reduced-motion: reduce) {
    .btn.v-btn {
        transition: none;
    }

    .btn.v-btn:hover {
        transform: none;
    }
}
</style>
