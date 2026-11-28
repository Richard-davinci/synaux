<script setup>
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {useSubmit} from '../../support/useSubmit';
import FuenteCotizacionCcl from '../../Components/Catalog/FuenteCotizacionCcl.vue';

const props = defineProps({data: {type: Object, required: true}});
const submit = useSubmit();
const vigente = computed(() => props.data.administration.currentCcl);
const manual = useForm({ccl_rate: ''});
const aplicacion = useForm({token: ''});
const propuesta = ref(null);
const consultando = ref(false);
const errorConsulta = ref('');
const mostrarManual = ref(false);
const ocupado = computed(() => consultando.value || manual.processing || aplicacion.processing);
const mismoDato = computed(() => vigente.value?.fuente === 'dolarapi'
    && vigente.value.fecha_fuente === propuesta.value?.fecha_fuente
    && Number(vigente.value.valor) === Number(propuesta.value?.valor));
const formatoValor = new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2, maximumFractionDigits: 4,
});
const formatoFecha = new Intl.DateTimeFormat('es-AR', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Argentina/Buenos_Aires',
});

// La consulta y la edición manual no cambian el valor vigente hasta el envío explícito.
watch(vigente, valor => {
    manual.defaults('ccl_rate', valor?.valor ?? '');
    manual.reset('ccl_rate');
}, {immediate: true});

async function consultar() {
    if (props.data.preview) {
        errorConsulta.value = 'La consulta está disponible desde la cuenta de Administración.';
        return;
    }
    consultando.value = true;
    propuesta.value = null;
    errorConsulta.value = '';
    aplicacion.clearErrors();
    try {
        const respuesta = await fetch('/admin/tarifario/ccl', {
            headers: {Accept: 'application/json'}, cache: 'no-store',
        });
        const datos = await respuesta.json();
        if (respuesta.ok && datos.token) propuesta.value = datos;
        else errorConsulta.value = respuesta.status === 503 ? datos.message : 'No pudimos completar la consulta. Probá de nuevo.';
    } catch {
        errorConsulta.value = 'No pudimos completar la consulta. Probá de nuevo.';
    } finally {
        consultando.value = false;
    }
}

function aplicar() {
    if (!propuesta.value || mismoDato.value || ocupado.value) return;
    aplicacion.token = propuesta.value.token;
    submit(aplicacion, '/admin/tarifario/ccl', {
        onSuccess: () => { propuesta.value = null; },
        onError: () => { propuesta.value = null; },
    });
}

function actualizarManual() {
    if (ocupado.value) return;
    submit(manual, '/admin/tarifario/ccl', {
        onSuccess: () => {
            mostrarManual.value = false;
            propuesta.value = null;
            errorConsulta.value = '';
            aplicacion.clearErrors();
        },
    });
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Cotización CCL"
           description="Administrá el valor de referencia que usa el catálogo para mostrar precios en pesos."/>
        <v-card tag="section" class="card ficha-cotizacion" aria-labelledby="cotizacion-vigente">
            <div class="comparacion-cotizacion" :class="{'con-propuesta': propuesta}">
                <div class="valor-cotizacion vigente">
                    <h2 id="cotizacion-vigente">CCL aplicado al catálogo</h2>
                    <template v-if="vigente">
                        <strong>USD 1 = {{ formatoValor.format(vigente.valor) }}</strong>
                        <FuenteCotizacionCcl v-if="vigente.fuente === 'dolarapi'" :fuente="vigente.fuente"/>
                        <small v-else>{{ vigente.fuente === 'manual' ? 'Carga manual' : 'Cotización registrada' }}</small>
                        <small v-if="vigente.fecha_fuente">Dato del {{ formatoFecha.format(new Date(vigente.fecha_fuente)) }}</small>
                        <small v-if="vigente.aplicada_en">Aplicada el {{ formatoFecha.format(new Date(vigente.aplicada_en)) }}</small>
                    </template>
                    <p v-else>Todavía no hay una cotización aplicada.</p>
                </div>
                <div v-if="propuesta" class="valor-cotizacion" aria-live="polite">
                    <h2>CCL de venta consultado</h2>
                    <strong>USD 1 = {{ formatoValor.format(propuesta.valor) }}</strong>
                    <FuenteCotizacionCcl fuente="dolarapi"/>
                    <small>Dato del {{ formatoFecha.format(new Date(propuesta.fecha_fuente)) }}</small>
                    <small v-if="mismoDato">Este dato ya está aplicado.</small>
                </div>
            </div>
            <p class="cotizacion-ayuda">Al aplicar un nuevo valor, se actualiza la referencia en pesos de todo el catálogo.</p>
            <div class="acciones-cotizacion">
                <B :variant="propuesta && !mismoDato ? 'secondary' : 'primary'" :disabled="ocupado" @click="consultar">
                    {{ consultando ? 'Consultando…' : 'Consultar CCL' }}
                </B>
                <B v-if="propuesta && !mismoDato" :disabled="ocupado" @click="aplicar">
                    {{ aplicacion.processing ? 'Aplicando…' : 'Aplicar cotización' }}
                </B>
            </div>
            <p v-if="errorConsulta || aplicacion.errors.cotizacion" class="notice" role="alert">
                {{ errorConsulta || aplicacion.errors.cotizacion }}
            </p>
            <details class="carga-manual" :open="mostrarManual" @toggle="mostrarManual = $event.target.open">
                <summary>Ingresar manualmente</summary>
                <form class="formulario-cotizacion" @submit.prevent="actualizarManual">
                    <F v-model="manual.ccl_rate" type="number" label="CCL de venta · ARS por USD" min="0.0001"
                       max="1000000" step="0.0001" required :error="manual.errors.ccl_rate"/>
                    <B type="submit" variant="secondary" :disabled="ocupado || manual.ccl_rate === ''">
                        {{ manual.processing ? 'Aplicando…' : 'Aplicar valor manual' }}
                    </B>
                </form>
            </details>
        </v-card>
    </section>
</template>

<style scoped>
/* La consulta aparece junto al valor aplicado para comparar antes de guardar. */
.ficha-cotizacion { max-width: 760px; padding: clamp(18px, 3vw, 28px); }
.comparacion-cotizacion { display: grid; gap: 14px; }
.comparacion-cotizacion.con-propuesta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.valor-cotizacion { align-content: start; display: grid; gap: 4px; border: 1px solid #1f6f7f30; border-radius: 12px; padding: 16px; }
.valor-cotizacion.vigente { background: #f5f9fa; }
.valor-cotizacion h2 { margin: 0; font-size: 1rem; }
.valor-cotizacion strong { font-size: 1.3rem; color: #153b64; overflow-wrap: anywhere; }
.valor-cotizacion p, .valor-cotizacion small, .cotizacion-ayuda { color: #446075; margin: 0; }
.acciones-cotizacion { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.carga-manual { padding-top: 14px; margin-top: 18px; border-top: 1px solid #1f6f7f30; }
.carga-manual summary { cursor: pointer; color: #153b64; font-weight: 700; }
.formulario-cotizacion { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px; margin-top: 12px; }
.formulario-cotizacion :deep(.field) { margin: 0; }
.formulario-cotizacion > button { margin-top: 20px; }
.cotizacion-ayuda { margin-top: 12px; font-size: .9rem; }
@media (max-width: 550px) {
    .formulario-cotizacion, .comparacion-cotizacion.con-propuesta { grid-template-columns: minmax(0, 1fr); }
    .formulario-cotizacion > button { justify-self: start; margin-top: 0; }
}
</style>
