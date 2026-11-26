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
        <section v-if="source" class="request-context" aria-label="Pedido del cliente">
            <div class="context-heading">
                <div><p class="request-id">Detalle del pedido original</p><h3 v-if="!selected.budget_id">{{ source.title }}</h3></div>
            </div>
            <p class="preserve-lines request-description">{{ source.description }}</p>
            <dl>
                <div>
                    <dt>Servicio principal</dt>
                    <dd>{{ source.service_name }}</dd>
                </div>
                <div>
                    <dt>Plazo solicitado</dt>
                    <dd>{{ source.desired_deadline || 'A coordinar' }}</dd>
                </div>
                <div>
                    <dt>Presupuesto disponible</dt>
                    <dd>{{ source.budget_reference || 'Sin indicar' }}</dd>
                </div>
            </dl>
            <p class="request-services"><strong>Servicios:</strong> {{ [source.service_name, ...(source.additional_services ?? []).map(item => item.name)].filter(Boolean).join(' · ') }}</p>
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
    background: #f5f9fa;
    border: 1px solid #1f6f7f22;
    padding: 16px;
    border-radius: 12px;
    margin: 16px 0;
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

.request-id {
    margin: 0 0 4px;
    color: #1f6f7f;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.request-description {
    margin: 0 0 10px;
}

.request-services {
    margin: 14px 0 0;
    color: #446075;
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
}
</style>
