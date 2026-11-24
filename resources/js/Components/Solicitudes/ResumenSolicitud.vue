<script setup>
import {date, money} from '../../support/formatters';

defineProps({
    form: {type: Object, required: true},
    pricing: {type: Object, required: true},
    selectedService: Object,
    estimate: Object,
    estimateAnswers: {type: Array, required: true},
    extraName: {type: Function, required: true},
});
</script>

<template>
    <!-- El resumen se actualiza con los mismos valores del formulario y se apila debajo en móvil. -->
    <aside class="request-summary">
        <v-card class="card">
            <p class="eyebrow">Tu solicitud</p>
            <h2>Resumen</h2>
            <div v-if="form.title" class="summary-extra"><strong>{{ form.title }}</strong>
                <p v-if="form.desired_deadline">Plazo deseado: {{ form.desired_deadline }}</p></div>
            <h3>{{ selectedService?.name }}</h3>
            <p>{{ selectedService?.summary }}</p>
            <ul v-if="selectedService?.includes?.length">
                <li v-for="item in selectedService.includes" :key="item">{{ item }}</li>
            </ul>
            <div class="request-price"><p>{{
                    estimate ? 'Estimación guardada · servicio principal' : pricing.preliminaryReference ? 'Referencia preliminar del servicio principal' : 'Referencia del servicio principal'
                }}</p><strong v-if="pricing.reference">{{ money(pricing.reference.min, pricing.reference.currency) }}
                <template v-if="pricing.reference.max !== pricing.reference.min"> –
                    {{ money(pricing.reference.max, pricing.reference.currency) }}
                </template>
            </strong><strong v-else>A cotizar según el alcance</strong>
                <p v-if="estimate">{{ date(estimate.created_at) }}</p>
                <p v-else-if="pricing.reference?.segment">{{ pricing.reference.segment }}</p></div>
            <dl v-if="estimateAnswers.length" class="summary-answers">
                <div v-for="answer in estimateAnswers" :key="answer.id">
                    <dt>{{ answer.question }}</dt>
                    <dd>{{ answer.label }}</dd>
                </div>
            </dl>
            <div v-for="(item, index) in form.additional_services" :key="index" class="summary-extra">
                <strong>{{ extraName(item.service_id) }} × {{ item.quantity }}</strong>
                <template v-if="pricing.additionalPrices[index].priced">
                    <p>{{ money(pricing.additionalPrices[index].min, pricing.summaryCurrency) }}
                        <template v-if="pricing.additionalPrices[index].max !== pricing.additionalPrices[index].min"> –
                            {{ money(pricing.additionalPrices[index].max, pricing.summaryCurrency) }}
                        </template>
                    </p>
                    <small>{{ money(pricing.additionalPrices[index].unitMin, pricing.summaryCurrency) }}
                        <template
                            v-if="pricing.additionalPrices[index].unitMax !== pricing.additionalPrices[index].unitMin">
                            – {{ money(pricing.additionalPrices[index].unitMax, pricing.summaryCurrency) }}
                        </template>
                        por unidad</small></template>
                <p v-else>{{
                        Number.isInteger(Number(item.quantity)) && Number(item.quantity) >= 1 && Number(item.quantity) <= 1000 ? 'Pendiente de cotización' : 'Ingresá una cantidad válida para calcular'
                    }}</p>
                <p v-if="item.notes">{{ item.notes }}</p>
            </div>
            <!-- El total reacciona a cambios de servicio, segmento y cantidad sin enviar importes del navegador. -->
            <div class="request-price summary-total" role="status" aria-live="polite" aria-atomic="true">
                <p>{{
                        pricing.preliminaryReference || pricing.pendingExtraCalculation ? 'Importe preliminar' : pricing.summaryPrice.pending ? 'Subtotal estimado' : 'Total estimado'
                    }}</p>
                <strong v-if="pricing.summaryPrice.hasPrice">{{
                        money(pricing.summaryPrice.min, pricing.summaryPrice.currency)
                    }}
                    <template v-if="pricing.summaryPrice.max !== pricing.summaryPrice.min"> –
                        {{ money(pricing.summaryPrice.max, pricing.summaryPrice.currency) }}
                    </template>
                </strong>
                <strong v-else>A cotizar según el alcance</strong>
                <small v-if="pricing.summaryPrice.pending">{{
                        pricing.summaryPrice.pending === 1 ? 'Hay 1 servicio pendiente de cotización o con cantidad por completar.' : 'Hay ' + pricing.summaryPrice.pending + ' servicios pendientes de cotización o con cantidades por completar.'
                    }} No está{{ pricing.summaryPrice.pending === 1 ? '' : 'n' }}
                    incluido{{ pricing.summaryPrice.pending === 1 ? '' : 's' }} en el subtotal.</small>
                <small v-if="pricing.preliminaryReference || pricing.pendingExtraCalculation">El importe se recalcula al
                    continuar, con las respuestas de tu solicitud.</small>
                <small v-else>Importes orientativos. El presupuesto formal confirmará el precio final.</small>
            </div>
            <p v-if="form.contact.name">{{ form.contact.name }}<br>{{ form.contact.email }}</p>
            <p class="summary-note">synaux revisará el pedido y enviará un presupuesto formal con el total, los plazos y
                las condiciones.</p>
        </v-card>
    </aside>
</template>

<style scoped>
.request-summary {
    position: sticky;
    top: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px) + var(--steps-height) + 16px);
}

.request-summary ul {
    padding-left: 1.25rem;
    margin-bottom: 1rem;
}

.summary-extra {
    border-top: 1px solid rgb(31 111 127 / 18%);
    padding-top: 1rem;
    margin-top: 1rem;
}

.summary-note {
    color: #446075;
    margin-top: 1.25rem;
}

.request-price {
    padding: 18px;
    background: #f5f9fa;
    border: 1px solid #1f6f7f22;
    border-radius: 12px;
    margin: 20px 0;
}

.request-price strong {
    display: block;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.65rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.request-price p {
    margin: 0 0 8px;
}

.summary-total {
    border-color: #1f6f7f;
}

.request-price small {
    display: block;
    margin-top: 12px;
}

.summary-answers {
    margin: 0 0 20px;
}

.summary-answers > div {
    padding: 12px 0;
    border-bottom: 1px solid #1f6f7f22;
}

.summary-answers dd {
    margin: 4px 0 0;
    color: #153b64;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.request-summary {
    overflow-wrap: anywhere;
    min-width: 0;
}

@media (max-width: 900px) {
    .request-summary {
        position: static;
    }
}
</style>
