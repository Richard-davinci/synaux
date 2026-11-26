<script setup>
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import BudgetLines from './ConceptosPresupuesto.vue';
import BudgetPreviewPanel from './VistaPreviaPresupuesto.vue';
import BudgetSourceFields from './OrigenPresupuesto.vue';
import BudgetTerms from './CondicionesPresupuesto.vue';
import BudgetVatFields from './CamposIva.vue';
import MigasNavegacion from '../Navigation/MigasNavegacion.vue';
import {money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';
import {referenceLine, requestBudgetLines} from '../../support/calculoPreciosSolicitud';

// El editor recibe solo las solicitudes autorizadas; Laravel vuelve a comprobar permisos y valores al guardar.
const props = defineProps({data: Object});
const selected = props.data.selected ?? {};
const submit = useSubmit();
const step = ref(1);
const preview = ref(false);
const previewView = ref('presupuesto');
// El presupuesto directo solicita solo los datos del destinatario y comienza con un concepto editable.
const direct = Boolean(props.data.directBudget);
const configuraPago = props.data.area === 'gestion' && !props.data.preview;
const labels = [direct ? 'Cliente' : 'Solicitud', 'Servicios', 'Condiciones'];
const emptyItem = () => ({description: '', quantity: 1, unit_price: ''});
// Un renglón del presupuesto representa un concepto completo; al editar se integra cualquier cantidad previa al importe.
const itemComoConcepto = item => ({
    description: item.description,
    quantity: 1,
    unit_price: item.unit_price === '' || item.unit_price == null ? ''
        : Math.round(Number(item.quantity || 1) * Number(item.unit_price) * 100) / 100,
});
const quote = useForm({
    budget_id: selected.budget_id ?? null,
    direct,
    request_id: direct ? null : (selected.request_id ?? props.data.requestId ?? ''),
    client: direct ? {name: '', email: '', company: '', phone: '', address: '', ...selected.client} : null,
    title: selected.title ?? '',
    currency_code: selected.currency_code ?? 'ARS',
    tax_rate: selected.tax_rate ?? 0,
    valid_until: selected.valid_until ?? new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
    estimated_delivery_days: selected.estimated_delivery_days ?? 30,
    terms: selected.terms ?? '',
    ...(configuraPago ? {
        modalidad_pago: selected.plan_pago?.modalidad ?? 'sena_saldo',
        porcentaje_sena: selected.plan_pago?.porcentaje_sena || 50,
        momento_hito_intermedio: selected.plan_pago?.cuotas?.find(cuota => cuota.concepto === 'hito_intermedio')?.momento
            ?? 'Al aprobar el diseño y la versión funcional de prueba',
        momento_pago_final: selected.plan_pago?.cuotas?.find(cuota => cuota.concepto === 'saldo_final')?.momento
            ?? 'Al aprobar la tienda terminada, antes de publicarla y entregar los accesos',
        instrucciones_transferencia: selected.plan_pago?.instrucciones ?? '',
    } : {}),
    items: selected.items?.map(itemComoConcepto) ?? (direct ? [emptyItem()] : []),
    send: false,
});
// El interruptor aplica IVA a todo el presupuesto; desactivarlo conserva la tasa para volver a incluirlo.
const previousVatRate = ref(Number(quote.tax_rate) || 21);
const includeVat = computed({
    get: () => Number(quote.tax_rate) > 0,
    set: enabled => {
        if (enabled) quote.tax_rate = previousVatRate.value;
        else {
            previousVatRate.value = Number(quote.tax_rate) || previousVatRate.value;
            quote.tax_rate = 0;
        }
    },
});
const requests = computed(() => props.data.budgetRequests ?? props.data.requests ?? []);
const source = computed(() => requests.value.find(item => item.id == quote.request_id));
// Las migas muestran el origen del presupuesto y reemplazan el botón de regreso aislado.
const migas = computed(() => direct || !source.value
    ? [['Gestión', '/gestion'], ['Presupuestos', '/gestion/presupuestos'], [selected.budget_id ? 'Presupuesto directo' : 'Nuevo presupuesto']]
    : [['Gestión', '/gestion'], ['Solicitudes', '/gestion/solicitudes'],
        [`SOL-${source.value.id} · ${source.value.title}`, '/solicitudes/' + source.value.id],
        [selected.budget_id ? 'Presupuesto' : 'Preparar presupuesto']]);
const requestOptions = computed(() => requests.value.filter(item =>
    (item.status === 'open' && item.user_id !== props.data.auth?.id) || item.id == selected.request_id
).map(item => ({value: item.id, label: 'SOL-' + item.id + ' · ' + item.title})));
const client = computed(() => direct ? quote.client : (source.value?.contact ?? selected.client));
const issuer = computed(() => props.data.issuerDetails ?? ({
    name: props.data.profile?.brand_name ?? selected.issuer?.name ?? 'synaux',
    contact: props.data.auth?.name ?? selected.issuer?.contact,
    email: props.data.auth?.email ?? selected.issuer?.email,
}));
const canPrepare = computed(() => direct ? Boolean(quote.client.name?.trim()) : source.value?.status === 'open');
// La disponibilidad del emisor la determina el servidor, también para el primer presupuesto de un integrante.
const needsIssuer = computed(() => !props.data.preview && !(props.data.emisorConfigurado ?? Boolean(props.data.profile)));
const emiteOrganizacion = computed(() => props.data.area === 'gestion');

// Cada concepto se redondea a centavos, igual que el documento calculado por el servidor.
const lineCents = item => Math.round(Number(item.quantity || 0) * Number(item.unit_price || 0) * 100);
const subtotalCents = computed(() => quote.items.reduce((sum, item) => sum + lineCents(item), 0));
const subtotal = computed(() => subtotalCents.value / 100);
const totalCentavos = computed(() => Math.round(subtotalCents.value * (1 + Number(quote.tax_rate || 0) / 100)));
const total = computed(() => totalCentavos.value / 100);
// La vista previa distribuye el mismo total en centavos que confirma el servidor.
const planPago = computed(() => {
    if (!configuraPago) return selected.plan_pago;
    const porcentaje = ['sena_saldo', 'anticipo_dos_hitos'].includes(quote.modalidad_pago)
        ? Number(quote.porcentaje_sena) : 0;
    const sena = Math.round(totalCentavos.value * porcentaje / 100);
    const cuotas = [];
    if (sena > 0) cuotas.push({
        concepto: 'sena', titulo: 'Anticipo', porcentaje, momento: 'Al aceptar el presupuesto y antes de iniciar el proyecto',
        importe_centavos: sena,
    });
    if (quote.modalidad_pago === 'anticipo_dos_hitos' && totalCentavos.value > 0) {
        const restante = totalCentavos.value - sena;
        const intermedio = Math.floor(restante / 2);
        cuotas.push({concepto: 'hito_intermedio', titulo: 'Segundo pago',
            porcentaje: Math.floor((100 - porcentaje) / 2),
            momento: quote.momento_hito_intermedio, importe_centavos: intermedio});
        cuotas.push({concepto: 'saldo_final', titulo: 'Pago final',
            porcentaje: 100 - porcentaje - Math.floor((100 - porcentaje) / 2),
            momento: quote.momento_pago_final, importe_centavos: restante - intermedio});
    } else if (totalCentavos.value > 0) {
        cuotas.push({concepto: sena ? 'saldo' : 'pago_total',
            titulo: sena ? 'Saldo' : 'Pago total', porcentaje: 100 - porcentaje, momento: 'Al finalizar el trabajo',
            importe_centavos: totalCentavos.value - sena});
    }
    return {cuotas, instrucciones: quote.instrucciones_transferencia};
});
// Si ya existe un borrador, Laravel reutiliza su número; solo las versiones enviadas generan otro.
const version = computed(() => {
    if (!selected.budget_id) return 1;
    const versions = (props.data.quotes ?? []).filter(item => item.budget_id === selected.budget_id);
    const draft = versions.find(item => item.status === 'draft');
    return draft?.version_number ?? (selected.status === 'draft' ? selected.version_number
        : Math.max(selected.version_number ?? 0, ...versions.map(item => item.version_number)) + 1);
});
const documentPreview = computed(() => ({
    ...quote.data(), subtotal: subtotal.value, total: total.value, version_number: version.value, plan_pago: planPago.value,
    created_at: selected.status === 'draft' ? selected.created_at : undefined, sent_at: null,
}));

// Solo un importe fijo en la misma moneda se propone como precio editable del nuevo presupuesto.
function suggestedPrice(reference, currency) {
    const line = referenceLine(reference, 1, null, currency);
    return line.priced && line.unitMin === line.unitMax && line.unitMin <= 100000000 ? line.unitMin : '';
}

function newItem(description, quantity, reference) {
    const importe = suggestedPrice(reference, quote.currency_code);
    const unidades = Number(quantity || 1);
    const referenciaCompleta = reference && unidades !== 1 ? {
        ...reference, min: Number(reference.min) * unidades, max: Number(reference.max) * unidades,
    } : reference;
    return {
        description, quantity: 1,
        unit_price: importe === '' ? '' : Math.round(Number(importe) * unidades * 100) / 100,
        reference: referenciaCompleta,
    };
}

function priceHint(reference) {
    if (!reference) return;
    const min = money(reference.min, reference.currency);
    const amount = Number(reference.min) === Number(reference.max) ? min : `${min} a ${money(reference.max, reference.currency)}`;
    const label = reference.recovered ? 'Referencia recuperada del tarifario asociado' : 'Referencia de la solicitud';
    return `${label} · ${amount} ${reference.currency}`;
}

// Al elegir otro pedido se reconstruyen los conceptos con las referencias de ese pedido.
watch(() => quote.request_id, () => {
    if (direct || selected.budget_id || !source.value) return;
    quote.title = source.value.title;
    const additional = source.value.additional_services ?? [];
    quote.currency_code = source.value.pricing_reference?.currency ?? additional.find(item => item.pricing_reference)?.pricing_reference.currency ?? 'ARS';
    const services = [
        {
            name: source.value.service_name, description: source.value.description, quantity: 1,
            reference: source.value.pricing_reference, breakdown: source.value.pricing_breakdown
        },
        ...additional.map(item => ({
            name: item.name, description: item.notes, quantity: item.quantity,
            reference: item.pricing_reference, breakdown: item.pricing_breakdown,
        })),
    ];
    // El presupuesto acepta hasta 100 renglones. Si el desglose excede el límite, se conserva un renglón por servicio.
    const detail = services.flatMap(requestBudgetLines);
    const items = detail.length <= 100 ? detail : services.flatMap(service => requestBudgetLines({
        ...service,
        breakdown: null
    }));
    quote.items = items.map(item => newItem(item.description, item.quantity, item.reference));
}, {immediate: true});
// Un importe sugerido no se traslada automáticamente a otra moneda.
watch(() => quote.currency_code, (currency, previous) => {
    for (const item of quote.items) {
        const original = suggestedPrice(item.reference, previous);
        if (item.reference?.currency !== currency && original !== '' && item.unit_price !== '' && Number(item.unit_price) === original) {
            item.unit_price = '';
        }
    }
}, {flush: 'sync'});

function advance() {
    if (step.value < 3) step.value += 1;
    else preview.value = true;
}

// El único acceso a la revisión abre el panel con sus pestañas de pedido original y presupuesto.
function openBudgetPreview() {
    previewView.value = 'presupuesto';
    preview.value = true;
}

function save(send) {
    quote.send = send;
    submit(quote, 'presupuesto', {
        previewTarget: 'g-presupuestos',
        onError: errors => {
            preview.value = false;
            const fields = Object.keys(errors);
            step.value = fields.some(field => ['client', 'direct', 'request_id', 'title', 'currency_code', 'budget_id'].includes(field) || field.startsWith('client.')) ? 1
                : fields.some(field => field === 'items' || field.startsWith('items.')) ? 2 : 3;
        },
    });
}
</script>

<template>
    <section class="page shell budget-editor">
        <MigasNavegacion :niveles="migas" dentro-de-contenedor class="budget-editor-breadcrumbs"/>
        <div class="budget-heading"><p class="eyebrow">Gestión · Presupuesto formal</p>
            <h1>{{ selected.budget_id && selected.status !== 'draft' ? 'Prepará la próxima versión' : 'Prepará el presupuesto' }}</h1>
            <p>{{
                    direct ? 'Cargá los datos del cliente, definí los servicios y prepará el PDF.' : 'Revisá el pedido del cliente, definí los servicios y comprobá el documento antes de enviarlo.'
                }}</p>
            <p v-if="selected.budget_id" class="editor-note">Los documentos enviados se conservan. {{
                    selected.status === 'draft' ? 'Estás editando el borrador de la versión' : 'Se guardará una nueva versión:'
                }} {{ version }}.</p></div>
        <div class="budget-stepper">
            <ol class="budget-steps" aria-label="Pasos para preparar presupuesto">
                <li v-for="(label, index) in labels" :key="label">
                    <button type="button" :disabled="index + 1 > step || quote.processing"
                            :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">{{
                            index + 1
                        }}. {{ label }}
                    </button>
                </li>
            </ol>
            <B variant="secondary" :disabled="quote.processing" @click="openBudgetPreview">Vista previa ampliada</B>
        </div>
        <div v-if="Object.keys(quote.errors).length" class="notice" role="alert">
            <div><strong>Revisá los datos del presupuesto</strong>
                <ul>
                    <li v-for="(error, field) in quote.errors" :key="field">{{ error }}</li>
                </ul>
            </div>
        </div>
        <div v-if="needsIssuer" class="editor-note" role="status">
            <template v-if="emiteOrganizacion">
                <p>Faltan los datos de emisión de synaux. Se administran en Datos de la organización.</p>
                <B v-if="data.auth?.puede_administrar" href="/admin/organizacion#emision" variant="secondary">Completar datos de la organización</B>
                <p v-else>Pedile a un administrador que los complete.</p>
            </template>
            <template v-else>
                <p>Completá tu perfil profesional antes de guardar o enviar el presupuesto.</p>
                <B v-if="data.stageTwo" href="/profesional/perfil" variant="secondary">Completar perfil profesional</B>
            </template>
        </div>

        <!-- El formulario valida el paso visible y conserva los datos al volver a editar. -->
        <form class="budget-editor-grid" @submit.prevent="advance">
            <v-card class="card budget-panel">
                <BudgetSourceFields v-if="step === 1" :quote="quote" :selected="selected" :data="data"
                                    :direct="direct" :source="source" :request-options="requestOptions"/>
                <BudgetLines v-else-if="step === 2" :quote="quote" :line-cents="lineCents" :price-hint="priceHint"
                             @add-item="quote.items.push(emptyItem())"/>
                <BudgetTerms v-else :quote="quote" :direct="direct" :configura-pago="configuraPago" :requiere-transferencia="total > 0"/>
                <!-- Una sola tasa de IVA se comparte entre servicios, condiciones y documento. -->
                <BudgetVatFields v-if="step > 1" v-model="includeVat" :quote="quote"/>
                <p v-if="source && !canPrepare" class="editor-note" role="status">Esta solicitud ya no está abierta.
                    Consultá el presupuesto y el estado del proyecto antes de continuar.</p>
                <div class="form-actions"><B v-if="step > 1" variant="secondary" :disabled="quote.processing"
                                             @click="step -= 1">Anterior</B><span v-else/><B type="submit"
                                                                                             :disabled="!canPrepare || needsIssuer || quote.processing"
                                                                                             arrow>{{
                        step === 3 ? 'Revisar documento' : 'Continuar'
                    }}</B></div>
            </v-card>
        </form>
        <BudgetPreviewPanel :visible="preview" :budget="documentPreview" :solicitud="source"
                            :vista-activa="previewView" :client="client" :issuer="issuer"
                            :direct="direct" :step="step" :disabled="!canPrepare || needsIssuer || quote.processing"
                            :saving="quote.processing && !quote.send" :persistent="quote.processing"
                            @close="preview = false" @save-draft="save(false)" @send="save(true)"
                            @change-view="previewView = $event"/>
    </section>
