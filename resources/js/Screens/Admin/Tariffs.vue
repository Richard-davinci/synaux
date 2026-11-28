<script setup>
import {computed, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

const props = defineProps({data: {type: Object, required: true}});
const submit = useSubmit();
const vigente = computed(() => props.data.administration.currentTariff);
const cotizacion = computed(() => vigente.value?.ccl_rate ?? vigente.value?.source_rate);
const fechaCotizacion = computed(() => vigente.value?.ccl_rate_at ?? vigente.value?.source_checked_at);
const formulario = useForm({ccl_rate: ''});
const cambio = computed(() => formulario.ccl_rate !== '' && Number(formulario.ccl_rate) !== Number(cotizacion.value));

// La cotización guardada se muestra al entrar y se actualiza al confirmar el cambio.
watch(cotizacion, valor => {
    formulario.defaults('ccl_rate', valor ?? '');
    formulario.reset('ccl_rate');
}, {immediate: true});

function actualizar() {
    if (cambio.value) submit(formulario, '/admin/tarifario/ccl');
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Cotización CCL"
           description="Actualizá el valor de referencia que usa el catálogo para mostrar precios en pesos."/>
        <v-card tag="section" class="card ficha-cotizacion" aria-labelledby="cotizacion-vigente">
            <header class="cotizacion-vigente">
                <h2 id="cotizacion-vigente">Cotización vigente</h2>
                <strong v-if="cotizacion">USD 1 = {{ money(cotizacion, 'ARS') }}</strong>
                <p v-else>Todavía no cargaste una cotización.</p>
                <small v-if="cotizacion && fechaCotizacion">Registrada el {{ date(fechaCotizacion) }}</small>
            </header>
            <form class="formulario-cotizacion" @submit.prevent="actualizar">
                <F v-model="formulario.ccl_rate" type="number" label="CCL de venta · ARS por USD" min="0.0001"
                   max="1000000" step="0.0001" required :error="formulario.errors.ccl_rate"
                   hint="Ingresá manualmente el valor que querés usar."/>
                <B type="submit" :disabled="!cambio || formulario.processing">
                    {{ formulario.processing ? 'Actualizando…' : 'Actualizar CCL' }}
                </B>
            </form>
            <p class="cotizacion-ayuda">El cambio actualiza el equivalente en pesos del catálogo. Los importes en USD y las estimaciones ya guardadas conservan su valor.</p>
        </v-card>
    </section>
</template>

<style scoped>
/* El valor vigente, la entrada y la acción comparten una tarjeta corta. */
.ficha-cotizacion { max-width: 680px; padding: clamp(18px, 3vw, 28px); }
.cotizacion-vigente { display: grid; gap: 4px; padding-bottom: 16px; border-bottom: 1px solid #1f6f7f30; }
.cotizacion-vigente h2 { margin: 0; font-size: 1.25rem; }
.cotizacion-vigente strong { font-size: 1.25rem; color: #153b64; }
.cotizacion-vigente p, .cotizacion-vigente small, .cotizacion-ayuda { color: #446075; margin: 0; }
.formulario-cotizacion { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px; margin-top: 16px; }
.formulario-cotizacion :deep(.field) { margin: 0; }
.formulario-cotizacion > button { margin-top: 20px; }
.cotizacion-ayuda { margin-top: 16px; font-size: .9rem; }
@media (max-width: 550px) {
    .formulario-cotizacion { grid-template-columns: minmax(0, 1fr); }
    .formulario-cotizacion > button { justify-self: start; margin-top: 0; }
}
</style>
