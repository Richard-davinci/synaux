<script setup>
import F from '../UI/FormField.vue';
import I from '../UI/AppIcon.vue';

defineProps({
    quote: {type: Object, required: true},
    direct: Boolean,
    configuraPago: Boolean,
    requiereTransferencia: Boolean,
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

    <!-- El importe de cada cuota se ve en el documento y cambia al ajustar los conceptos. -->
    <template v-if="configuraPago">
        <h3>Forma de pago</h3>
        <div class="form-grid">
            <F v-model="quote.modalidad_pago" label="Plan de pago" type="select" :options="[
                {value: 'sena_saldo', label: 'Seña y saldo'},
                {value: 'al_finalizar', label: '100% al finalizar'}
            ]" :error="quote.errors.modalidad_pago" required/>
            <F v-if="quote.modalidad_pago === 'sena_saldo'" v-model="quote.porcentaje_sena" label="Seña (%)"
               type="number" min="1" max="99" step="1" :error="quote.errors.porcentaje_sena" required/>
        </div>
        <F v-if="requiereTransferencia" v-model="quote.instrucciones_transferencia" label="Datos para la transferencia"
           type="textarea" maxlength="1000" hint="Indicá el titular y el alias o CBU de la cuenta que recibirá el pago."
           :error="quote.errors.instrucciones_transferencia" required/>
    </template>
    <F v-model="quote.terms" :label="configuraPago ? 'Otras condiciones y revisiones' : 'Condiciones, forma de pago y revisiones'"
       type="textarea" maxlength="10000" hint="Detallá las entregas, las revisiones incluidas y cuándo comienza el plazo."
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
