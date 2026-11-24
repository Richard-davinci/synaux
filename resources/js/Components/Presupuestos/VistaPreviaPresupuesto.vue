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
const emit = defineEmits(['expand', 'save']);
</script>

<template>
    <!-- La vista previa usa el documento compartido y refleja los cambios del formulario. -->
    <aside class="budget-summary" aria-label="Vista previa del presupuesto">
        <div class="preview-toolbar">
            <h2>Vista previa</h2>
            <B variant="ghost" @click="emit('expand')">Ampliar</B>
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
    top: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px) + 16px);
    overflow-wrap: anywhere;
    border: 1px solid #1f6f7f22;
    border-radius: 12px;
    background: #fff;
}

.budget-summary > .btn {
    width: 100%;
}

.preview-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid #1f6f7f22;
}

.preview-toolbar h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
}

.document-preview-scroll {
    max-height: 65dvh;
    overflow: auto;
    padding: 12px;
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

@media (max-width: 900px) {
    .budget-summary {
        position: static;
    }
}
</style>
