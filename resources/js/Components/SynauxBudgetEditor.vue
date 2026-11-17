<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from './SynauxButton.vue';
import F from './SynauxField.vue';
import I from './SynauxIcon.vue';
import BudgetDocument from './BudgetDocument.vue';
import { money, date, useSubmit } from '../support/synaux';

// El editor recibe solo las solicitudes autorizadas; Laravel vuelve a comprobar permisos y valores al guardar.
const props = defineProps({ data: Object });
const selected = props.data.selected ?? {};
const submit = useSubmit();
const step = ref(1);
const preview = ref(false);
const labels = ['Solicitud', 'Servicios', 'Condiciones'];
const quote = useForm({
    budget_id: selected.budget_id ?? null,
    request_id: selected.request_id ?? props.data.requestId ?? '',
    title: selected.title ?? '',
    currency_code: selected.currency_code ?? 'ARS',
    tax_rate: selected.tax_rate ?? 0,
    valid_until: selected.valid_until ?? new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
    estimated_delivery_days: selected.estimated_delivery_days ?? 30,
    terms: selected.terms ?? '',
    items: selected.items?.map(item => ({ description: item.description, quantity: item.quantity, unit_price: item.unit_price })) ?? [],
    send: false,
});
const requests = computed(() => props.data.budgetRequests ?? props.data.requests ?? []);
const source = computed(() => requests.value.find(item => item.id == quote.request_id));
const requestOptions = computed(() => requests.value.filter(item =>
    (item.status === 'open' && item.user_id !== props.data.auth?.id) || item.id == selected.request_id
).map(item => ({ value: item.id, label: 'SOL-' + item.id + ' · ' + item.title })));
const client = computed(() => source.value?.contact ?? selected.client);
const issuer = computed(() => props.data.issuerDetails ?? ({
    name: props.data.profile?.brand_name ?? selected.issuer?.name ?? 'synaux',
    contact: props.data.auth?.name ?? selected.issuer?.contact,
    email: props.data.auth?.email ?? selected.issuer?.email,
}));
const canPrepare = computed(() => source.value?.status === 'open');
const needsIssuer = computed(() => !props.data.profile && !props.data.preview);

// Cada concepto se redondea a centavos, igual que el documento calculado por el servidor.
const lineCents = item => Math.round(Number(item.quantity || 0) * Number(item.unit_price || 0) * 100);
const subtotalCents = computed(() => quote.items.reduce((sum, item) => sum + lineCents(item), 0));
const subtotal = computed(() => subtotalCents.value / 100);
const total = computed(() => Math.round(subtotalCents.value * (1 + Number(quote.tax_rate || 0) / 100)) / 100);
// Si ya existe un borrador, Laravel reutiliza su número; solo las versiones enviadas generan otro.
const version = computed(() => {
    if (!selected.budget_id) return 1;
    const versions = (props.data.quotes ?? []).filter(item => item.budget_id === selected.budget_id);
    const draft = versions.find(item => item.status === 'draft');
    return draft?.version_number ?? (selected.status === 'draft' ? selected.version_number
        : Math.max(selected.version_number ?? 0, ...versions.map(item => item.version_number)) + 1);
});
const documentPreview = computed(() => ({
    ...quote.data(), subtotal: subtotal.value, total: total.value, version_number: version.value,
}));

// Al elegir otro pedido se reconstruyen los conceptos a cotizar; el aviso del selector explica este cambio.
watch(() => quote.request_id, () => {
    if (selected.budget_id || !source.value) return;
    quote.title = source.value.title;
    quote.items = [
        { description: source.value.service_name + '\n' + source.value.description, quantity: 1, unit_price: '' },
        ...(source.value.additional_services ?? []).map(item => ({
            description: item.name + (item.notes ? '\n' + item.notes : ''), quantity: item.quantity, unit_price: '',
        })),
    ];
}, { immediate: true });
function advance() {
    if (step.value < 3) step.value += 1;
    else preview.value = true;
}
function save(send) {
    quote.send = send;
    submit(quote, 'presupuesto', {
        previewTarget: 'g-presupuestos',
        onError: errors => {
            preview.value = false;
            const fields = Object.keys(errors);
            step.value = fields.some(field => ['request_id', 'title', 'currency_code', 'budget_id'].includes(field)) ? 1
                : fields.some(field => field === 'items' || field.startsWith('items.')) ? 2 : 3;
        },
    });
}
</script>

