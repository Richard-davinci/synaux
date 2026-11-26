<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import T from '../UI/PageTitle.vue';
import S from '../UI/StatusBadge.vue';
import I from '../UI/AppIcon.vue';
import MigasNavegacion from '../Navigation/MigasNavegacion.vue';
import BudgetDocument from './DocumentoPresupuesto.vue';
import HistorialPresupuesto from './HistorialPresupuesto.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// El servidor entrega documentos autorizados; la vista distingue historial, decisión y proyecto.
const props = defineProps({data: Object});
const record = computed(() => props.data.selected ?? {});
const versiones = computed(() => (props.data.quotes ?? []).filter(item => item.budget_id === record.value.budget_id).sort((a, b) => b.version_number - a.version_number));
const source = computed(() => props.data.requests?.find(item => item.id === record.value.request_id));
const migas = computed(() => {
    const cliente = props.data.area === 'cliente';
    const raiz = cliente ? ['Mi espacio', '/cliente'] : ['Gestión', '/gestion'];
    const destino = cliente ? '/cliente/presupuestos' : '/gestion/presupuestos';
    return [raiz, ['Presupuestos', destino], ['PRE-' + record.value.budget_id, destino], ['Versión ' + record.value.version_number]];
});
const project = computed(() => props.data.projects?.find(item => item.accepted_budget_version_id === record.value.id));
const isOwner = computed(() => props.data.preview || record.value.request_owner === props.data.auth?.id || record.value.client_user_id === props.data.auth?.id);
const canDecide = computed(() => isOwner.value && record.value.status === 'sent' && record.value.display_status !== 'expired'
    && record.value.current && (!source.value || source.value.status === 'open'));
const canEdit = computed(() => !props.data.preview && record.value.issuer_id === props.data.auth?.id
    && record.value.status !== 'accepted' && (!source.value || source.value.status === 'open'));
const state = computed(() => {
    if (record.value.status === 'accepted') return {
        title: 'Presupuesto aceptado',
        text: 'Esta es la versión y las condiciones que confirmaste. Podés seguir el proyecto desde tu cuenta.',
        icon: 'check'
    };
    if (record.value.status === 'rejected') return {
        title: 'Presupuesto rechazado',
        text: 'El rechazo quedó registrado. Consultá el historial para revisar otras versiones.',
        icon: 'info'
    };
    if (record.value.status === 'draft') return {
        title: 'Borrador interno',
        text: 'Este documento todavía no fue enviado al cliente.',
        icon: 'edit'
    };
    if (!record.value.current) return {
        title: 'Estás viendo una versión anterior',
        text: 'Consultá la versión más reciente para conocer el estado y las condiciones actuales.',
        icon: 'clock'
    };
    if (record.value.external && record.value.display_status !== 'expired') return {
        title: 'Presupuesto directo emitido',
        text: 'Descargá el PDF para compartirlo con el cliente.',
        icon: 'file'
    };
    if (record.value.display_status === 'expired') return {
        title: 'La vigencia de este presupuesto finalizó',
        text: 'Contactá a synaux para revisar el alcance y preparar una nueva versión antes de confirmar.',
        icon: 'clock'
    };
    if (source.value && source.value.status !== 'open') return {
        title: 'La solicitud ya está cerrada',
        text: 'Podés consultar el documento y sus condiciones. Revisá tus proyectos para continuar.',
        icon: 'folder'
    };
    return {
        title: 'Presupuesto disponible para revisar',
        text: 'Revisá los servicios, el importe, los plazos y las condiciones antes de tomar una decisión.',
        icon: 'file'
    };
});
const decision = ref(props.data.referenceKey === 'presupuesto-aceptar' ? 'accepted' : props.data.referenceKey === 'presupuesto-rechazar' ? 'rejected' : '');
const form = useForm({id: record.value.id, decision: '', confirmed: false, motivo_rechazo: ''});
// La aceptación solo pide confirmación. Para rechazar también se necesita un motivo escrito.
const puedeConfirmar = computed(() => form.confirmed && !form.processing
    && (decision.value !== 'rejected' || Boolean(form.motivo_rechazo.trim())));
const otrosErrores = computed(() => Object.entries(form.errors).filter(([campo]) => campo !== 'motivo_rechazo'));
const submit = useSubmit();