</template>

<style scoped>
/* La grilla, las tarjetas y los pasos siguen el recorrido del cliente sin comprimir los conceptos. */
.budget-editor-breadcrumbs {
    margin-bottom: 20px;
}

.budget-editor {
    width: min(1680px, calc(100% - 48px));
    max-width: none;
    padding-block: clamp(24px, 4vw, 48px) 64px;
}

.budget-heading {
    margin: 0 0 20px;
    max-width: 860px;
}

.budget-heading h1 {
    font-size: clamp(1.75rem, 3vw, 2.3rem);
    font-weight: 700;
    line-height: 1.15;
    margin: 0 0 8px;
}

.budget-panel :deep(h2) {
    font-size: 1.35rem;
    font-weight: 700;
}

.budget-panel :deep(h3) {
    font-size: 1rem;
    font-weight: 700;
}

.budget-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    padding: 0;
    margin: 0;
    list-style: none;
    min-width: 0;
}

.budget-stepper {
    position: sticky;
    top: calc(var(--navbar-height, 76px) + var(--workspace-bar-height, 0px) + 10px);
    z-index: 20;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 16px;
    margin: 0 0 28px;
    padding: 12px 14px;
    border: 1px solid #1f6f7f22;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 5px 14px #153b6410;
}

.budget-steps button {
    min-height: 54px;
    width: 100%;
    border: 1px solid #1f6f7f33;
    border-radius: 10px;
    color: #153b64;
    background: white;
    font: inherit;
    font-weight: 700;
    padding: 10px 14px;
    cursor: pointer;
}

.budget-steps button[aria-current='step'] {
    background: #153b64;
    color: white;
}

.budget-steps button:disabled {
    opacity: .6;
    cursor: default;
}

.budget-steps button:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

.budget-editor-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.budget-editor-grid > * {
    min-width: 0;
}

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

.context-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 14px;
}

.context-heading h3, .context-heading h2 {
    margin: 0;
}

@media (max-width: 600px) {
    .budget-editor {
        width: min(100% - 28px, 1180px);
    }

    .budget-steps {
        gap: 6px;
    }

    .budget-stepper {
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 20px;
        padding: 10px;
    }

    .budget-stepper > :deep(.btn) {
        width: 100%;
    }

    .budget-steps button {
        min-height: 48px;
        font-size: .8rem;
        padding: 8px 4px;
    }

    .form-actions {
        flex-wrap: wrap;
        gap: 12px;
    }

    .form-actions .btn {
        flex: 1 1 100%;
    }
}
</style>
