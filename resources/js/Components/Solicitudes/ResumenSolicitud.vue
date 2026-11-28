<script setup>
import {computed, ref, watch} from 'vue';
import {useDisplay} from 'vuetify';
import DocumentoPresupuesto from '../Presupuestos/DocumentoPresupuesto.vue';
import AppIcon from '../UI/AppIcon.vue';
import SidePanel from '../UI/SidePanel.vue';
import PrevisualizacionCliente from '../Estimates/PrevisualizacionCliente.vue';
import {referenceLine} from '../../support/calculoPreciosSolicitud';
import {date, money} from '../../support/formatters';

// La vista previa lee los importes del mismo cálculo que usa el formulario.
const props = defineProps({
    form: {type: Object, required: true},
    pricing: {type: Object, required: true},
    selectedService: Object,
    estimacionGuardada: Object,
    extraName: {type: Function, required: true},
});

const {width} = useDisplay();
const movil = computed(() => width.value <= 900);
const abierto = ref(false);
watch(movil, (esMovil) => {
    if (!esMovil) abierto.value = false;
});

function importe(precio, moneda) {
    return precio.min === precio.max
        ? money(precio.min, moneda)
        : money(precio.min, moneda) + ' – ' + money(precio.max, moneda);
}

const documento = computed(() => {
    const {form, pricing, selectedService, extraName} = props;
    const moneda = pricing.summaryCurrency;
    const renglones = [];
    if (selectedService) {
        const linea = referenceLine(pricing.reference, 1, null, moneda);
        const referencia = props.estimacionGuardada
            ? 'Estimación guardada · ' + date(props.estimacionGuardada.created_at)
            : linea.segment;
        renglones.push({
            description: [selectedService.name, referencia].filter(Boolean).join('\n'),
            importe: linea.priced ? importe(linea, moneda)
                : linea.hasKnownAmount ? 'Subtotal conocido: ' + importe(linea, moneda) + ' · saldo a cotizar' : 'A cotizar',
        });
    }

    form.additional_services.forEach((servicio, indice) => {
        // Solo los servicios elegidos tienen un renglón en el documento.
        if (!servicio.service_id) return;
        const linea = pricing.additionalPrices[indice];
        const cantidad = Number(servicio.quantity);
        const valida = Number.isInteger(cantidad) && cantidad >= 1 && cantidad <= 1000;
        const nombre = extraName(servicio.service_id);
        // El multiplicador se conserva en el documento si el borrador ya tenía más de una unidad.
        const concepto = valida && cantidad > 1 ? nombre + ' × ' + cantidad : nombre;
        const referencia = linea?.preliminar ? ['Referencia preliminar', linea.segment].filter(Boolean).join(' · ') : '';
        renglones.push({
            description: [concepto, referencia, servicio.notes?.trim()].filter(Boolean).join('\n'),
            importe: linea?.priced ? importe(linea, moneda)
                : linea?.hasKnownAmount ? 'Subtotal conocido: ' + importe(linea, moneda) + ' · saldo a cotizar'
                    : valida ? 'A cotizar' : 'Completá la cantidad',
        });
    });

    return {title: form.title.trim(), items: renglones};
});

const estimacion = computed(() => {
    const {pricing, form} = props;
    const total = pricing.summaryPrice;
    const preliminar = pricing.preliminaryReference || pricing.pendingExtraCalculation;
    const pendientes = total.pending && documento.value.items.length ? total.pending : 0;

    return {
        alcance: form.description.trim(),
        etiqueta: pendientes && total.hasPrice
            ? (preliminar ? 'Subtotal preliminar' : 'Subtotal estimado')
            : (preliminar ? 'Importe preliminar' : 'Total estimado'),
        importe: total.hasPrice ? importe(total, total.currency) : 'A cotizar',
        pendientes: total.hasPrice && pendientes
            ? (pendientes === 1 ? '1 servicio pendiente queda fuera del subtotal.' : pendientes + ' servicios pendientes quedan fuera del subtotal.')
            : '',
        recalculo: preliminar ? 'El importe se recalcula al continuar con tus respuestas.' : '',
        plazo: form.desired_deadline.trim(),
    };
});

const datosDocumento = computed(() => ({
    budget: documento.value,
    client: props.form.contact,
    estimacion: estimacion.value,
    compact: true,
}));
</script>

<template>
    <!-- En pantallas chicas el mismo documento se abre sin desplazar la etapa del formulario. -->
    <div v-if="movil" class="acceso-vista-previa">
        <SidePanel v-model="abierto" title="Vista previa de tu estimación" max-width="580"
                   body-label="Documento de tu estimación" close-label="Cerrar vista previa">
            <template #activator="{ props: activador }">
                <button v-bind="activador" type="button" class="boton-vista-previa">
                    <AppIcon name="file" aria-hidden="true"/>
                    <span>Ver estimación</span>
                    <AppIcon name="arrow" aria-hidden="true"/>
                </button>
            </template>
            <div class="documento-en-panel">
                <PrevisualizacionCliente :preview="pricing.livePreview" :loading="pricing.previewLoading"
                                         :error="pricing.previewError" :requested="pricing.previewRequested"
                                         empty-message="Elegí un servicio para ver el cálculo actualizado."/>
                <DocumentoPresupuesto v-bind="datosDocumento"/>
            </div>
        </SidePanel>
    </div>
    <aside v-else class="vista-previa-escritorio" aria-label="Vista previa de tu estimación" tabindex="0">
        <PrevisualizacionCliente :preview="pricing.livePreview" :loading="pricing.previewLoading"
                                 :error="pricing.previewError" :requested="pricing.previewRequested"
                                 empty-message="Elegí un servicio para ver el cálculo actualizado."/>
        <DocumentoPresupuesto v-bind="datosDocumento"/>
    </aside>
</template>

<style scoped>
/* El documento se mantiene debajo de los pasos y se desplaza si se agregan muchos servicios. */
.vista-previa-escritorio {
    --tope-vista-previa: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px) + var(--steps-height) + 16px);
    position: sticky;
    top: var(--tope-vista-previa);
    grid-column: 2;
    grid-row: 2;
    min-width: 0;
    max-height: calc(100dvh - var(--tope-vista-previa) - 16px);
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    overflow: auto;
    overscroll-behavior: contain;
}

.vista-previa-escritorio :deep(.estimate-live) {
    margin: 12px;
}

.boton-vista-previa {
    display: flex;
    align-items: center;
    width: 100%;
    min-height: 44px;
    gap: 8px;
    padding: 8px 14px;
    border: 1px solid #1f6f7f4d;
    border-radius: 8px;
    color: #153b64;
    font-weight: 700;
    background: #f5f9fa;
    cursor: pointer;
}

.boton-vista-previa:hover {
    border-color: #1f6f7f;
}

.boton-vista-previa:focus-visible, .vista-previa-escritorio:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.boton-vista-previa .icon {
    width: 18px;
    height: 18px;
}

.boton-vista-previa .icon:last-child {
    margin-left: auto;
}

.documento-en-panel {
    padding: 16px;
}

.documento-en-panel :deep(.estimate-live) {
    margin-bottom: 16px;
}
</style>