// La galería enlaza estados ficticios; en la cuenta cada enlace abre el documento autorizado.
function enlaceVersionHistorial(item) {
    if (!props.data.preview) return '/presupuestos/' + item.id;
    if (item.status === 'accepted') return '/prototipo/presupuesto-aceptado';
    if (item.status === 'rejected') return '/prototipo/presupuesto-rechazado';
    if (!item.current) return '/prototipo/presupuesto-anterior';
    return '/prototipo/presupuesto';
}

// Cada apertura exige confirmar de nuevo; cerrar no conserva una casilla marcada de otra decisión.
watch(decision, () => {
    form.resetAndClearErrors('confirmed', 'motivo_rechazo');
});

function decide() {
    if (!canDecide.value || !puedeConfirmar.value) return;
    form.decision = decision.value;
    submit(form, 'decidir-presupuesto', {previewTarget: decision.value === 'accepted' ? 'proyecto' : 'presupuestos'});
}
</script>

<template>
    <section class="page shell" :class="{ 'workspace-view': data.area === 'cliente' }">
        <div class="client-budget">
            <MigasNavegacion :niveles="migas" dentro-de-contenedor class="budget-breadcrumbs"/>
            <T :eyebrow="data.area === 'cliente' ? undefined : 'Presupuesto formal'" :title="record.title">
                <S :status="record.display_status ?? record.status"/></T>
            <div class="budget-state" :class="'budget-state--' + record.status" role="status"><I :name="state.icon"/>
                <div><strong>{{ state.title }}</strong>
                    <p>{{ state.text }}</p>
                </div>
            </div>
            <section v-if="record.status === 'rejected'" class="rejection-reason" aria-labelledby="rejection-reason-title">
                <div class="rejection-reason-heading">
                    <I name="info"/><h2 id="rejection-reason-title">Motivo del rechazo · Versión {{ record.version_number }}</h2>
                </div>
                <p v-if="record.motivo_rechazo" class="preserve-lines">{{ record.motivo_rechazo }}</p>
                <p v-else class="rejection-reason-empty">No se registró un motivo para esta versión.</p>
            </section>
            <div class="client-budget-grid">
                <div class="budget-document-stage">
                    <BudgetDocument :budget="record" :client="record.client" :issuer="record.issuer"/>
                </div>
                <aside class="budget-side">
                    <v-card class="card">
                        <h2>Resumen del presupuesto</h2>
                        <p class="budget-total-label">Total · {{ record.currency_code }}</p>
                        <strong class="budget-amount">{{ money(record.total, record.currency_code) }}</strong>
                        <div :class="['button-stack', { 'budget-action-grid': canEdit }]">
                            <B v-if="canEdit && record.status === 'draft'" :href="'/editor/presupuesto/' + record.id">
                                Continuar borrador
                            </B>
                            <B v-if="!data.preview" :href="'/presupuestos/' + record.id + '/pdf'"
                               variant="secondary">Descargar PDF</B>
                            <p v-else class="budget-help">La descarga del PDF está disponible en tu cuenta con el
                                presupuesto real.</p>
                            <template v-if="canDecide"><B arrow @click="decision = 'accepted'">Aceptar presupuesto</B><B
                                variant="secondary" @click="decision = 'rejected'">Rechazar presupuesto</B></template>
                            <B v-if="project && record.status === 'accepted'" :href="'/proyectos/' + project.id" arrow>Ver
                                proyecto</B>
                            <B v-else-if="record.status === 'accepted'" href="/cliente/proyectos" arrow>Ir a mis
                                proyectos</B>
                            <B v-if="canEdit && record.status !== 'draft'" :href="'/editor/presupuesto/' + record.id">
                                Preparar nueva versión
                            </B>
                        </div>
                        <p v-if="canDecide" class="budget-help">La aceptación crea el proyecto con esta versión. Antes
                            de confirmar podrás revisar tu decisión.</p>
                        <p v-if="record.display_status === 'expired' && record.current" class="budget-help">Para
                            solicitar una actualización, contactá a synaux indicando PRE-{{ record.budget_id }}.</p>
                    </v-card>
                    <v-card class="card historial-en-detalle">
                        <HistorialPresupuesto :versiones="versiones" :id-actual="record.id"
                                              :enlace-version="enlaceVersionHistorial" :notificaciones="false"/>
                    </v-card>
                </aside>
            </div>
            <!-- La decisión se envía solamente desde la confirmación y nunca al abrir el diálogo. -->
            <SidePanel title="Confirmar decisión" :model-value="!!decision && canDecide" :persistent="form.processing"
                       max-width="480" @update:model-value="decision = $event ? decision : ''">
                <div class="synaux-app">
                    <v-card class="card modal-card budget-decision">
                        <p class="eyebrow">Confirmar decisión · Versión {{ record.version_number }}</p>
                        <h2>{{
                                decision === 'accepted' ? '¿Aceptás este presupuesto?' : '¿Querés rechazar este presupuesto?'
                            }}</h2>
                        <p>{{
                                decision === 'accepted' ? 'Se creará un proyecto con los servicios, el total y las condiciones de esta versión.' : 'Esta versión quedará rechazada. Podrás consultarla y descargarla desde el historial.'
                            }}</p>
                        <div class="decision-summary"><strong>{{ record.title }}</strong>
                            <dl class="data-list">
                                <div>
                                    <dt>Total · {{ record.currency_code }}</dt>
                                    <dd>{{ money(record.total, record.currency_code) }}</dd>
                                </div>
                                <div>
                                    <dt>Vigencia</dt>
                                    <dd>{{ date(record.valid_until) }}</dd>
                                </div>
                                <div>
                                    <dt>Entrega estimada</dt>
                                    <dd>{{ record.estimated_delivery_days }} días</dd>
                                </div>
                            </dl>
                        </div>
                        <F v-if="decision === 'rejected'" v-model="form.motivo_rechazo" label="Motivo del rechazo"
                           type="textarea" maxlength="1000" required :disabled="form.processing" :error="form.errors.motivo_rechazo"
                           hint="Por ejemplo, si necesitás ajustar el importe, el plazo o el alcance."/>
                        <div v-if="otrosErrores.length" role="alert" class="notice">
                            <ul>
                                <li v-for="[campo, error] in otrosErrores" :key="campo">{{ error }}</li>
                            </ul>
                        </div>
                        <label class="decision-check"><input v-model="form.confirmed" type="checkbox"
                                                             :disabled="form.processing"><span>{{
                                decision === 'accepted' ? 'Revisé el alcance, el total, los plazos y las condiciones y acepto esta versión.' : 'Confirmo que quiero rechazar esta versión del presupuesto.'
                            }}</span></label>
                        <div class="form-actions"><B variant="secondary" :disabled="form.processing"
                                                     @click="decision = ''">Volver al presupuesto</B><B
                            :disabled="!puedeConfirmar" @click="decide">{{
                                form.processing ? 'Guardando decisión…' : decision === 'accepted' ? 'Confirmar aceptación' : 'Confirmar rechazo'
                            }}</B></div>
                    </v-card>
                </div>
            </SidePanel>
        </div>
    </section>
