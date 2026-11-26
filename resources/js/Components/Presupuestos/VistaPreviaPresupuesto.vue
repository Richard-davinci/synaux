<script setup>
import {computed, ref} from 'vue';
import {useDisplay} from 'vuetify';
import SidePanel from '../UI/SidePanel.vue';
import B from '../UI/AppButton.vue';
import I from '../UI/AppIcon.vue';
import BudgetDocument from './DocumentoPresupuesto.vue';

// La vista queda fija junto al formulario en escritorio y se abre en un panel angosto en pantallas chicas.
defineProps({
    visible: Boolean,
    vistaPreviaVisible: Boolean,
    budget: {type: Object, required: true},
    client: Object,
    issuer: Object,
    direct: Boolean,
    step: Number,
    disabled: Boolean,
    saving: Boolean,
    persistent: Boolean,
});
const emit = defineEmits(['close', 'ocultar-vista', 'save-draft', 'send']);
const {width} = useDisplay();
const vistaMovil = computed(() => width.value <= 1260);
const panelFijo = ref(null);

function irAVista() {
    panelFijo.value?.scrollIntoView({behavior: 'smooth', block: 'start'});
}

defineExpose({irAVista});
</script>

<template>
    <aside v-if="!vistaMovil && vistaPreviaVisible" ref="panelFijo" class="budget-preview-fixed" aria-label="Vista previa del presupuesto">
        <div class="preview-heading">
            <div>
                <p class="eyebrow">Documento en vivo</p>
                <h2>Vista previa del presupuesto</h2>
            </div>
            <div class="preview-heading-actions">
                <span class="preview-live"><span aria-hidden="true"></span> Actualizada</span>
                <button type="button" class="preview-toggle" title="Ocultar vista previa" aria-label="Ocultar vista previa"
                        @click="emit('ocultar-vista')"><I name="panel-hide"/></button>
            </div>
        </div>
        <div class="preview-document-scroll">
            <section class="budget-preview-content" aria-label="Documento completo actualizado en vivo">
                <BudgetDocument :budget="budget" :client="client" :issuer="issuer"/>
            </section>
        </div>
        <footer class="preview-actions">
            <B variant="secondary" compact :disabled="disabled || saving" @click="emit('save-draft')">
                {{ saving ? 'Guardando…' : 'Guardar borrador' }}
            </B>
            <B v-if="step === 3" compact :disabled="disabled || persistent" arrow @click="emit('send')">
                {{ direct ? 'Emitir presupuesto' : 'Enviar presupuesto' }}
            </B>
        </footer>
    </aside>
    <SidePanel v-if="vistaMovil" title="Vista previa del presupuesto" :model-value="visible" :persistent="persistent" :max-width="560"
               @update:model-value="value => !value && emit('close')">
        <div class="synaux-app budget-preview-panel">
            <section class="budget-preview-content" aria-label="Documento completo actualizado en vivo">
                <div class="preview-heading">
                    <div>
                        <p class="eyebrow">Documento en vivo</p>
                        <h2>{{ direct ? 'Revisá el presupuesto para emitir' : 'Revisá el presupuesto para enviar' }}</h2>
                    </div>
                    <span class="preview-live"><span aria-hidden="true"></span> Actualizada</span>
                </div>
                <BudgetDocument :budget="budget" :client="client" :issuer="issuer"/>
            </section>
        </div>
        <template #footer>
            <div class="preview-mobile-actions">
                <B variant="secondary" compact :disabled="disabled || saving" @click="emit('save-draft')">
                    {{ saving ? 'Guardando…' : 'Guardar borrador' }}
                </B>
                <B variant="ghost" compact :disabled="persistent" @click="emit('close')">Seguir editando</B>
                <B v-if="step === 3" compact :disabled="disabled || persistent" arrow @click="emit('send')">
                    {{ direct ? 'Emitir presupuesto' : 'Enviar presupuesto' }}
                </B>
            </div>
        </template>
    </SidePanel>
</template>

<style scoped>
/* La previsualización conserva el espacio de lectura y fija las acciones fuera del documento desplazable. */
.budget-preview-fixed {
    position: fixed;
    inset: var(--navbar-height, 76px) 0 0 auto;
    display: flex;
    flex-direction: column;
    width: var(--budget-preview-width, clamp(380px, 32vw, 460px));
    min-height: 0;
    overflow: hidden;
    border: 1px solid #1f6f7f26;
    border-right: 0;
    border-bottom: 0;
    border-radius: 14px 0 0 0;
    background: #fff;
    box-shadow: var(--card-shadow);
    z-index: 80;
}

.budget-preview-fixed > .preview-heading {
    flex-wrap: wrap;
    flex: 0 0 auto;
    margin: 0;
    padding: 14px 16px;
    border-bottom: 1px solid #1f6f7f22;
    background: #f5f9fa;
}

.preview-heading-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-toggle {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border: 1px solid #1f6f7f26;
    border-radius: 8px;
    background: #fff;
    color: #153b64;
    cursor: pointer;
}

.preview-toggle .icon {
    width: 18px;
    height: 18px;
}

.preview-toggle:hover {
    background: #eef6f7;
}

.preview-toggle:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

.budget-preview-fixed .preview-heading h2 {
    font-size: 1rem;
}

.preview-document-scroll {
    flex: 1 1 auto;
    min-height: 0;
    padding: 12px;
    overflow: auto;
    overscroll-behavior: contain;
    background: #f5f9fa;
}

.preview-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    flex: 0 0 auto;
    padding: 10px 12px;
    border-top: 1px solid #1f6f7f26;
    background: #fff;
}

.preview-actions > :only-child {
    grid-column: 1 / -1;
}

.preview-mobile-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
    gap: 8px;
}

.budget-preview-panel {
    min-height: 100%;
    background: #f5f9fa;
}

.budget-preview-content {
    width: 100%;
    margin-inline: auto;
    padding: 12px;
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
    font-size: clamp(1rem, 1.4vw, 1.15rem);
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
    border-radius: 10px;
    box-shadow: 0 3px 12px #153b6410;
}

.budget-preview-content :deep(.document-parties) {
    grid-template-columns: minmax(0, 1fr);
}

.budget-preview-content :deep(.document-heading) {
    padding: 16px;
}

.budget-preview-content :deep(.document-heading .brand-logo) {
    width: 88px;
}

.budget-preview-content :deep(.document-label) {
    font-size: 1.1rem;
}

.budget-preview-content :deep(.document-number),
.budget-preview-content :deep(.document-dates),
.budget-preview-content :deep(.party-content),
.budget-preview-content :deep(.document-items table),
.budget-preview-content :deep(.document-totals),
.budget-preview-content :deep(.document-conditions > div) {
    font-size: .78rem;
}

.budget-preview-content :deep(.document-content) {
    padding: 14px;
}

.budget-preview-content :deep(.document-title) {
    font-size: 1.05rem;
}

.budget-preview-content :deep(.document-items td),
.budget-preview-content :deep(.document-items th) {
    padding: 7px 6px;
}

.budget-preview-content :deep(.document-items table) {
    min-width: 0;
}

.budget-preview-content :deep(.document-column-concept) {
    width: 72%;
}

.budget-preview-content :deep(.document-column-amount) {
    width: 28%;
}

@media (max-width: 1260px) {
    .preview-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
