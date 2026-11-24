<script setup>
import F from '../UI/FormField.vue';
import I from '../UI/AppIcon.vue';

defineProps({
    quote: {type: Object, required: true},
    direct: Boolean,
});
</script>

<template>
    <h2>Plazos y condiciones</h2>
    <div class="form-grid">
        <F v-model="quote.estimated_delivery_days" label="Entrega estimada (días desde el inicio)" type="number" min="1"
           max="3650" step="1" :error="quote.errors.estimated_delivery_days" required/>
        <F v-model="quote.valid_until" label="Presupuesto válido hasta" type="date"
           :min="new Date().toLocaleDateString('en-CA')" :error="quote.errors.valid_until" required/>
    </div>

    <F v-model="quote.terms" label="Condiciones, forma de pago y revisiones" type="textarea" maxlength="10000"
       hint="Detallá el anticipo, las entregas, las revisiones incluidas y cuándo comienza el plazo."
       :error="quote.errors.terms" required/>
    <p class="editor-note"><I name="info"/> {{
            direct ? 'Al emitirlo, vas a poder descargar el PDF para compartirlo con el cliente.' : 'El cliente recibirá esta versión en su cuenta. Podrá descargarla y aceptar o rechazar el presupuesto vigente.'
        }}</p>
</template>

<style scoped>
.editor-note {
    background: #f5f9fa;
    border: 1px solid #1f6f7f22;
    padding: 16px;
    border-radius: 12px;
    margin: 16px 0;
}

.editor-note .icon {
    width: 20px;
    vertical-align: middle;
    margin-right: 6px;
}
</style>
