<script setup>
import {computed, ref} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import S from '../UI/StatusBadge.vue';
import T from '../UI/PageTitle.vue';
import SidePanel from '../UI/SidePanel.vue';
import MigasNavegacion from '../Navigation/MigasNavegacion.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';
import {referenceLine, summarizePrices} from '../../support/calculoPreciosSolicitud';

// Gestión recibe el pedido autorizado y lee los importes que quedaron guardados con la solicitud.
const props = defineProps({data: Object});
const request = computed(() => props.data.selected);
// La ruta muestra que la solicitud pertenece a Gestión y deja volver al listado sin un botón aislado.
const migas = computed(() => [
    ['Gestión', '/gestion'],
    ['Solicitudes', '/gestion/solicitudes'],
    ['SOL-' + request.value.id],
]);
// El detalle y la tabla comparten la etapa real de gestión, tomando primero el presupuesto más reciente.
const presupuesto = computed(() => [...(props.data.quotes ?? [])]
    .sort((a, b) => Number(b.version_number) - Number(a.version_number) || Number(b.id) - Number(a.id))[0] ?? null);
const estadoRevision = computed(() => {
    if (props.data.area !== 'gestion') return null;
    if (presupuesto.value) {
        const estado = presupuesto.value.display_status ?? presupuesto.value.status;
        return {status: estado === 'draft' ? 'review' : estado, etiqueta: ({draft: 'Presupuesto en preparación', sent: 'Presupuesto enviado', issued: 'Presupuesto emitido',
            accepted: 'Presupuesto aceptado', rejected: 'Presupuesto rechazado', expired: 'Presupuesto vencido'})[estado] ?? estado};
    }
    if (request.value.status === 'open') return request.value.reviewed_at
        ? {status: 'pending', etiqueta: 'Pendiente de presupuesto'}
        : {status: 'pending', etiqueta: 'Pendiente de revisión'};
    return null;
});
const extras = computed(() => request.value.additional_services ?? []);
const currency = computed(() => request.value.pricing_reference?.currency
    ?? extras.value.find(item => item.pricing_reference)?.pricing_reference.currency ?? 'ARS');
const services = computed(() => [
    {name: request.value.service_name, quantity: 1, pricing_reference: request.value.pricing_reference},
    ...extras.value,
].map(item => ({...item, price: referenceLine(item.pricing_reference, item.quantity, null, currency.value)})));
const amounts = computed(() => summarizePrices(services.value.map(item => item.price), currency.value));

function priceText({min, max}) {
    return min === max ? money(min, currency.value) : money(min, currency.value) + ' a ' + money(max, currency.value);
}

const open = computed(() => request.value.status === 'open');
const canPrepare = computed(() => open.value && Boolean(request.value.reviewed_at)
    && (props.data.preview || (props.data.area === 'gestion' && request.value.user_id !== props.data.auth?.id)));
const budgetAction = computed(() => {
    if (props.data.area !== 'gestion') return null;
    const latest = presupuesto.value;
    if (latest?.status === 'draft') return {href: '/editor/presupuesto/' + latest.id, label: 'Continuar borrador'};
    const state = latest?.display_status ?? latest?.status;
    if (latest && ['rejected', 'expired'].includes(state) && open.value) {
        return {href: '/editor/presupuesto/' + latest.id, label: 'Preparar nueva versión'};
    }
    if (latest) return {href: '/presupuestos/' + latest.id, label: 'Ver presupuesto'};
    if (canPrepare.value) return {href: '/editor/presupuesto?solicitud=' + request.value.id, label: 'Preparar presupuesto'};
    return null;
});
const textoRevision = computed(() => {
    const latest = presupuesto.value;
    if (latest?.status === 'draft') return 'Hay un borrador interno. Todavía no fue enviado al cliente.';
    if (latest) return 'Consultá el estado y las condiciones del presupuesto asociado.';
    if (request.value.reviewed_at) return 'Revisión completada el ' + date(request.value.reviewed_at) + '. Falta preparar el presupuesto.';
    return open.value ? 'Pendiente de revisión' : 'La solicitud está cerrada.';
});
const closing = ref(false);
const submit = useSubmit();
const state = useForm({id: request.value.id, status: ''});

