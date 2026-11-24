<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import T from '../UI/PageTitle.vue';
import S from '../UI/StatusBadge.vue';
import I from '../UI/AppIcon.vue';
import BudgetDocument from './DocumentoPresupuesto.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// El servidor entrega documentos autorizados; la vista distingue historial, decisión y proyecto.
const props = defineProps({data: Object});
const record = computed(() => props.data.selected ?? {});
const versions = computed(() => (props.data.quotes ?? []).filter(item => item.budget_id === record.value.budget_id).sort((a, b) => b.version_number - a.version_number));
const latest = computed(() => versions.value.find(item => item.current));
const source = computed(() => props.data.requests?.find(item => item.id === record.value.request_id));
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
        text: 'Tu decisión quedó registrada. El documento permanece disponible para consultar y descargar.',
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
const form = useForm({id: record.value.id, decision: '', confirmed: false});
const submit = useSubmit();

// La galería enlaza estados ficticios; en la cuenta cada enlace abre el documento autorizado.
function historyHref(item) {
    if (!props.data.preview) return '/presupuestos/' + item.id;
    if (item.status === 'accepted') return '/prototipo/presupuesto-aceptado';
    if (item.status === 'rejected') return '/prototipo/presupuesto-rechazado';
    if (!item.current) return '/prototipo/presupuesto-anterior';
    return '/prototipo/presupuesto';
}

// Cada apertura exige confirmar de nuevo; cerrar no conserva una casilla marcada de otra decisión.
watch(decision, () => {
    form.confirmed = false;
    form.clearErrors();
});

function decide() {
    if (!canDecide.value || !form.confirmed || form.processing) return;
    form.decision = decision.value;
    submit(form, 'decidir-presupuesto', {previewTarget: decision.value === 'accepted' ? 'proyecto' : 'presupuestos'});
}
</script>

