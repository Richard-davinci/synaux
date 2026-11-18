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
.catalog-price { margin-top: auto; padding-top: .75rem; }
/* El segmento es un selector secundario; su estado activo no compite con la acción de ver detalles. */
.price-segments { display: flex; gap: 3px; padding: 3px; margin-bottom: 10px; border: 1px solid #1f6f7f22; border-radius: 8px; background: #f5f9fa; }
.price-segments button { flex: 1; min-width: 0; min-height: 30px; border: 0; border-radius: 5px; padding: 4px 5px; color: #446075; background: transparent; font: inherit; font-size: .75rem; font-weight: 600; cursor: pointer; }
.price-segments button[aria-pressed='true'] { background: #fff; color: #153b64; box-shadow: 0 1px 4px #153b6426; }
.price-segments button:hover:not([aria-pressed='true']) { color: #153b64; background: #fff; }
@media (pointer: coarse) { .price-segments button { min-height: 44px; } }
.price-segments button:focus-visible { outline: 2px solid #153b64; outline-offset: 2px; }
/* El precio ocupa una superficie propia y conserva contraste con los controles de segmento. */
.price-summary { display: grid; gap: .2rem; padding: 12px; background: #f5f9fa; border: 1px solid #1f6f7f22; border-radius: 12px; }
.price-summary span, .price-summary small { color: #446075; font-size: .8rem; }
.price-summary strong { color: #153b64; font-family: 'Space Grotesk', sans-serif; font-size: 1.4rem; line-height: 1.25; overflow-wrap: anywhere; }
</style>