function changeStatus(status) {
    state.status = status;
    submit(state, 'estado-solicitud', {
        onSuccess: () => {
            closing.value = false;
        }
    });
    if (props.data.preview) closing.value = false;
}
</script>

<template>
    <section class="page shell workspace-view">
        <MigasNavegacion :niveles="migas" class="review-breadcrumbs"/>
        <T eyebrow="Revisión de solicitud" :title="request.title">
            <div class="review-heading-meta">
                <S :status="estadoRevision?.status ?? request.status" :request="!presupuesto"
                   :etiqueta="estadoRevision?.etiqueta"/>
                <span>Registrada el {{ date(request.created_at) }}</span>
            </div>
        </T>

        <div class="review-layout">
            <div class="review-content">
                <section class="card review-card" aria-labelledby="review-scope">
                    <h2 id="review-scope">Alcance de la solicitud</h2>
                    <p class="preserve-lines">{{ request.description }}</p>
                    <dl class="review-facts">
                        <div>
                            <dt>Plazo solicitado</dt>
                            <dd>{{ request.desired_deadline || 'A coordinar' }}</dd>
                        </div>
                        <div>
                            <dt>Presupuesto indicado</dt>
                            <dd>{{ request.budget_reference || 'Sin indicar' }}</dd>
                        </div>
                        <div>
                            <dt>Visibilidad</dt>
                            <dd>{{ request.visibility === 'public' ? 'Pública' : 'Privada' }}</dd>
                        </div>
                    </dl>
                    <p v-if="request.skills?.length" class="review-skills"><strong>Habilidades solicitadas</strong><br>{{
                            request.skills.join(', ')
                        }}</p>
                </section>

                <section class="card review-card" aria-labelledby="review-services">
                    <h2 id="review-services">Servicios solicitados</h2>
                    <article v-for="(item, index) in services" :key="item.service_id ?? 'principal'"
                             class="review-service">
                        <div class="review-service-heading">
                            <div><p class="eyebrow">{{
                                    index ? 'Adicional · ' + item.quantity + ' ud.' : 'Principal'
                                }}</p>
                                <h3>{{ item.name }}</h3></div>
                            <strong v-if="item.price.priced">{{ priceText(item.price) }}</strong>
                            <small v-else>Pendiente de cotización</small>
                        </div>
                        <p v-if="item.notes" class="preserve-lines">{{ item.notes }}</p>
                        <dl v-if="(index ? item.answers : request.answers)?.length" class="review-answers">
                            <div v-for="answer in (index ? item.answers : request.answers)" :key="answer.id">
                                <dt>{{ answer.question }}</dt>
                                <dd class="preserve-lines">{{ answer.label }}</dd>
                            </div>
                        </dl>
                    </article>
                </section>
            </div>

            <aside class="review-content" aria-label="Acciones y datos del cliente">
                <section class="card review-card review-action-card" aria-labelledby="review-action">
                    <h2 id="review-action">Revisión y acciones</h2>
                    <p>{{ textoRevision }}</p>
                    <div v-if="open" class="review-actions">
                        <B v-if="budgetAction" :href="budgetAction.href" arrow>{{ budgetAction.label }}</B>
                        <B v-if="!request.reviewed_at" variant="secondary" :disabled="state.processing"
                           @click="changeStatus('review')">Marcar revisada</B>
                        <B variant="ghost" :disabled="state.processing" @click="closing = true">Cerrar solicitud</B>
                    </div>
                    <div v-else-if="budgetAction" class="review-actions">
                        <B :href="budgetAction.href" arrow>{{ budgetAction.label }}</B>
                    </div>
                    <p v-else class="review-muted">La solicitud ya no está abierta para cotizar.</p>
                </section>

                <section class="card review-card" aria-labelledby="review-reference">
                    <h2 id="review-reference">{{
                            amounts.hasPrice && amounts.pending ? 'Subtotal orientativo' : 'Estimación orientativa'
                        }}</h2>
                    <strong v-if="amounts.hasPrice" class="review-amount">{{ priceText(amounts) }}</strong>
                    <p v-else>Pendiente de cotización.</p>
                    <p v-if="amounts.hasPrice && amounts.pending" class="review-muted">Quedan servicios pendientes que
                        no están incluidos en este importe.</p>
                </section>

                <section v-if="request.contact" class="card review-card review-contact"
                         aria-labelledby="review-contact-title">
                    <h2 id="review-contact-title">Contacto</h2>
                    <strong>{{ request.contact.name }}</strong>
                    <p v-if="request.contact.company">{{ request.contact.company }}</p>
                    <p v-if="request.contact.cuit">CUIT {{ request.contact.cuit }}</p>
                    <p v-if="request.contact.email">{{ request.contact.email }}</p>
                    <p v-if="request.contact.phone">{{ request.contact.phone }}</p>
                    <p v-if="request.contact.address">{{ request.contact.address }}</p>
                </section>
            </aside>
        </div>

        <SidePanel v-model="closing" title="Cerrar solicitud" max-width="460" :persistent="state.processing">
            <section class="card modal-card">
                <h2>¿Querés cerrar esta solicitud?</h2>
                <p>La solicitud dejará de estar disponible para preparar un presupuesto.</p>
                <div class="form-actions">
                    <B variant="secondary" :disabled="state.processing" @click="closing = false">Cancelar</B>
                    <B :disabled="state.processing" @click="changeStatus('closed')">Cerrar solicitud</B>
                </div>
            </section>
        </SidePanel>
    </section>