</template>

<style scoped>
/* El documento dispone de ancho propio y el resumen se apila debajo en pantallas pequeñas. */
.client-budget-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.client-budget-grid > * {
    min-width: 0;
}

.budget-breadcrumbs {
    width: 100%;
    max-width: none;
    margin-bottom: 12px;
    padding-top: 0;
}

.budget-document-stage {
    /* El documento se lee como una pieza sobre un fondo suave, separado del resumen lateral. */
    min-width: 0;
    padding: clamp(12px, 2vw, 22px);
    border: 1px solid #d4e3e8;
    border-radius: 16px;
    background: #f5f9fa;
}

.budget-document-stage :deep(.budget-document) {
    box-shadow: 0 5px 16px #153b6410;
}

.budget-document-stage :deep(.document-heading) {
    padding: 20px 22px;
}

.budget-document-stage :deep(.document-heading .brand-logo) {
    width: 88px;
}

.budget-document-stage :deep(.document-label) {
    margin-bottom: 5px;
    font-size: 1.25rem;
}

.budget-document-stage :deep(.document-content) {
    padding: 20px 22px;
}

.budget-document-stage :deep(.document-title) {
    margin-bottom: 16px;
    font-size: 1.12rem;
}

.budget-document-stage :deep(.document-parties) {
    gap: 12px;
    margin-bottom: 20px;
}

.budget-document-stage :deep(.party-content) {
    padding: 10px 12px;
    font-size: .8rem;
}

.budget-document-stage :deep(.document-conditions) {
    padding: 16px 22px;
}

.budget-side {
    display: grid;
    gap: 20px;
    position: sticky;
    top: calc(var(--navbar-height, 76px) + var(--workspace-bar-height, 0px) + 16px);
}

.budget-side .button-stack {
    /* Las acciones mantienen su medida y quedan centradas con aire respecto de los datos del resumen. */
    justify-items: center;
    margin-top: 16px;
    padding: 10px 8px 0;
}

