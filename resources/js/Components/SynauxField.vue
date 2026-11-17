<script setup>
// Agrupa etiqueta, control y error para que los formularios mantengan el valor ante una validación fallida.
defineProps({ modelValue: [String, Number, Boolean, Array], label: String, type: { type: String, default: 'text' }, options: Array, error: String, hint: String, required: Boolean, autocomplete: String, min: [String, Number], max: [String, Number], step: [String, Number], placeholder: String, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
</script>
<template>
    <label class="field">
        <span>{{ label }}<span v-if="required" aria-hidden="true"> *</span></span>
        <textarea v-if="type === 'textarea'" :value="modelValue" :required="required" :disabled="disabled" :placeholder="placeholder" :aria-invalid="!!error" @input="emit('update:modelValue', $event.target.value)" />
        <select v-else-if="type === 'select'" :value="modelValue" :required="required" :disabled="disabled" :aria-invalid="!!error" @change="emit('update:modelValue', $event.target.value)">
            <option value="">Seleccioná una opción</option>
            <option v-for="option in options" :key="option.value ?? option.id ?? option" :value="option.value ?? option.id ?? option">{{ option.label ?? option.name ?? option }}</option>
        </select>
        <input v-else :value="modelValue" :type="type" :required="required" :autocomplete="autocomplete" :min="min" :max="max" :step="step" :disabled="disabled" :placeholder="placeholder" :aria-invalid="!!error" @input="emit('update:modelValue', $event.target.value)">
        <small v-if="error" class="error" role="alert">{{ error }}</small><small v-else-if="hint">{{ hint }}</small>
    </label>
</template>
