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
    client: Object,
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
        <h2>Revisá la solicitud</h2>
        <F v-model="quote.request_id" label="Solicitud relacionada" type="select"
           :disabled="!!selected.budget_id || quote.processing" :options="requestOptions"
           :error="quote.errors.request_id" required/>
        <p v-if="!selected.budget_id" class="editor-hint">Si cambiás la solicitud, se reemplazan el título y los
            conceptos por los del pedido elegido.</p>
        <p v-if="!requestOptions.length">Todavía no hay solicitudes disponibles para presupuestar.</p>
        <section v-if="source" class="request-context" aria-label="Pedido del cliente">
            <div class="context-heading"><h3>SOL-{{ source.id }} · {{ source.title }}</h3><B
                :href="'/solicitudes/' + source.id" variant="ghost">Ver solicitud</B></div>
            <p class="preserve-lines">{{ source.description }}</p>
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
            <div v-if="source.additional_services?.length">
                <h3>Servicios adicionales</h3>
                <div v-for="item in source.additional_services" :key="item.service_id">
                    <p><strong>{{ item.name }} × {{ item.quantity }}</strong><br v-if="item.notes">{{ item.notes }}</p>
                    <dl v-if="item.answers?.length">
                        <div v-for="answer in item.answers" :key="answer.id">
                            <dt>{{ answer.question }}</dt>
                            <dd>{{ answer.label }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            <div v-if="source.answers?.length"><h3>Respuestas de la estimación</h3>
                <dl>
                    <div v-for="answer in source.answers" :key="answer.id">
                        <dt>{{ answer.question }}</dt>
                        <dd>{{ answer.label }}</dd>
                    </div>
                </dl>
            </div>
        </section>
        <section v-if="client" class="client-context"><h3>Contacto del pedido</h3><strong>{{ client.name }}</strong>
            <p>{{ client.email }}</p>
            <p v-if="client.company">{{ client.company }}</p>
            <p v-if="client.phone">{{ client.phone }}</p>
            <p v-if="client.address">{{ client.address }}</p></section>
    </template>
    <F v-model="quote.title" label="Título del presupuesto" maxlength="255" :error="quote.errors.title" required/>
    <F v-model="quote.currency_code" label="Moneda del presupuesto" type="select" :options="['ARS', 'USD', 'EUR']"
       :error="quote.errors.currency_code" required/>
    <p class="editor-hint">Los importes se ingresan en la moneda elegida. Cambiarla no convierte los precios.</p>
</template>

<style scoped>
.request-context, .client-context {
    background: #f5f9fa;
    border: 1px solid #1f6f7f22;
    padding: 16px;
    border-radius: 12px;
    margin: 16px 0;
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

.client-context p {
    margin: 4px 0;
    overflow-wrap: anywhere;
}

.editor-hint {
    font-size: .9rem;
    color: #446075;
}
</style>
