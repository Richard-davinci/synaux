<script setup>
import {computed} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import {money} from '../../support/formatters';
import {referenceLine, summarizePrices} from '../../support/calculoPreciosSolicitud';

// Los campos editan el mismo formulario, tanto para una solicitud como para un presupuesto directo.
const props = defineProps({
    quote: {type: Object, required: true},
    selected: {type: Object, required: true},
    data: {type: Object, required: true},
    direct: Boolean,
    source: Object,
    requestOptions: {type: Array, required: true},
});
// La ficha reutiliza los importes orientativos de la solicitud y los agrupa sin volver a calcular tarifas.
const serviciosSolicitud = computed(() => {
    if (!props.source) return [];
    const extras = props.source.additional_services ?? [];
    const moneda = props.source.pricing_reference?.currency
        ?? extras.find(item => item.pricing_reference)?.pricing_reference.currency ?? 'ARS';
    return [
        {name: props.source.service_name, quantity: 1, answers: props.source.answers,
            pricing_reference: props.source.pricing_reference},
        ...extras,
    ].map((item, index) => ({...item, principal: index === 0,
        price: referenceLine(item.pricing_reference, item.quantity ?? 1, null, moneda), moneda}));
});
const estimacion = computed(() => summarizePrices(serviciosSolicitud.value.map(item => item.price),
    serviciosSolicitud.value[0]?.moneda ?? 'ARS'));

function textoPrecio({min, max}, moneda) {
    return min === max ? money(min, moneda) : `${money(min, moneda)} a ${money(max, moneda)}`;
}
</script>

