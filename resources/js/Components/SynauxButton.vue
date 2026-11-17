<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SynauxIcon from './SynauxIcon.vue';
import { referenceHref } from '../support/synaux';
// El mismo control conserva el aspecto del prototipo y adapta los enlaces de la galería de referencia.
const props = defineProps({ href: String, variant: { type: String, default: 'primary' }, arrow: Boolean, type: { type: String, default: 'button' }, disabled: Boolean });
const page = usePage();
const destination = computed(() => referenceHref(props.href, page.props));
</script>
<template>
    <v-btn :href="destination" :type="type" :disabled="disabled" :variant="variant === 'ghost' ? 'text' : variant === 'secondary' ? 'outlined' : 'flat'" :class="['btn', `btn-${variant}`]">
        <slot /><SynauxIcon v-if="arrow" name="arrow" />
    </v-btn>
</template>

<style scoped>
/* El texto conserva el contraste aunque el botón sea un enlace y reciba estilos generales de navegación. */
.btn-primary.v-btn,
.btn-primary.v-btn:hover,
.btn-primary.v-btn:focus-visible {
    color: #fff;
    background: #153b64;
}
.btn-primary.v-btn:hover { background: #1f6f7f; }
.btn-secondary.v-btn { color: #153b64; background: #fff; border-color: #153b64; }
.btn-secondary.v-btn:hover { color: #153b64; background: #f5f9fa; }
.btn-ghost.v-btn { color: #153b64; background: transparent; }
.btn-ghost.v-btn:hover { color: #1f6f7f; background: #f5f9fa; }
.btn.v-btn:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 3px; }
.btn.v-btn.v-btn--disabled { opacity: .5; box-shadow: none; transform: none; cursor: default; }
@media (prefers-reduced-motion: reduce) { .btn.v-btn { transition: none; } .btn.v-btn:hover { transform: none; } }
</style>