<template>
    <section class="page shell budget-editor">
        <B href="/gestion/presupuestos" variant="ghost">← Volver a presupuestos</B>
        <div class="budget-heading"><p class="eyebrow">Gestión · Presupuesto formal</p><h1>{{ selected.budget_id ? 'Prepará la próxima versión' : 'Prepará el presupuesto' }}</h1><p>Revisá el pedido del cliente, definí los servicios y comprobá el documento antes de enviarlo.</p><p v-if="selected.budget_id" class="editor-note">Los documentos enviados se conservan. {{ selected.status === 'draft' ? 'Estás editando el borrador de la versión' : 'Se guardará una nueva versión:' }} {{ version }}.</p></div>
        <ol class="budget-steps" aria-label="Pasos para preparar presupuesto"><li v-for="(label, index) in labels" :key="label"><button type="button" :disabled="index + 1 > step || quote.processing" :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">{{ index + 1 }}. {{ label }}</button></li></ol>
        <div v-if="Object.keys(quote.errors).length" class="notice" role="alert"><div><strong>Revisá los datos del presupuesto</strong><ul><li v-for="(error, field) in quote.errors" :key="field">{{ error }}</li></ul></div></div>
        <div v-if="needsIssuer" class="editor-note" role="status"><p>Completá los datos del emisor antes de guardar o enviar el presupuesto.</p><B href="/mi-perfil#emision" variant="secondary">Completar datos del emisor</B></div>

        <!-- El formulario valida el paso visible y conserva los datos al volver a editar. -->
        <form class="budget-editor-grid" @submit.prevent="advance">
            <v-card class="card budget-panel">
                <p class="eyebrow">Paso {{ step }} de 3</p>
                <template v-if="step === 1">
                    <h2>Revisá la solicitud</h2>
                    <F v-model="quote.request_id" label="Solicitud relacionada" type="select" :disabled="!!selected.budget_id || quote.processing" :options="requestOptions" :error="quote.errors.request_id" required />
                    <p v-if="!selected.budget_id" class="editor-hint">Si cambiás la solicitud, se reemplazan el título y los conceptos por los del pedido elegido.</p>
                    <p v-if="!requestOptions.length">Todavía no hay solicitudes disponibles para presupuestar.</p>
                    <section v-if="source" class="request-context" aria-label="Pedido del cliente">
                        <div class="context-heading"><h3>SOL-{{ source.id }} · {{ source.title }}</h3><B :href="'/solicitudes/' + source.id" variant="ghost">Ver solicitud</B></div>
                        <p class="preserve-lines">{{ source.description }}</p>
                        <dl><div><dt>Servicio principal</dt><dd>{{ source.service_name }}</dd></div><div><dt>Plazo solicitado</dt><dd>{{ source.desired_deadline || 'A coordinar' }}</dd></div><div><dt>Presupuesto disponible</dt><dd>{{ source.budget_reference || 'Sin indicar' }}</dd></div></dl>
                        <div v-if="source.additional_services?.length"><h3>Servicios adicionales</h3><p v-for="item in source.additional_services" :key="item.service_id"><strong>{{ item.name }} × {{ item.quantity }}</strong><br>{{ item.notes }}</p></div>
                        <div v-if="source.answers?.length"><h3>Respuestas de la estimación</h3><dl><div v-for="answer in source.answers" :key="answer.id"><dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd></div></dl></div>
                    </section>
                    <section v-if="client" class="client-context"><h3>Contacto del pedido</h3><strong>{{ client.name }}</strong><p>{{ client.email }}</p><p v-if="client.company">{{ client.company }}</p><p v-if="client.phone">{{ client.phone }}</p><p v-if="client.address">{{ client.address }}</p></section>
                    <F v-model="quote.title" label="Título del presupuesto" maxlength="255" :error="quote.errors.title" required />
                    <F v-model="quote.currency_code" label="Moneda del presupuesto" type="select" :options="['ARS', 'USD', 'EUR']" :error="quote.errors.currency_code" required />
                    <p class="editor-hint">Los importes se ingresan en la moneda elegida. Cambiarla no convierte los precios.</p>
                </template>
                <template v-else-if="step === 2">
                    <h2>Definí los servicios</h2><p>Completá el alcance, la cantidad y el precio de cada concepto.</p>
                    <div v-for="(item, index) in quote.items" :key="index" class="quote-item">
                        <div class="context-heading"><h3>Concepto {{ index + 1 }}</h3><B variant="ghost" :disabled="quote.items.length === 1 || quote.processing" :aria-label="'Quitar concepto ' + (index + 1)" @click="quote.items.splice(index, 1)">Quitar</B></div>
                        <F v-model="item.description" label="Servicio y entregables" type="textarea" maxlength="2000" :error="quote.errors['items.' + index + '.description']" required />
                        <div class="form-grid"><F v-model="item.quantity" label="Cantidad" type="number" min="0.001" max="10000" step="0.001" :error="quote.errors['items.' + index + '.quantity']" required /><F v-model="item.unit_price" :label="'Precio unitario · ' + quote.currency_code" type="number" min="0" max="100000000" step="0.01" :error="quote.errors['items.' + index + '.unit_price']" required /></div>
                        <p class="item-subtotal">Subtotal del concepto <strong>{{ item.unit_price === '' ? 'Pendiente de cotizar' : money(lineCents(item) / 100, quote.currency_code) }}</strong></p>
                    </div>
                    <B variant="secondary" :disabled="quote.items.length >= 100 || quote.processing" @click="quote.items.push({description:'',quantity:1,unit_price:''})">Agregar concepto</B>
                </template>
                <template v-else>
                    <h2>Plazos y condiciones</h2>
                    <div class="form-grid"><F v-model="quote.estimated_delivery_days" label="Entrega estimada (días desde el inicio)" type="number" min="1" max="3650" step="1" :error="quote.errors.estimated_delivery_days" required /><F v-model="quote.valid_until" label="Presupuesto válido hasta" type="date" :min="new Date().toLocaleDateString('en-CA')" :error="quote.errors.valid_until" required /></div>
                    <F v-model="quote.tax_rate" label="Impuestos (%)" type="number" min="0" max="100" step="0.01" :error="quote.errors.tax_rate" required />
                    <F v-model="quote.terms" label="Condiciones, forma de pago y revisiones" type="textarea" maxlength="10000" hint="Detallá el anticipo, las entregas, las revisiones incluidas y cuándo comienza el plazo." :error="quote.errors.terms" required />
                    <p class="editor-note"><I name="info" /> El cliente recibirá esta versión en su cuenta. Podrá descargarla y aceptar o rechazar el presupuesto vigente.</p>
                </template>
                <p v-if="source && !canPrepare" class="editor-note" role="status">Esta solicitud ya no está abierta. Consultá el presupuesto y el estado del proyecto antes de continuar.</p>
                <div class="form-actions"><B v-if="step > 1" variant="secondary" :disabled="quote.processing" @click="step -= 1">Anterior</B><span v-else /><B type="submit" :disabled="!canPrepare || needsIssuer || quote.processing" arrow>{{ step === 3 ? 'Revisar documento' : 'Continuar' }}</B></div>
            </v-card>

            <!-- Los totales permanecen a la vista; los precios vacíos se señalan como pendientes. -->
            <aside class="card budget-summary">
                <p class="eyebrow">Presupuesto · Versión {{ version }}</p><h2>Resumen</h2><h3>{{ quote.title || 'Elegí una solicitud' }}</h3><p v-if="source">SOL-{{ source.id }} · {{ source.service_name }}</p>
                <dl class="data-list"><div><dt>Conceptos</dt><dd>{{ quote.items.length }}</dd></div><div><dt>Subtotal</dt><dd>{{ money(subtotal, quote.currency_code) }}</dd></div><div><dt>Impuestos ({{ quote.tax_rate }}%)</dt><dd>{{ money(total - subtotal, quote.currency_code) }}</dd></div><div class="quote-total"><dt>Total</dt><dd>{{ money(total, quote.currency_code) }}</dd></div></dl>
                <p v-if="quote.items.some(item => item.unit_price === '')" class="editor-note">Hay conceptos pendientes de cotizar. El total se actualizará al completar sus precios.</p>
                <p>Vigencia: {{ date(quote.valid_until) }}<br>Entrega estimada: {{ quote.estimated_delivery_days }} días.</p>
                <B variant="secondary" :disabled="!canPrepare || needsIssuer || quote.processing" @click="save(false)">{{ quote.processing && !quote.send ? 'Guardando…' : 'Guardar borrador' }}</B><p class="editor-hint">El borrador queda en Gestión. El cliente lo verá cuando lo envíes.</p>
            </aside>
        </form>
        <v-dialog v-model="preview" max-width="1000" :persistent="quote.processing">
            <div class="synaux-app"><v-card class="card modal-card budget-preview"><div class="context-heading"><h2>Revisá antes de enviar</h2><B variant="ghost" :disabled="quote.processing" aria-label="Cerrar vista previa" @click="preview = false">Cerrar</B></div><BudgetDocument :budget="documentPreview" :client="client" :issuer="issuer" /><div class="form-actions"><B variant="secondary" :disabled="quote.processing" @click="preview = false">Volver a editar</B><B :disabled="quote.processing || !canPrepare || needsIssuer" arrow @click="save(true)">{{ quote.processing ? 'Enviando…' : 'Enviar presupuesto al cliente' }}</B></div></v-card></div>
        </v-dialog>
    </section>
