<script setup>
import { VSwitch } from 'vuetify/components';
import F from '../UI/FormField.vue';

defineProps({ quote: { type: Object, required: true } });
const includeVat = defineModel({ type: Boolean });
</script>

<template>
    <section class="vat-settings" aria-label="IVA del presupuesto">
        <VSwitch v-model="includeVat" label="Incluir IVA" color="primary" inset hide-details density="compact" :disabled="quote.processing" />
        <F v-if="includeVat" v-model="quote.tax_rate" label="IVA (%)" type="number" min="0.01" max="100" step="0.01" :error="quote.errors.tax_rate" required />
        <p v-else-if="quote.errors.tax_rate" class="error" role="alert">{{ quote.errors.tax_rate }}</p>
        <p class="editor-hint">{{ includeVat ? 'Se aplica al subtotal de todos los conceptos.' : 'El total se muestra sin IVA agregado.' }}</p>
    </section>
</template>

<style scoped>
.vat-settings { margin: 20px 0; padding: 16px; background: #f5f9fa; border: 1px solid #1f6f7f22; border-radius: 10px; }
.vat-settings :deep(.field) { margin: 12px 0; }
.vat-settings p { margin-bottom: 0; }
.editor-hint { font-size: .9rem; color: #446075; }
</style>
