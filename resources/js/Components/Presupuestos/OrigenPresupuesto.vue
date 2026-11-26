<script setup>
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';

// Los campos editan el mismo formulario, tanto para una solicitud como para un presupuesto directo.
defineProps({
    quote: {type: Object, required: true},
    selected: {type: Object, required: true},
    data: {type: Object, required: true},
    direct: Boolean,
    source: Object,
    requestOptions: {type: Array, required: true},
});
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
                    <section class="request-detail-card">
                        <h3>Alcance de la solicitud</h3>
                        <p class="preserve-lines request-description">{{ source.description || 'Sin descripción adicional.' }}</p>
                        <dl class="request-facts">
                            <div><dt>Plazo solicitado</dt><dd>{{ source.desired_deadline || 'A coordinar' }}</dd></div>
                            <div><dt>Presupuesto indicado</dt><dd>{{ source.budget_reference || 'Sin indicar' }}</dd></div>
                            <div><dt>Visibilidad</dt><dd>{{ source.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div>
                        </dl>
                    </section>
                    <section class="request-detail-card">
                        <h3>Servicios solicitados</h3>
                        <article class="request-service">
                            <p class="request-service-type">Servicio principal</p>
                            <strong>{{ source.service_name }}</strong>
                            <dl v-if="source.answers?.length" class="request-answers">
                                <div v-for="(answer, index) in source.answers" :key="answer.id || index">
                                    <dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd>
                                </div>
                            </dl>
                        </article>
                        <article v-for="(item, index) in source.additional_services ?? []" :key="item.service_id || index"
                                 class="request-service">
                            <p class="request-service-type">Servicio adicional<span v-if="item.quantity && item.quantity !== 1"> · {{ item.quantity }} ud.</span></p>
                            <strong>{{ item.name }}</strong>
                            <p v-if="item.notes" class="preserve-lines request-description">{{ item.notes }}</p>
                            <dl v-if="item.answers?.length" class="request-answers">
                                <div v-for="(answer, answerIndex) in item.answers" :key="answer.id || answerIndex">
                                    <dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd>
                                </div>
                            </dl>
                        </article>
                    </section>
                </div>
                <aside v-if="source.contact" class="request-detail-card request-contact-card">
                    <h3>Contacto del cliente</h3>
                    <strong>{{ source.contact.name }}</strong>
                    <p v-if="source.contact.company">{{ source.contact.company }}</p>
                    <p v-if="source.contact.cuit">CUIT {{ source.contact.cuit }}</p>
                    <p v-if="source.contact.email">{{ source.contact.email }}</p>
                    <p v-if="source.contact.phone">{{ source.contact.phone }}</p>
                    <p v-if="source.contact.address">{{ source.contact.address }}</p>
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
    padding: 16px;
    border: 1px solid #1f6f7f22;
    border-radius: 12px;
    background: #fff;
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

.request-facts {
    display: grid;
    gap: 8px;
    margin: 14px 0 0;
}

.request-facts > div,
.request-answers > div {
    display: grid;
    grid-template-columns: minmax(130px, .8fr) minmax(0, 1.2fr);
    gap: 10px;
    padding-top: 8px;
    border-top: 1px solid #1f6f7f22;
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
    gap: 8px;
    margin: 10px 0 0;
}

.request-contact-card {
    position: sticky;
    top: 12px;
}

.context-heading h3, .context-heading h2 {
    margin: 0;
}

.request-context dl > div {
    padding: 10px 0;
    border-bottom: 1px solid #1f6f7f22;
}

.request-context dd {
    margin: 4px 0 0;
    font-weight: 700;
    overflow-wrap: anywhere;
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

    .request-contact-card {
        position: static;
    }

    .request-facts > div,
    .request-answers > div {
        grid-template-columns: minmax(0, 1fr);
        gap: 3px;
    }
}
</style>
