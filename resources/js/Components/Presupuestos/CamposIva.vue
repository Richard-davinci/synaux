<script setup>
import {VSwitch} from 'vuetify/components';
import F from '../UI/FormField.vue';

defineProps({quote: {type: Object, required: true}});
const includeVat = defineModel({type: Boolean});
</script>

<template>
    <section class="vat-settings" aria-label="IVA del presupuesto">
        <VSwitch v-model="includeVat" label="Incluir IVA" color="primary" hide-details density="compact"
                 :disabled="quote.processing"/>
        <F v-if="includeVat" v-model="quote.tax_rate" label="IVA (%)" type="number" min="0.01" max="100" step="0.01"
           :error="quote.errors.tax_rate" required/>
        <p v-else-if="quote.errors.tax_rate" class="error" role="alert">{{ quote.errors.tax_rate }}</p>
        <p class="editor-hint">{{
                includeVat ? 'Se aplica al subtotal de todos los conceptos.' : 'El total se muestra sin IVA agregado.'
            }}</p>
    </section>
</template>

<style scoped>
.vat-settings {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 140px;
    align-items: start;
    gap: 8px 16px;
    margin: 20px 0;
    padding: 16px;
    background: #f5f9fa;
    border: 1px solid #1f6f7f22;
    border-radius: 10px;
}

.vat-settings :deep(.field) {
    grid-column: 2;
    grid-row: 1;
    width: 140px;
    max-width: 100%;
    margin: 0;
}

.vat-settings :deep(.v-switch) {
    grid-column: 1;
    grid-row: 1;
    width: max-content;
    max-width: 100%;
    margin: 0;
}

.vat-settings :deep(.v-switch .v-selection-control) {
    min-height: 36px;
}

.vat-settings p {
    grid-column: 1 / -1;
    margin-bottom: 0;
}

.editor-hint {
    font-size: .9rem;
    color: #446075;
}

@media (max-width: 420px) {
    .vat-settings {
        grid-template-columns: minmax(0, 1fr) 112px;
        gap: 8px;
        padding: 12px;
    }

    .vat-settings :deep(.field) {
        width: 112px;
    }
}
</style>