<template>
    <section class="page shell">
        <div class="client-budget">
            <B :href="data.area === 'gestion' ? '/gestion/presupuestos' : '/cliente/presupuestos'" variant="ghost">←
                Volver a presupuestos</B>
            <T :eyebrow="'PRE-' + record.budget_id + ' · Presupuesto formal'" :title="record.title"
               :description="'Versión ' + record.version_number + ' · Emitida el ' + date(record.sent_at ?? record.created_at)">
                <S :status="record.display_status ?? record.status"/></T>
            <div class="budget-state" role="status"><I :name="state.icon"/>
                <div><strong>{{ state.title }}</strong>
                    <p>{{ state.text }}</p><B v-if="latest && latest.id !== record.id"
                                              :href="data.preview ? '/prototipo/presupuesto' : '/presupuestos/' + latest.id"
                                              variant="secondary">Ver versión {{ latest.version_number }}</B></div>
            </div>
            <div class="client-budget-grid">
                <BudgetDocument :budget="record" :client="record.client" :issuer="record.issuer"/>
                <aside class="budget-side">
                    <v-card class="card">
                        <p class="eyebrow">Versión {{ record.version_number }}</p>
                        <h2>Resumen del presupuesto</h2>
                        <strong class="budget-amount">{{ money(record.total, record.currency_code) }}</strong>
                        <dl class="data-list">
                            <div>
                                <dt>Moneda</dt>
                                <dd>{{ record.currency_code }}</dd>
                            </div>
                            <div>
                                <dt>Vigencia hasta</dt>
                                <dd>{{ date(record.valid_until) }}</dd>
                            </div>
                            <div>
                                <dt>Entrega estimada</dt>
                                <dd>{{ record.estimated_delivery_days }} días desde el inicio acordado</dd>
                            </div>
                        </dl>
                        <div class="button-stack"><B v-if="!data.preview" :href="'/presupuestos/' + record.id + '/pdf'"
                                                     variant="secondary">Descargar PDF · versión
                            {{ record.version_number }}</B>
                            <p v-else class="budget-help">La descarga del PDF está disponible en tu cuenta con el
                                presupuesto real.</p>
                            <template v-if="canDecide"><B arrow @click="decision = 'accepted'">Aceptar presupuesto</B><B
                                variant="secondary" @click="decision = 'rejected'">Rechazar presupuesto</B></template>
                            <B v-if="project && record.status === 'accepted'" :href="'/proyectos/' + project.id" arrow>Ver
                                proyecto</B>
                            <B v-else-if="record.status === 'accepted'" href="/cliente/proyectos" arrow>Ir a mis
                                proyectos</B>
                            <B v-if="canEdit" :href="'/editor/presupuesto/' + record.id" variant="secondary">{{
                                    record.status === 'draft' ? 'Continuar borrador' : 'Preparar nueva versión'
                                }}</B>
                        </div>
                        <p v-if="canDecide" class="budget-help">La aceptación crea el proyecto con esta versión. Antes
                            de confirmar podrás revisar tu decisión.</p>
                        <p v-if="record.display_status === 'expired' && record.current" class="budget-help">Para
                            solicitar una actualización, contactá a synaux indicando PRE-{{ record.budget_id }}.</p>
                    </v-card>
                    <v-card class="card"><h2>Historial de versiones</h2>
                        <p class="budget-help">Cada versión conserva sus importes y condiciones.</p>
                        <ol class="budget-history">
                            <li v-for="item in versions" :key="item.id"
                                :class="{ 'history-selected': item.id === record.id }"><a :href="historyHref(item)"
                                                                                          :aria-current="item.id === record.id ? 'page' : undefined">Versión
                                {{ item.version_number }}<span v-if="item.id === record.id"> · En pantalla</span></a><S
                                :status="item.display_status ?? item.status"/><small>{{
                                    date(item.sent_at ?? item.created_at)
                                }}</small><small v-if="item.current">{{
                                    record.external ? 'Última versión emitida' : 'Última versión enviada'
                                }}</small></li>
                        </ol>
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
                        <div v-if="Object.keys(form.errors).length" role="alert" class="notice">
                            <ul>
                                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>
                        <label class="decision-check"><input v-model="form.confirmed" type="checkbox"
                                                             :disabled="form.processing"><span>{{
                                decision === 'accepted' ? 'Revisé el alcance, el total, los plazos y las condiciones y acepto esta versión.' : 'Confirmo que quiero rechazar esta versión del presupuesto.'
                            }}</span></label>
                        <div class="form-actions"><B variant="secondary" :disabled="form.processing"
                                                     @click="decision = ''">Volver al presupuesto</B><B
                            :disabled="!form.confirmed || form.processing" @click="decide">{{
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

.budget-side {
    display: grid;
    gap: 20px;
    position: sticky;
    top: 24px;
}

.client-budget h2, .budget-decision h2 {
    font-size: clamp(1.4rem, 2vw, 1.8rem);
    font-weight: 700;
    line-height: 1.25;
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

.budget-state > .icon {
    width: 26px;
    height: 26px;
    flex: 0 0 26px;
    color: #1f6f7f;
}

.budget-state strong {
    font-size: 1.1rem;
}

.budget-state p {
    margin: 6px 0 0;
}

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

.budget-history {
    padding: 0;
    margin: 0;
    list-style: none;
}

.budget-history li {
    display: grid;
    gap: 8px;
    padding: 16px 12px;
    border-bottom: 1px solid #1f6f7f22;
}

.budget-history a {
    color: #153b64;
    font-weight: 700;
    padding: 8px 0;
}

.budget-history a:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

.history-selected {
    border-radius: 10px;
    background: #f5f9fa;
}

.budget-history small {
    color: #446075;
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
    accent-color: #153b64;
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

    .budget-decision .form-actions {
        flex-wrap: wrap;
        gap: 12px;
    }

    .budget-decision .form-actions .btn {
        flex: 1 1 100%;
    }
}
</style>