</template>

<style scoped>
/* La descripción, los servicios y la revisión tienen sus propios encabezados y orden de lectura. */
.review-breadcrumbs {
    margin-bottom: 14px;
}

.review-heading-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    color: #446075;
    font-size: .875rem;
}

.review-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(245px, 1fr);
    gap: clamp(16px, 3vw, 28px);
    align-items: start;
}

.review-content {
    display: grid;
    gap: 14px;
    min-width: 0;
}

.review-card {
    padding: clamp(18px, 3vw, 28px);
    overflow-wrap: anywhere;
}

.review-card h2 {
    margin: 0 0 12px;
}

.review-card p:last-child {
    margin-bottom: 0;
}

.review-facts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 18px 0 0;
    padding: 14px;
    background: #f5f9fa;
    border-radius: 10px;
}

.review-facts dt, .review-answers dt, .review-muted, .review-service-heading small {
    color: #446075;
}

.review-facts dt {
    font-size: .875rem;
}

.review-facts dd, .review-answers dd {
    margin: 0;
    text-align: start;
}

.review-facts dd {
    font-weight: 700;
}

.review-skills {
    margin-top: 12px;
}

.review-service {
    border-top: 1px solid #1f6f7f26;
    padding-block: 14px;
}

.review-service:last-child {
    padding-bottom: 0;
}

.review-service-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.review-service-heading h3 {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 700;
}

.review-service-heading .eyebrow {
    margin: 0 0 4px;
}

.review-service > p {
    margin: 8px 0 0;
}

.review-answers {
    display: grid;
    gap: 8px;
    margin: 12px 0 0;
}

.review-answers > div {
    padding: 8px 12px;
    background: #f5f9fa;
    border-radius: 8px;
}

.review-answers dt {
    font-size: .875rem;
}

.review-answers dd {
    font-weight: 600;
}

.review-action-card {
    border-top: 3px solid #2db3a3;
}

.review-actions {
    display: grid;
    gap: 8px;
}

.review-amount {
    display: block;
    margin-bottom: 6px;
    font: 700 clamp(1.25rem, 3vw, 1.6rem)/1.25 'Space Grotesk', sans-serif;
}

.review-contact p {
    margin: 4px 0 0;
}

@media (max-width: 780px) {
    .review-layout {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 480px) {
    .review-facts {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
