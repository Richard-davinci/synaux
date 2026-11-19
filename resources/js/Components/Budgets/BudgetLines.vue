<script setup>
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import { money } from '../../support/synaux';

// Al editar un concepto se conserva su referencia y se actualiza el subtotal de la vista previa.
defineProps({
    quote: { type: Object, required: true },
    lineCents: { type: Function, required: true },
    priceHint: { type: Function, required: true },
});
const emit = defineEmits(['add-item']);
</script>

<template>
    <h2>Definí los servicios</h2><p>Completá el alcance, la cantidad y el precio de cada concepto.</p>
    <div v-for="(item, index) in quote.items" :key="index" class="quote-item">
        <div class="context-heading"><h3>Concepto {{ index + 1 }}</h3><B variant="ghost" :disabled="quote.items.length === 1 || quote.processing" :aria-label="'Quitar concepto ' + (index + 1)" @click="quote.items.splice(index, 1)">Quitar</B></div>
        <F v-model="item.description" label="Servicio y entregables" type="textarea" maxlength="2000" :error="quote.errors['items.' + index + '.description']" required />
        <div class="form-grid"><F v-model="item.quantity" label="Cantidad" type="number" min="0.001" max="10000" step="0.001" :error="quote.errors['items.' + index + '.quantity']" required /><F v-model="item.unit_price" :label="'Precio unitario · ' + quote.currency_code" type="number" min="0" max="100000000" step="0.01" :error="quote.errors['items.' + index + '.unit_price']" :hint="priceHint(item.reference)" required /></div>
        <p class="item-subtotal">Subtotal del concepto <strong>{{ item.unit_price === '' ? 'Pendiente de cotizar' : money(lineCents(item) / 100, quote.currency_code) }}</strong></p>
    </div>
    <B variant="secondary" :disabled="quote.items.length >= 100 || quote.processing" @click="emit('add-item')">Agregar concepto</B>
</template>

<style scoped>
.context-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
.context-heading h3, .context-heading h2 { margin: 0; }
.quote-item { padding: 18px; border: 1px solid #1f6f7f33; border-radius: 14px; margin: 18px 0; }
.item-subtotal { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin: 16px 0 0; }
@media (max-width: 600px) { .quote-item { padding: 14px; } }
</style>
