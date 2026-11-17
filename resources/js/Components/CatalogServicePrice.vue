<script setup>
import { computed } from 'vue';
import { money } from '../support/synaux';

// Cada tarjeta mantiene su propio segmento. Los importes llegan del tarifario publicado por Laravel.
const props = defineProps({ reference: Object, serviceName: String, modelValue: { type: String, default: 'PyME' } });
const emit = defineEmits(['update:modelValue']);
// La tarjeta comunica su selección para que el enlace al detalle conserve el mismo tipo de cliente.
const segment = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) });
const options = computed(() => props.reference?.segments ?? []);
const price = computed(() => options.value.find(item => item.label === segment.value) ?? options.value[0] ?? props.reference);
</script>
<template>
    <div class="catalog-price">
        <div v-if="options.length" class="price-segments" role="group" :aria-label="'Tipo de cliente para ' + serviceName">
            <button v-for="option in options" :key="option.label" type="button" :aria-pressed="price.label === option.label" @click="segment = option.label">{{ option.label }}</button>
        </div>
        <div class="price-summary" aria-live="polite">
            <template v-if="reference">
                <span>Precio estimativo · {{ reference.currency }}</span>
                <strong>{{ money(price.min, reference.currency) }}<template v-if="price.max !== price.min"> – {{ money(price.max, reference.currency) }}</template></strong>
                <small>{{ options.length ? 'Referencia para ' + price.label : 'Alcance base' }}</small>
            </template>
            <template v-else><span>Presupuesto a medida</span><strong>Consultar precio</strong></template>
        </div>
    </div>
</template>
<style scoped>
/* Las referencias mantienen un pie común en las tarjetas y usan únicamente la paleta de synaux. */
.catalog-price { margin-top: auto; padding-top: 1.25rem; }
.price-segments { display: flex; flex-wrap: wrap; gap: .35rem; padding-bottom: 1rem; }
.price-segments button { flex: 1; min-height: 44px; border: 1px solid #446075; border-radius: .5rem; padding: .4rem .55rem; color: #446075; background: #fff; font: inherit; font-size: .875rem; font-weight: 600; cursor: pointer; }
.price-segments button[aria-pressed='true'] { background: #1f6f7f; border-color: #1f6f7f; color: #fff; }
.price-segments button:hover:not([aria-pressed='true']) { color: #153b64; border-color: #153b64; background: #f5f9fa; }
.price-segments button:focus-visible { outline: 2px solid #153b64; outline-offset: 2px; }
.price-summary { display: grid; gap: .25rem; padding-top: 1rem; border-top: 1px solid rgb(31 111 127 / 18%); }
.price-summary span, .price-summary small { color: #446075; font-size: .875rem; }
.price-summary strong { color: #153b64; font-family: 'Space Grotesk', sans-serif; font-size: 1.4rem; }
</style>