<template>
    <B v-if="!selected.budget_id && (data.auth?.puede_gestionar ?? data.auth?.is_admin) && !data.preview"
       :href="direct ? '/editor/presupuesto' : '/editor/presupuesto?directo=1'"
       variant="ghost">{{ direct ? 'Crear desde una solicitud' : 'Crear presupuesto directo' }}</B>
    <template v-if="direct">
        <h2>Datos del cliente</h2>
        <F v-model="quote.client.name" label="Nombre del cliente" maxlength="255" :error="quote.errors['client.name']"
           required/>
        <div class="form-grid">
            <F v-model="quote.client.company" label="Empresa" maxlength="255" :error="quote.errors['client.company']"/>
            <F v-model="quote.client.email" label="Correo electrónico" type="email" maxlength="254"
               :error="quote.errors['client.email']"/>
            <F v-model="quote.client.phone" label="Teléfono" type="tel" maxlength="50"
               :error="quote.errors['client.phone']"/>
            <F v-model="quote.client.address" label="Dirección" maxlength="500"
               :error="quote.errors['client.address']"/>
        </div>
    </template>
    <template v-else>
        <h2>Pedido original</h2>
        <F v-if="!selected.budget_id" v-model="quote.request_id" label="Pedido que vas a presupuestar" type="select"
           :disabled="quote.processing" :options="requestOptions" :error="quote.errors.request_id" required/>
        <div v-else-if="source" class="request-fixed-source">
            <span>Pedido que vas a presupuestar</span>
            <strong>SOL-{{ source.id }} · {{ source.title }}</strong>
        </div>
        <p v-if="!selected.budget_id" class="editor-hint">Elegí el pedido original del cliente. El presupuesto se prepara y se guarda por separado.</p>
        <p v-if="!requestOptions.length">Todavía no hay pedidos disponibles para presupuestar.</p>
        <section v-if="source" class="request-context" aria-label="Información de la solicitud">
            <div class="request-detail-layout">
                <div class="request-detail-main">
                    <section class="card request-detail-card">
                        <h3>Alcance de la solicitud</h3>
                        <p class="preserve-lines request-description">{{ source.description || 'Sin descripción adicional.' }}</p>
                        <dl class="request-facts">
                            <div><dt>Plazo solicitado</dt><dd>{{ source.desired_deadline || 'A coordinar' }}</dd></div>
                            <div><dt>Presupuesto indicado</dt><dd>{{ source.budget_reference || 'Sin indicar' }}</dd></div>
                            <div><dt>Visibilidad</dt><dd>{{ source.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div>
                        </dl>
                        <p v-if="source.skills?.length" class="request-skills"><strong>Habilidades solicitadas</strong><br>{{ source.skills.join(' · ') }}</p>
                    </section>
                    <section class="card request-detail-card">
                        <h3>Servicios solicitados</h3>
                        <article v-for="(item, index) in serviciosSolicitud" :key="item.service_id || index" class="request-service">
                            <div class="request-service-heading">
                                <div><p class="request-service-type">{{ item.principal ? 'Principal' : 'Adicional' }}<span v-if="!item.principal && item.quantity && item.quantity !== 1"> · {{ item.quantity }} ud.</span></p>
                                    <strong>{{ item.name }}</strong></div>
                                <strong v-if="item.price.priced" class="request-service-price">{{ textoPrecio(item.price, item.moneda) }}</strong>
                                <small v-else>Pendiente de cotización</small>
                            </div>
                            <p v-if="item.notes" class="preserve-lines request-description">{{ item.notes }}</p>
                            <dl v-if="item.answers?.length" class="request-answers">
                                <div v-for="(answer, answerIndex) in item.answers" :key="answer.id || answerIndex">
                                    <dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd>
                                </div>
                            </dl>
                        </article>
                    </section>
                </div>
                <aside class="request-detail-aside">
                    <section class="card request-detail-card request-estimate-card">
                        <h3>{{ estimacion.hasPrice && estimacion.pending ? 'Subtotal orientativo' : 'Estimación orientativa' }}</h3>
                        <strong v-if="estimacion.hasPrice">{{ textoPrecio(estimacion, serviciosSolicitud[0].moneda) }}</strong>
                        <strong v-else>Pendiente de cotización</strong>
                        <p v-if="estimacion.hasPrice && estimacion.pending">Hay servicios pendientes que no están incluidos en este importe.</p>
                        <p v-else-if="!estimacion.hasPrice">El importe se define al preparar el presupuesto.</p>
                        <p v-else>El importe final se confirma en el presupuesto formal.</p>
                    </section>
                    <section v-if="source.contact" class="card request-detail-card request-contact-card">
                        <h3>Contacto del cliente</h3>
                        <strong>{{ source.contact.name }}</strong>
                        <p v-if="source.contact.company">{{ source.contact.company }}</p>
                        <p v-if="source.contact.cuit">CUIT {{ source.contact.cuit }}</p>
                        <p v-if="source.contact.email">{{ source.contact.email }}</p>
                        <p v-if="source.contact.phone">{{ source.contact.phone }}</p>
                        <p v-if="source.contact.address">{{ source.contact.address }}</p>
                    </section>
                </aside>
            </div>
        </section>
    </template>
    <div class="budget-identification-fields">
        <F v-model="quote.title" label="Título del presupuesto" maxlength="255" :error="quote.errors.title" required/>
        <F v-model="quote.currency_code" label="Moneda del presupuesto" type="select" :options="['ARS', 'USD', 'EUR']"
           :error="quote.errors.currency_code" required/>
    </div>
    <p class="editor-hint">Los importes se ingresan en la moneda elegida. Cambiarla no convierte los precios.</p>
</template>

<style scoped>
.request-context {
    margin: 16px 0 20px;
}

/* Un borrador vinculado conserva la referencia del pedido como dato, no como control deshabilitado. */
.request-fixed-source {
    display: grid;
    gap: 4px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border-left: 3px solid #1f6f7f;
    border-radius: 0 8px 8px 0;
    background: #f5f9fa;
}

.request-fixed-source span {
    color: #446075;
    font-size: .8rem;
}

.request-fixed-source strong {
    color: #153b64;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.budget-identification-fields {
    /* Título y moneda comparten una fila en escritorio y se apilan en móvil. */
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
    align-items: start;
}

.request-detail-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(220px, .9fr);
    align-items: start;
    gap: 14px;
}

.request-detail-main {
    display: grid;
    min-width: 0;
    gap: 14px;
}

.request-detail-card {
    min-width: 0;
    padding: clamp(18px, 3vw, 28px);
    border: 1px solid #1f6f7f22;
    border-radius: 14px;
    background: #fff;
    overflow-wrap: anywhere;
}

.request-detail-card h3 {
    margin: 0 0 10px;
    color: #153b64;
    font-size: 1.05rem;
}

.request-detail-card p {
    margin: 5px 0;
    color: #446075;
    overflow-wrap: anywhere;
}

.request-detail-aside {
    display: grid;
    gap: 14px;
}

.request-estimate-card > strong {
    color: #153b64;
    font: 700 1.35rem/1.25 'Space Grotesk', sans-serif;
}

.request-facts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 14px 0 0;
    padding: 14px;
    border-radius: 10px;
    background: #f5f9fa;
}

.request-facts > div {
    display: grid;
    align-content: start;
    gap: 3px;
    min-width: 0;
}

.request-skills {
    margin: 14px 0 0 !important;
}

.request-facts dt,
.request-answers dt {
    color: #446075;
    font-size: .85rem;
}

.request-facts dd,
.request-answers dd {
    margin: 0;
    color: #153b64;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.request-service {
    padding-top: 10px;
    border-top: 1px solid #1f6f7f22;
}

.request-service-heading > div > strong {
    color: #153b64;
}

.request-service-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px 14px;
}

.request-service-price {
    color: #153b64;
    font: 700 1rem/1.25 'Space Grotesk', sans-serif;
}

.request-service + .request-service {
    margin-top: 12px;
}

.request-service > strong,
.request-contact-card > strong {
    color: #153b64;
}

.request-service-type {
    margin: 0 0 4px !important;
    color: #1f6f7f !important;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.request-answers {
    display: grid;
    gap: 6px;
    margin: 10px 0 0;
}

.request-answers > div {
    display: grid;
    gap: 3px;
    min-width: 0;
    padding: 8px 10px;
    border-radius: 8px;
    background: #f5f9fa;
}

.editor-hint {
    font-size: .9rem;
    color: #446075;
}

@media (max-width: 600px) {
    .budget-identification-fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 0;
    }

    .request-detail-layout {
        grid-template-columns: minmax(0, 1fr);
    }

    .request-detail-aside {
        position: static;
    }

    .request-facts {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
