<script setup>
import SidePanel from '../UI/SidePanel.vue';
import B from '../UI/AppButton.vue';
import BudgetDocument from './DocumentoPresupuesto.vue';

defineProps({
    visible: Boolean,
    budget: {type: Object, required: true},
    client: Object,
    issuer: Object,
    direct: Boolean,
    step: Number,
    disabled: Boolean,
    saving: Boolean,
    persistent: Boolean,
});
const emit = defineEmits(['close', 'save-draft', 'send']);
</script>

<template>
    <SidePanel title="Vista previa del presupuesto" :model-value="visible" :persistent="persistent" :max-width="960"
               @update:model-value="value => !value && emit('close')">
        <div class="synaux-app budget-preview-panel">
            <section class="budget-preview-content" aria-label="Documento completo actualizado en vivo">
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
            <B variant="secondary" :disabled="disabled || saving" @click="emit('save-draft')">
                {{ saving ? 'Guardando…' : 'Guardar borrador' }}
            </B>
            <B variant="ghost" :disabled="persistent" @click="emit('close')">Seguir editando</B>
            <B v-if="step === 3" :disabled="disabled || persistent" arrow @click="emit('send')">
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
}
</style>
