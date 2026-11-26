<script setup>
import B from '../UI/AppButton.vue';
import BudgetDocument from './DocumentoPresupuesto.vue';

defineProps({
    budget: {type: Object, required: true},
    client: Object,
    issuer: Object,
    direct: Boolean,
    disabled: Boolean,
    saving: Boolean,
});
const emit = defineEmits(['save']);
</script>

<template>
    <!-- El documento completo queda a la vista mientras se editan los pasos del presupuesto. -->
    <aside class="budget-summary" aria-label="Documento del presupuesto actualizado en vivo">
        <div class="preview-toolbar">
            <div>
                <p class="eyebrow">Documento en vivo</p>
                <h2>{{ direct ? 'Presupuesto para emitir' : 'Presupuesto para enviar' }}</h2>
            </div>
            <span class="preview-live"><span aria-hidden="true"></span> Se actualiza al editar</span>
        </div>
        <div class="document-preview-scroll">
            <BudgetDocument :budget="budget" :client="client" :issuer="issuer"/>
        </div>
        <div class="preview-save">
            <B variant="secondary" :disabled="disabled"
               @click="emit('save')">{{ saving ? 'Guardando…' : 'Guardar borrador' }}</B>
            <p class="editor-hint">{{
                    direct ? 'El borrador queda disponible en Gestión.' : 'El borrador queda en Gestión. El cliente lo verá cuando lo envíes.'
                }}</p>
        </div>
    </aside>
</template>

<style scoped>
.budget-summary {
    position: sticky;
    top: calc(var(--navbar-height, 76px) + var(--workspace-bar-height, 0px) + 68px);
    height: calc(100dvh - var(--navbar-height, 76px) - var(--workspace-bar-height, 0px) - 84px);
    min-height: 420px;
    display: flex;
    flex-direction: column;
    overflow-wrap: anywhere;
    border: 1px solid #1f6f7f22;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 8px 22px #153b6410;
}

.budget-summary > .btn {
    width: 100%;
}

.preview-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    border-bottom: 1px solid #1f6f7f22;
}

.preview-toolbar .eyebrow {
    margin: 0 0 3px;
    font-size: .7rem;
}

.preview-toolbar h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
}

.preview-live {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    flex: none;
    padding: 6px 10px;
    border: 1px solid #1f6f7f2e;
    border-radius: 999px;
    background: #f5f9fa;
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

.document-preview-scroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    padding: clamp(16px, 1.5vw, 24px);
    background: #f5f9fa;
}

.preview-save {
    padding: 16px;
}

.preview-save > .btn {
    width: 100%;
}

.preview-save p {
    margin: 10px 0 0;
}

.editor-hint {
    font-size: .9rem;
    color: #446075;
}

@media (max-width: 1200px) {
    .budget-summary {
        display: none;
    }
}

@media (max-width: 600px) {
    .preview-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
