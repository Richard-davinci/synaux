<script setup>
import SidePanel from '../UI/SidePanel.vue';
import B from '../UI/AppButton.vue';
import BudgetDocument from './DocumentoPresupuesto.vue';

const props = defineProps({
    visible: Boolean,
    budget: {type: Object, required: true},
    solicitud: Object,
    vistaActiva: {type: String, default: 'presupuesto'},
    client: Object,
    issuer: Object,
    direct: Boolean,
    step: Number,
    disabled: Boolean,
    saving: Boolean,
    persistent: Boolean,
});
const emit = defineEmits(['close', 'save-draft', 'send', 'change-view']);

function moverPestana(event) {
    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    emit('change-view', props.vistaActiva === 'pedido' ? 'presupuesto' : 'pedido');
}
</script>

<template>
    <SidePanel title="Pedido y presupuesto" :model-value="visible" :persistent="persistent" :max-width="960"
               @update:model-value="value => !value && emit('close')">
        <div class="synaux-app budget-preview-panel">
            <div v-if="!direct && solicitud" class="review-tabs" role="tablist" aria-label="Contenido para revisar">
                <button id="tab-pedido-original" type="button" role="tab"
                        :aria-selected="vistaActiva === 'pedido'" aria-controls="panel-pedido-original"
                        :tabindex="vistaActiva === 'pedido' ? 0 : -1" @click="emit('change-view', 'pedido')"
                        @keydown="moverPestana">
                    Pedido original
                </button>
                <button id="tab-presupuesto" type="button" role="tab"
                        :aria-selected="vistaActiva === 'presupuesto'" aria-controls="panel-presupuesto"
                        :tabindex="vistaActiva === 'presupuesto' ? 0 : -1" @click="emit('change-view', 'presupuesto')"
                        @keydown="moverPestana">
                    Presupuesto
                </button>
            </div>
            <section v-if="vistaActiva === 'pedido' && !direct && solicitud" id="panel-pedido-original"
                     class="request-preview-content" role="tabpanel" aria-labelledby="tab-pedido-original">
                <div class="preview-heading">
                    <div>
                        <p class="eyebrow">Solicitud original · SOL-{{ solicitud.id }}</p>
                        <h2>{{ solicitud.title }}</h2>
                    </div>
                </div>
                <section class="request-preview-card">
                    <h3>Qué necesita el cliente</h3>
                    <p class="preserve-lines">{{ solicitud.description || 'Sin descripción adicional.' }}</p>
                    <dl class="request-facts">
                        <div><dt>Servicio principal</dt><dd>{{ solicitud.service_name || 'Sin especificar' }}</dd></div>
                        <div><dt>Plazo deseado</dt><dd>{{ solicitud.desired_deadline || 'A coordinar' }}</dd></div>
                        <div><dt>Presupuesto de referencia</dt><dd>{{ solicitud.budget_reference || 'Sin indicar' }}</dd></div>
                        <div><dt>Visibilidad</dt><dd>{{ solicitud.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div>
                    </dl>
                    <p v-if="solicitud.skills?.length" class="request-skills"><strong>Habilidades solicitadas</strong><br>{{ solicitud.skills.join(' · ') }}</p>
                </section>
                <section v-if="solicitud.additional_services?.length" class="request-preview-card">
                    <h3>Servicios adicionales</h3>
                    <article v-for="(item, index) in solicitud.additional_services" :key="item.service_id || index"
                             class="request-service">
                        <h4>{{ item.name }}<span v-if="item.quantity && item.quantity !== 1"> · {{ item.quantity }}</span></h4>
                        <p v-if="item.notes" class="preserve-lines">{{ item.notes }}</p>
                        <dl v-if="item.answers?.length" class="request-answers">
                            <div v-for="(answer, answerIndex) in item.answers" :key="answer.id || answerIndex">
                                <dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd>
                            </div>
                        </dl>
                    </article>
                </section>
                <section v-if="solicitud.answers?.length" class="request-preview-card">
                    <h3>Respuestas del pedido</h3>
                    <dl class="request-answers">
                        <div v-for="(answer, index) in solicitud.answers" :key="answer.id || index">
                            <dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd>
                        </div>
                    </dl>
                </section>
                <section v-if="solicitud.contact" class="request-preview-card">
                    <h3>Datos de contacto</h3>
                    <p><strong>{{ solicitud.contact.name }}</strong></p>
                    <p>{{ solicitud.contact.email }}</p>
                    <p v-if="solicitud.contact.company">{{ solicitud.contact.company }}</p>
                    <p v-if="solicitud.contact.phone">{{ solicitud.contact.phone }}</p>
                    <p v-if="solicitud.contact.address">{{ solicitud.contact.address }}</p>
                </section>
            </section>
            <section v-else-if="vistaActiva === 'presupuesto' || direct" id="panel-presupuesto"
                     class="budget-preview-content" role="tabpanel"
                     :aria-labelledby="direct ? undefined : 'tab-presupuesto'"
                     aria-label="Documento completo actualizado en vivo">
                <div class="preview-heading">
                    <div>
                        <p class="eyebrow">Documento en vivo</p>
                        <h2>{{ direct ? 'Revisá el presupuesto para emitir' : 'Revisá el presupuesto para enviar' }}</h2>
                    </div>
                    <span class="preview-live"><span aria-hidden="true"></span> Se actualiza al editar</span>
                </div>
                <BudgetDocument :budget="budget" :client="client" :issuer="issuer"/>
            </section>
        </div>
        <template #footer>
            <B v-if="vistaActiva === 'pedido' && !direct" variant="secondary"
               @click="emit('change-view', 'presupuesto')">Volver al presupuesto</B>
            <B v-else variant="secondary" :disabled="disabled || saving" @click="emit('save-draft')">
                {{ saving ? 'Guardando…' : 'Guardar borrador' }}
            </B>
            <B v-if="vistaActiva !== 'pedido' || direct" variant="ghost" :disabled="persistent" @click="emit('close')">Seguir editando</B>
            <B v-if="step === 3 && (vistaActiva !== 'pedido' || direct)" :disabled="disabled || persistent" arrow @click="emit('send')">
                {{ direct ? 'Emitir presupuesto' : 'Enviar presupuesto' }}
            </B>
        </template>
    </SidePanel>
</template>

<style scoped>
.budget-preview-panel {
    min-height: 100%;
    background: #f5f9fa;
}

.budget-preview-content {
    width: min(100%, 860px);
    margin-inline: auto;
    padding: clamp(18px, 3vw, 32px);
}

.review-tabs {
    position: sticky;
    top: 0;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    padding: 14px clamp(16px, 3vw, 28px);
    border-bottom: 1px solid #1f6f7f22;
    background: #fff;
}

.review-tabs button {
    min-height: 44px;
    padding: 9px 14px;
    border: 1px solid #1f6f7f33;
    border-radius: 9px;
    background: #f5f9fa;
    color: #153b64;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.review-tabs button[aria-selected='true'] {
    border-color: #153b64;
    background: #153b64;
    color: #fff;
    box-shadow: 0 3px 9px #153b6426;
}

.review-tabs button:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 2px;
}

.request-preview-content {
    width: min(100%, 800px);
    margin-inline: auto;
    padding: clamp(18px, 3vw, 30px);
}

.request-preview-content .preview-heading {
    align-items: flex-start;
}

.request-preview-content .preview-heading h2 {
    overflow-wrap: anywhere;
}

.request-preview-card {
    margin-top: 16px;
    padding: 18px;
    border: 1px solid #d4e3e8;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 12px #153b640a;
}

.request-preview-card h3 {
    margin: 0 0 12px;
    color: #153b64;
    font-size: 1.05rem;
}

.request-preview-card h4 {
    margin: 0 0 6px;
    color: #153b64;
    font-size: .95rem;
}

.request-preview-card p {
    margin: 5px 0;
    color: #446075;
    overflow-wrap: anywhere;
}

.request-facts, .request-answers {
    margin: 14px 0 0;
}

.request-skills {
    margin: 14px 0 0;
    color: #446075;
}

.request-facts > div, .request-answers > div {
    display: grid;
    grid-template-columns: minmax(130px, .7fr) minmax(0, 1.3fr);
    gap: 12px;
    padding: 10px 0;
    border-top: 1px solid #d4e3e8;
}

.request-facts dt, .request-answers dt {
    color: #567189;
    font-size: .86rem;
}

.request-facts dd, .request-answers dd {
    margin: 0;
    color: #153b64;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.request-service + .request-service {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #d4e3e8;
}

.preview-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.preview-heading .eyebrow {
    margin: 0 0 4px;
    font-size: .72rem;
}

.preview-heading h2 {
    margin: 0;
    color: #153b64;
    font-family: 'Space Grotesk', sans-serif;
    font-size: clamp(1.15rem, 2vw, 1.4rem);
    font-weight: 700;
}

.preview-live {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    flex: none;
    padding: 7px 11px;
    border: 1px solid #1f6f7f2e;
    border-radius: 999px;
    background: #fff;
    color: #446075;
    font-size: .75rem;
    font-weight: 700;
}

.preview-live span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #26b4a2;
    box-shadow: 0 0 0 3px #26b4a21c;
}

.budget-preview-content :deep(.budget-document) {
    border-radius: 14px;
    box-shadow: 0 8px 24px #153b6410;
}

.budget-preview-content :deep(.document-heading) {
    padding: clamp(22px, 3vw, 32px);
}

.budget-preview-content :deep(.document-heading .brand-logo) {
    width: 124px;
}

.budget-preview-content :deep(.document-label) {
    font-size: 1.65rem;
}

.budget-preview-content :deep(.document-number),
.budget-preview-content :deep(.document-dates),
.budget-preview-content :deep(.party-content),
.budget-preview-content :deep(.document-items table),
.budget-preview-content :deep(.document-totals),
.budget-preview-content :deep(.document-conditions > div) {
    font-size: .95rem;
}

.budget-preview-content :deep(.document-content) {
    padding: clamp(22px, 3vw, 32px);
}

.budget-preview-content :deep(.document-title) {
    font-size: 1.5rem;
}

.budget-preview-content :deep(.document-items td),
.budget-preview-content :deep(.document-items th) {
    padding: 12px 10px;
}

@media (max-width: 600px) {
    .preview-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .request-facts > div, .request-answers > div {
        grid-template-columns: 1fr;
        gap: 3px;
    }
}
</style>
