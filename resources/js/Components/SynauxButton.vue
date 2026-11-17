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