.budget-side .button-stack .btn {
    width: min(100%, 250px);
    min-width: 0;
    height: auto;
    min-height: 44px;
    padding: 8px 14px;
    font-size: .9375rem;
    line-height: 1.2;
}

.budget-side .button-stack .btn :deep(.v-btn__content) {
    white-space: normal;
    overflow-wrap: anywhere;
    text-align: center;
}

.budget-total-label {
    margin: 18px 0 -10px;
    color: #446075;
    font-size: .875rem;
    font-weight: 700;
}

.budget-action-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.budget-side .budget-action-grid .btn {
    padding-inline: 8px;
    font-size: .875rem;
}

.client-budget h2, .budget-decision h2 {
    font-size: clamp(1.2rem, 1.7vw, 1.5rem);
    font-weight: 700;
    line-height: 1.25;
}

.client-budget :deep(.page-title h1) {
    font-size: clamp(1.7rem, 2.6vw, 2.15rem);
    line-height: 1.18;
}

.client-budget :deep(.page-title > div > p:last-child) {
    margin-top: 6px;
}

.budget-state {
    display: flex;
    gap: 14px;
    margin: 24px 0;
    padding: 20px;
    border: 1px solid #1f6f7f33;
    border-radius: 14px;
    background: #f5f9fa;
}

.budget-state--rejected {
    /* El estado se distingue con la paleta institucional sin competir con el motivo escrito. */
    border-color: #1f6f7f66;
    border-left: 4px solid #1f6f7f;
    background: #eef6f7;
}

.budget-state--rejected > .icon {
    color: #1f6f7f;
}

.budget-state--draft {
    border-color: #c9dce2;
    border-left: 4px solid #153b64;
    background: #f5f9fa;
}

.budget-state--draft > .icon {
    color: #153b64;
}

.rejection-reason {
    /* El motivo tiene una tarjeta independiente para que el cliente lo encuentre sin confundirlo con el estado. */
    margin: -8px 0 24px;
    padding: 18px 20px;
    border: 1px solid #c9dce2;
    border-left: 4px solid #1f6f7f;
    border-radius: 12px;
    background: #f5f9fa;
}

.rejection-reason-heading {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #153b64;
}

.rejection-reason-heading .icon {
    width: 18px;
    height: 18px;
    color: #1f6f7f;
}

.rejection-reason h2 {
    margin: 0;
    font-size: 1rem;
}

.rejection-reason > p {
    margin: 10px 0 0;
    padding-top: 10px;
    border-top: 1px solid #1f6f7f26;
    color: #446075;
}

.rejection-reason-empty {
    font-style: italic;
}

.budget-state > .icon {
    width: 26px;
    height: 26px;
    flex: 0 0 26px;
    color: #1f6f7f;
}

.budget-state strong {
    font-size: 1rem;
}

.budget-state p {
    margin: 6px 0 0;
}

.budget-state .preserve-lines { overflow-wrap: anywhere; }

.budget-state .btn {
    margin-top: 14px;
}

.budget-amount {
    display: block;
    font-family: 'Space Grotesk', sans-serif;
    font-size: clamp(1.7rem, 2.5vw, 2rem);
    margin: 16px 0;
    overflow-wrap: anywhere;
}

.budget-help {
    font-size: .9rem;
    margin-top: 14px;
}

.historial-en-detalle :deep(.historial-presupuestos) {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
}

.budget-side .data-list dd {
    text-align: right;
    overflow-wrap: anywhere;
}

.budget-decision {
    max-height: 90vh;
    overflow-y: auto;
}

.decision-summary {
    padding: 16px;
    border-radius: 10px;
    background: #f5f9fa;
    margin: 20px 0;
}

.decision-check {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    line-height: 1.5;
    cursor: pointer;
}

.decision-check input {
    width: 22px;
    height: 22px;
    min-height: 22px;
    flex: 0 0 22px;
    margin-top: 2px;
}

.decision-check input:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

@media (max-width: 950px) {
    .client-budget-grid {
        grid-template-columns: 1fr;
    }

    .budget-side {
        position: static;
    }
}

@media (max-width: 600px) {
    .budget-state {
        padding: 16px;
    }

    .budget-document-stage :deep(.document-heading),
    .budget-document-stage :deep(.document-content) {
        padding: 16px;
    }

    .budget-document-stage :deep(.document-conditions) {
        padding: 14px 16px;
    }

    .budget-action-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .budget-decision .form-actions {
        flex-wrap: wrap;
        gap: 12px;
    }

    .budget-decision .form-actions .btn {
        flex: 1 1 100%;
    }
}
</style>
