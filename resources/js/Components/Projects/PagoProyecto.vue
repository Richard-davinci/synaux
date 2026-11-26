<script setup>
import {computed, ref, useId, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import {money} from '../../support/formatters';

const props = defineProps({
    pago: {type: Object, required: true},
    cuota: {type: Object, required: true},
    proyectoId: {type: Number, required: true},
    moneda: {type: String, required: true},
    puedePresentar: Boolean,
    puedeRevisar: Boolean,
});

// Cada hito conserva su propio comprobante y revisión, sin mezclar los pagos del proyecto.
const archivoId = useId();
const ruta = computed(() => '/proyectos/' + props.proyectoId + '/pagos/' + props.pago.id);
const comprobante = useForm({comprobante: null});
const revision = useForm({decision: '', intento: 0, observacion: ''});
const mostrarObservacion = ref(false);
const estados = {
    pendiente: ['Pendiente', 'pending'],
    en_revision: ['En revisión', 'review'],
    confirmado: ['Confirmado', 'accepted'],
    observado: ['Requiere corrección', 'rejected'],
};
const puedeCargar = computed(() => props.puedePresentar && ['pendiente', 'observado'].includes(props.pago.estado));
const puedeDecidir = computed(() => props.puedeRevisar && props.pago.estado === 'en_revision');

function enviar() {
    comprobante.post(ruta.value + '/comprobante', {preserveScroll: true, onSuccess: () => comprobante.resetAndClearErrors()});
}

function revisar(decision) {
    revision.decision = decision;
    revision.intento = props.pago.intento;
    revision.patch(ruta.value + '/revision', {preserveScroll: true, onSuccess: () => revision.resetAndClearErrors()});
}

watch(() => props.pago.estado, () => { mostrarObservacion.value = false; });
</script>

<template>
    <section class="pago-proyecto">
        <div class="cabecera-pago">
            <div>
                <h3>{{ cuota.titulo }} · {{ money(pago.importe_centavos / 100, moneda) }}</h3>
                <p>{{ cuota.momento }}</p>
            </div>
            <span :class="['status', 'status-' + estados[pago.estado][1]]"><span/>{{ estados[pago.estado][0] }}</span>
        </div>
        <p v-if="pago.observacion" class="notice">{{ pago.observacion }}</p>
        <a v-if="pago.tiene_comprobante" :href="ruta + '/comprobante'">Descargar comprobante</a>

        <form v-if="puedeCargar" class="formulario-pago" @submit.prevent="enviar">
            <label :for="archivoId">{{ pago.estado === 'observado' ? 'Nuevo comprobante' : 'Comprobante de transferencia' }}</label>
            <input :id="archivoId" type="file" accept="application/pdf,image/png,image/jpeg" required
                   @change="comprobante.comprobante = $event.target.files[0] ?? null">
            <small>PDF, JPG o PNG de hasta 5 MB.</small>
            <p v-if="comprobante.errors.comprobante" class="error" role="alert">{{ comprobante.errors.comprobante }}</p>
            <B compact type="submit" :disabled="!comprobante.comprobante || comprobante.processing">
                {{ comprobante.processing ? 'Enviando…' : 'Enviar comprobante' }}
            </B>
        </form>

        <div v-if="puedeDecidir" class="formulario-pago">
            <p>Verificá la acreditación de la transferencia antes de confirmar.</p>
            <p v-if="revision.errors.decision || revision.errors.intento" class="error" role="alert">{{ revision.errors.decision || revision.errors.intento }}</p>
            <div v-if="!mostrarObservacion" class="acciones-pago">
                <B compact :disabled="revision.processing" @click="revisar('confirmado')">Confirmar pago</B>
                <B compact variant="secondary" :disabled="revision.processing" @click="mostrarObservacion = true">Pedir corrección</B>
            </div>
            <form v-else class="formulario-pago" @submit.prevent="revisar('observado')">
                <F v-model="revision.observacion" label="Motivo de la corrección" type="textarea" maxlength="1000" required
                   :error="revision.errors.observacion"/>
                <div class="acciones-pago">
                    <B compact type="submit" :disabled="revision.processing">Enviar observación</B>
                    <B compact variant="secondary" :disabled="revision.processing" @click="mostrarObservacion = false">Cancelar</B>
                </div>
            </form>
        </div>
    </section>
</template>

<style scoped>
/* Las cuotas son legibles en el detalle del proyecto y se apilan cuando falta ancho. */
.pago-proyecto, .formulario-pago {
    display: grid;
    gap: 10px;
    justify-items: start;
    min-width: 0;
}
.pago-proyecto {
    padding: 16px;
    border: 1px solid #1f6f7f26;
    border-radius: 12px;
    overflow-wrap: anywhere;
}
.cabecera-pago, .acciones-pago {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
}
.cabecera-pago { width: 100%; justify-content: space-between; }
.pago-proyecto h3, .pago-proyecto p { margin: 0; }
.pago-proyecto h3 { font-size: 1rem; }
.pago-proyecto a { color: #1f6f7f; text-decoration: underline; }
.formulario-pago, .formulario-pago input[type='file'] { max-width: 100%; }
.formulario-pago small { color: #446075; }
</style>
