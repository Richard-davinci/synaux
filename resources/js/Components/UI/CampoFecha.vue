<script setup>
import {computed, ref, useId} from 'vue';
import {VDatePicker, VMenu} from 'vuetify/components';
import B from './AppButton.vue';
import I from './AppIcon.vue';

const props = defineProps({label: String, error: String, min: String, required: Boolean});
const valor = defineModel({type: String, default: ''});
const abierto = ref(false);
const id = useId();

// Las fechas del servidor conservan el día local al pasar al calendario y al formulario.
const fecha = computed(() => valor.value || null);
const texto = computed(() => valor.value ? valor.value.split('-').reverse().join('/') : 'Seleccioná una fecha');
function seleccionar(fecha) {
    valor.value = fecha instanceof Date
        ? [fecha.getFullYear(), String(fecha.getMonth() + 1).padStart(2, '0'), String(fecha.getDate()).padStart(2, '0')].join('-')
        : fecha ?? '';
    abierto.value = false;
}
</script>

<template>
    <div class="field campo-fecha">
        <label :for="id">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label>
        <VMenu v-model="abierto" :close-on-content-click="false" :offset="6" :min-width="0" :max-width="344"
               location="bottom start">
            <template #activator="{props: activador}">
                <button v-bind="activador" :id="id" type="button" class="fecha-activador"
                        :aria-label="label + ', ' + texto" :aria-invalid="Boolean(error)"
                        :aria-required="required || undefined"
                        :aria-describedby="error ? id + '-error' : undefined">
                    {{ texto }}<I name="calendar"/>
                </button>
            </template>
            <v-card class="selector-fecha">
                <VDatePicker :model-value="fecha" :min="min" color="primary" hide-header width="320"
                             :first-day-of-week="1"
                             show-adjacent-months @update:model-value="seleccionar"/>
                <div class="selector-fecha-acciones">
                    <B variant="ghost" compact :disabled="!valor" @click="seleccionar('')">Borrar fecha</B>
                    <B variant="secondary" compact @click="abierto = false">Cerrar</B>
                </div>
            </v-card>
        </VMenu>
        <small v-if="error" :id="id + '-error'" class="error" role="alert">{{ error }}</small>
    </div>
</template>

<style scoped>
/* El botón conserva la densidad del formulario y el menú aplica el tema de synaux. */
.fecha-activador {
    width: 100%;
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid #153b6466;
    border-radius: 8px;
    color: #153b64;
    background: #fff;
    font: inherit;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.fecha-activador:hover { border-color: #1f6f7f; }
.fecha-activador[aria-invalid="true"] { border-color: #a13b42; }
.fecha-activador .icon { width: 18px; height: 18px; flex-shrink: 0; }
/* El encabezado define el contorno superior y el borde fino separa el menú del formulario. */
.selector-fecha {
    width: 320px;
    max-width: calc(100vw - 24px);
    border: 1px solid #153b6433;
    box-shadow: 0 6px 16px #153b6424;
    overflow: hidden;
}
.selector-fecha :deep(.v-date-picker) {
    width: 100%;
    max-width: 100%;
    color: #153b64;
}
.selector-fecha :deep(.v-date-picker-controls) {
    background: #153b64;
    color: #fff;
}
@media (max-width: 360px) {
    .selector-fecha :deep(.v-date-picker) {
        width: 100% !important;
    }
}
.selector-fecha :deep(.v-date-picker-controls .v-btn) {
    color: #fff;
    font-weight: 700;
}
.selector-fecha :deep(.v-date-picker-controls .v-btn:focus-visible) {
    outline-color: #fff;
    outline-offset: -2px;
}
.selector-fecha :deep(.v-date-picker-month__day--adjacent:not(.v-date-picker-month__day--hide-adjacent)) {
    opacity: 1;
    color: #446075;
}
.selector-fecha :deep(.v-date-picker-month__weekday) {
    color: #446075;
    font-weight: 700;
}
.selector-fecha :deep(.v-date-picker-month__day--selected .v-date-picker-month__day-btn) {
    font-weight: 700;
}
.selector-fecha-acciones {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 12px 12px;
    border-top: 1px solid #1f6f7f26;
}
</style>
