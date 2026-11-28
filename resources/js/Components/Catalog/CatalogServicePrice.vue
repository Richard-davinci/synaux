<script setup>
import {computed} from 'vue';
import {money} from '../../support/formatters';
import FuenteCotizacionCcl from './FuenteCotizacionCcl.vue';

// Cada tarjeta mantiene su propio segmento. Los importes llegan del tarifario publicado por Laravel.
const props = defineProps({reference: Object, serviceName: String, currency: {type: String, default: 'ARS'}, modelValue: {type: String, default: 'PyME'}});
const emit = defineEmits(['update:modelValue']);
// La tarjeta comunica su selección para que el enlace al detalle conserve el mismo tipo de cliente.
const segment = computed({get: () => props.modelValue, set: value => emit('update:modelValue', value)});
const options = computed(() => props.reference?.segments ?? []);
const price = computed(() => options.value.find(item => item.label === segment.value) ?? options.value[0] ?? props.reference);
// La conversión usa la cotización CCL que quedó guardada con esta versión del tarifario.
const displayedPrice = computed(() => {
    if (!price.value) return null;
    const sourceCurrency = props.reference.currency;
    const rate = Number(props.reference.ccl_rate);
    if (!rate || sourceCurrency === props.currency) return {min: price.value.min, max: price.value.max, currency: sourceCurrency};
    const convert = amount => sourceCurrency === 'USD' ? Number(amount) * rate : Number(amount) / rate;
    return {min: convert(price.value.min), max: convert(price.value.max), currency: props.currency};
});
const secondaryPrice = computed(() => {
    if (!price.value || !props.reference.ccl_rate) return null;
    const sourceCurrency = props.reference.currency;
    const otherCurrency = props.currency === 'ARS' ? 'USD' : 'ARS';
    if (otherCurrency === sourceCurrency) return {min: price.value.min, max: price.value.max, currency: otherCurrency};
    const rate = Number(props.reference.ccl_rate);
    const convert = amount => sourceCurrency === 'USD' ? Number(amount) * rate : Number(amount) / rate;
    return {min: convert(price.value.min), max: convert(price.value.max), currency: otherCurrency};
});
</script>
<template>
    <div class="catalog-price">
        <div v-if="options.length" class="price-segments" role="group"
             :aria-label="'Tipo de cliente para ' + serviceName">
            <button v-for="option in options" :key="option.label" type="button"
                    :aria-pressed="price.label === option.label" @click="segment = option.label">{{ option.label }}
            </button>
        </div>
        <div class="price-summary" aria-live="polite">
            <template v-if="reference">
                <span>Precio estimativo · {{ displayedPrice.currency }}</span>
                <strong>{{ money(displayedPrice.min, displayedPrice.currency) }}
                    <template v-if="Number(displayedPrice.max) !== Number(displayedPrice.min)"> – {{ money(displayedPrice.max, displayedPrice.currency) }}</template>
                </strong>
                <small v-if="secondaryPrice">Equivale a {{ money(secondaryPrice.min, secondaryPrice.currency) }}<template v-if="Number(secondaryPrice.max) !== Number(secondaryPrice.min)"> – {{ money(secondaryPrice.max, secondaryPrice.currency) }}</template></small>
                <FuenteCotizacionCcl v-if="secondaryPrice" :fuente="reference.ccl_source"/>
            </template>
            <template v-else><span>Presupuesto a medida</span><strong>Consultar precio</strong></template>
        </div>
    </div>
</template>
<style scoped>
/* Las referencias mantienen un pie común en las tarjetas y usan únicamente la paleta de synaux. */
.catalog-price {
    margin-top: auto;
    padding-top: .75rem;
}

/* El segmento es un selector secundario; su estado activo no compite con la acción de ver detalles. */
.price-segments {
    display: flex;
    gap: 3px;
    padding: 3px;
    margin-bottom: 10px;
    border: 1px solid #1f6f7f22;
    border-radius: 8px;
    background: #f5f9fa;
}

.price-segments button {
    flex: 1;
    min-width: 0;
    min-height: 30px;
    border: 1px solid transparent;
    border-radius: 5px;
    padding: 4px 5px;
    color: #446075;
    background: transparent;
    font: inherit;
    font-size: .75rem;
    font-weight: 600;
    cursor: pointer;
}

/* El fondo turquesa y el borde firme hacen reconocible la selección sin convertirla en otra acción principal. */
.price-segments button[aria-pressed='true'] {
    background: #2db3a32e;
    color: #153b64;
    border-color: #1f6f7f;
    font-weight: 700;
    box-shadow: none;
}

.price-segments button:hover:not([aria-pressed='true']) {
    color: #153b64;
    background: #fff;
}

@media (pointer: coarse) {
    .price-segments button {
        min-height: 44px;
    }
}

.price-segments button:focus-visible {
    outline: 2px solid #153b64;
    outline-offset: 2px;
}

/* El precio usa el fondo de la tarjeta y una separación simple, sin otro recuadro. */
.price-summary {
    display: grid;
    gap: .2rem;
    padding: 12px 0 0;
    border-top: 1px solid #1f6f7f22;
}

.price-summary span, .price-summary small {
    color: #446075;
    font-size: .8rem;
}

.price-summary small { line-height: 1.4; }

.price-summary strong {
    color: #153b64;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.4rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}
</style>
