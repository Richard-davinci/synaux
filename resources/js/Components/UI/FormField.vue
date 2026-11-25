<script setup>
import {computed, ref, useId, watch} from 'vue';
import {VSelect, VTextarea, VTextField} from 'vuetify/components';
import I from './AppIcon.vue';

// Todos los formularios comparten controles Vuetify compactos y conservan sus etiquetas externas.
const props = defineProps({
    modelValue: [String, Number, Boolean, Array],
    label: String,
    type: {type: String, default: 'text'},
    options: Array,
    error: String,
    hint: String,
    required: Boolean,
    autocomplete: String,
    min: [String, Number],
    max: [String, Number],
    step: [String, Number],
    placeholder: String,
    disabled: Boolean,
    readonly: Boolean,
    minlength: [String, Number],
    maxlength: [String, Number]
});
const emit = defineEmits(['update:modelValue']);
const inputId = useId();
const helpId = `${inputId}-help`;
const selectControl = ref(null);
const selectInvalid = ref(false);
const passwordVisible = ref(false);
const hasSelection = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '' && (!Array.isArray(props.modelValue) || props.modelValue.length > 0));
const items = computed(() => {
    const values = (props.options ?? []).map(option => ({
        value: option?.value ?? option?.id ?? option,
        title: String(option?.label ?? option?.name ?? option)
    }));
    return values.some(option => option.value === '') ? values : [{
        value: '',
        title: props.placeholder || 'Seleccioná una opción'
    }, ...values];
});
const message = computed(() => props.error || (selectInvalid.value ? 'Seleccioná una opción.' : ''));
const common = computed(() => ({
    id: inputId,
    variant: 'outlined',
    density: 'compact',
    color: 'primary',
    hideDetails: true,
    disabled: props.disabled,
    error: Boolean(message.value),
    'aria-invalid': Boolean(message.value),
    'aria-describedby': message.value || props.hint ? helpId : undefined
}));
watch([() => props.modelValue, () => props.disabled, () => props.type], ([value, disabled, type]) => {
    selectInvalid.value = false;
    if (!value || disabled || type !== 'password') passwordVisible.value = false;
});

function update(value) {
    emit('update:modelValue', value ?? '');
}

// VSelect usa un campo de búsqueda de solo lectura; este testigo conserva la validación HTML del formulario.
function invalidSelection() {
    selectInvalid.value = true;
    selectControl.value?.focus();
}
</script>

<template>
    <div class="field synaux-field">
        <label :for="inputId">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label>
        <template v-if="type === 'select'">
            <VSelect ref="selectControl" class="synaux-field-control" v-bind="common" :model-value="modelValue"
                     :items="items" item-title="title" item-value="value"
                     :placeholder="placeholder || 'Seleccioná una opción'" :aria-required="required"
                     :menu-props="{maxHeight:280}" @update:model-value="update"/>
            <input v-if="required" class="select-validity-proxy" type="text" tabindex="-1" aria-hidden="true"
                   :value="hasSelection ? 'selected' : ''" :disabled="disabled" required
                   @invalid.prevent="invalidSelection">
        </template>
        <VTextarea v-else-if="type === 'textarea'" class="synaux-field-control" v-bind="common"
                   :model-value="modelValue" :required="required" :minlength="minlength" :maxlength="maxlength"
                   :placeholder="placeholder" rows="3" auto-grow @update:model-value="update"/>
        <VTextField v-else class="synaux-field-control" v-bind="common" :model-value="modelValue"
                    :type="type === 'password' ? (passwordVisible ? 'text' : 'password') : type" :required="required"
                    :readonly="readonly"
                    :autocomplete="autocomplete" :minlength="minlength" :maxlength="maxlength" :min="min" :max="max"
                    :step="step" :placeholder="placeholder" @update:model-value="update">
            <template v-if="type === 'password'" #append-inner>
                <button type="button" class="password-toggle" :disabled="disabled"
                        :aria-label="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                        :title="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-controls="inputId"
                        :aria-pressed="passwordVisible" @click="passwordVisible = !passwordVisible"><I
                    :name="passwordVisible ? 'eye-off' : 'eye'"/></button>
            </template>
        </VTextField>
        <small v-if="message" :id="helpId" class="error" role="alert">{{ message }}</small><small v-else-if="hint"
                                                                                                  :id="helpId">{{
            hint
        }}</small>
    </div>
</template>

<style scoped>
/* Se restablecen los estilos del input interno para que el CSS nativo anterior no agrande Vuetify. */
.synaux-field {
    position: relative;
    min-width: 0;
    gap: 6px;
    margin-bottom: 16px;
}

.synaux-field .synaux-field-control {
    min-width: 0;
    font-family: 'Nunito', sans-serif;
    font-size: 1rem;
    font-weight: 400;
}

.synaux-field .synaux-field-control :deep(.v-field) {
    border-radius: 8px;
    background: #fff;
}

.synaux-field .synaux-field-control :deep(input), .synaux-field .synaux-field-control :deep(textarea) {
    min-height: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    outline: none;
}

.synaux-field .synaux-field-control :deep(input.v-field__input), .synaux-field .synaux-field-control :deep(textarea.v-field__input) {
    padding-block: var(--v-field-input-padding-top) var(--v-field-input-padding-bottom);
    padding-inline: var(--v-field-padding-start) var(--v-field-padding-end);
}

.synaux-field .synaux-field-control :deep(.v-field__input input) {
    padding: 0;
    padding-inline: inherit;
}

.synaux-field .synaux-field-control :deep(textarea:not(.v-textarea__sizer)) {
    min-height: 96px;
}

/* El testigo no ocupa una fila ni recibe foco; el error dirige al selector visible. */
.synaux-field .select-validity-proxy {
    position: absolute;
    width: 1px;
    height: 1px;
    min-height: 0;
    padding: 0;
    border: 0;
    opacity: 0;
    pointer-events: none;
}

.synaux-field .synaux-field-control :deep(.v-field__append-inner) {
    align-items: center;
    padding-block: 0;
}

.password-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #153b64;
    cursor: pointer;
}

.password-toggle:hover {
    background: #1f6f7f0a;
}

.password-toggle:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 0;
}

.password-toggle:disabled {
    opacity: .5;
    cursor: default;
}

@media (pointer: coarse) {
    .password-toggle {
        min-width: 44px;
        min-height: 44px;
    }
}
</style>