</template>

<style scoped>
/* La grilla, las tarjetas y los pasos siguen el recorrido del cliente sin comprimir los conceptos. */
.budget-heading { margin: 26px 0; max-width: 860px; }
.budget-heading h1 { font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 700; line-height: 1.12; margin-bottom: 16px; }
.budget-editor h2 { font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 700; }
.budget-editor h3 { font-size: 1.1rem; font-weight: 700; }
.budget-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; padding: 0; margin: 0 0 24px; list-style: none; }
.budget-steps button { min-height: 48px; width: 100%; border: 1px solid #1f6f7f33; border-radius: 10px; color: #153b64; background: white; font: inherit; font-weight: 700; cursor: pointer; }
.budget-steps button[aria-current='step'] { background: #153b64; color: white; }
.budget-steps button:disabled { opacity: .6; cursor: default; }
.budget-steps button:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 3px; }
.budget-editor-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 24px; align-items: start; }
.budget-editor-grid > * { min-width: 0; }
.budget-summary { position: sticky; top: 24px; overflow-wrap: anywhere; }
.budget-summary > .btn { width: 100%; }
.editor-note, .request-context, .client-context { background: #f5f9fa; border: 1px solid #1f6f7f22; padding: 16px; border-radius: 12px; margin: 16px 0; }
.editor-note .icon { width: 20px; vertical-align: middle; margin-right: 6px; }
.editor-hint { font-size: .9rem; color: #446075; }
.context-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
.context-heading h3, .context-heading h2 { margin: 0; }
.request-context dl > div { padding: 10px 0; border-bottom: 1px solid #1f6f7f22; }
.request-context dd { margin: 4px 0 0; font-weight: 700; overflow-wrap: anywhere; }
.client-context p { margin: 4px 0; overflow-wrap: anywhere; }
.quote-item { padding: 18px; border: 1px solid #1f6f7f33; border-radius: 14px; margin: 18px 0; }
.item-subtotal { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin: 16px 0 0; }
.quote-total { font-size: 1.2rem; font-weight: 700; }
.budget-preview { max-height: 90vh; overflow-y: auto; }
@media (max-width: 900px) { .budget-editor-grid { grid-template-columns: 1fr; } .budget-summary { position: static; } }
@media (max-width: 600px) { .budget-steps { gap: 6px; } .budget-steps button { font-size: .85rem; padding: 8px 4px; } .quote-item { padding: 14px; } .form-actions { flex-wrap: wrap; gap: 12px; } .form-actions .btn { flex: 1 1 100%; } }
</style>
