<script setup>
import I from './AppIcon.vue';

// Vuetify conserva el foco, Escape y el bloqueo de scroll; la presentación abre desde la derecha.
defineOptions({inheritAttrs: false});
const props = defineProps({
    modelValue: Boolean,
    persistent: Boolean,
    maxWidth: {type: [Number, String], default: 560},
    title: {type: String, default: 'Detalles'},
    bodyLabel: String,
    closeLabel: {type: String, default: 'Cerrar panel lateral'},
});
const emit = defineEmits(['update:modelValue']);

function update(value) {
    if (!value && props.persistent) return;
    emit('update:modelValue', value);
}
</script>

<template>
    <v-dialog v-bind="$attrs" :model-value="modelValue" :persistent="persistent" :max-width="maxWidth"
              class="synaux-side-panel"
              :style="{ '--side-panel-width': typeof maxWidth === 'number' || /^\d+$/.test(String(maxWidth)) ? `${maxWidth}px` : maxWidth }"
              transition="slide-x-reverse-transition" :aria-label="$attrs['aria-labelledby'] ? undefined : title"
              @update:model-value="update">
        <!-- Los accesos y acciones opcionales usan el mismo panel y conservan su recorrido por teclado. -->
        <template v-if="$slots.activator" #activator="activator">
            <slot name="activator" v-bind="activator"/>
        </template>
        <div class="synaux-app side-panel-surface">
            <div class="side-panel-heading"><span>{{ title }}</span>
                <button type="button" class="side-panel-close" :disabled="persistent" :aria-label="closeLabel"
                        @click="update(false)"><I name="close"/></button>
            </div>
            <div class="side-panel-body" :role="bodyLabel ? 'region' : undefined"
                 :aria-label="bodyLabel" :tabindex="bodyLabel ? 0 : undefined">
                <slot/>
            </div>
            <div v-if="$slots.footer" class="side-panel-footer">
                <slot name="footer"/>
            </div>
        </div>
    </v-dialog>
</template>

<style>
/* El ancho compacto depende del contenido; el panel ocupa como máximo el ancho disponible en móvil. */
.v-dialog.synaux-side-panel {
    justify-content: flex-end;
    align-items: stretch;
}

.v-dialog.synaux-side-panel > .v-overlay__content {
    position: fixed;
    inset: 0 0 0 auto;
    margin: 0 !important;
    width: min(100vw, var(--side-panel-width, 560px));
    max-width: 100vw;
    height: 100dvh;
    max-height: 100dvh !important;
}

.synaux-side-panel .side-panel-surface {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    min-height: 0;
    border-radius: 0;
    background: #fff;
    color: #153b64;
    box-shadow: -8px 0 32px #153b6424;
    font-family: 'Nunito', sans-serif;
}

.synaux-side-panel .side-panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    gap: 16px;
    min-height: 64px;
    padding: 8px max(16px, env(safe-area-inset-right)) 8px 24px;
    border-bottom: 1px solid #1f6f7f22;
    background: #f5f9fa;
}

.synaux-side-panel .side-panel-heading > span {
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    color: #153b64;
}

.synaux-side-panel .side-panel-close {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #153b64;
    cursor: pointer;
}

.synaux-side-panel .side-panel-close:hover {
    background: #1f6f7f14;
}

.synaux-side-panel .side-panel-close:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

.synaux-side-panel .side-panel-close:disabled {
    opacity: .45;
    cursor: wait;
}

.synaux-side-panel .side-panel-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding-bottom: env(safe-area-inset-bottom);
}

/* Las acciones visibles quedan al pie aunque el contenido central se desplace. */
.synaux-side-panel .side-panel-footer {
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
    gap: 12px;
    padding: 12px clamp(14px, 3vw, 24px);
    border-top: 1px solid #1f6f7f26;
}

.synaux-side-panel .side-panel-body[tabindex]:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: -3px;
}

/* Un solo contenedor desplaza el contenido; las tarjetas anteriores dejan de simular una ventana. */
.synaux-side-panel .synaux-app .card.modal-card {
    width: 100%;
    max-width: none;
    max-height: none;
    overflow: visible !important;
    margin: 0;
    padding: 24px;
    border: 0;
    border-radius: 0;
    box-shadow: none;
}

.synaux-side-panel .notification-center {
    max-height: none;
    overflow: visible;
    border-radius: 0;
}

.synaux-side-panel .side-panel-body .form-actions {
    flex-wrap: wrap;
}

@media (max-width: 600px) {
    .synaux-side-panel .side-panel-heading {
        padding-left: 16px;
    }

    .synaux-side-panel .synaux-app .card.modal-card {
        padding: 20px 16px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .synaux-side-panel, .synaux-side-panel * {
        transition: none !important;
        animation: none !important;
    }
}
</style>
