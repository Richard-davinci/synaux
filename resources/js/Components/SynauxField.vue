<script setup>
import { ref, useId, watch } from 'vue';
import I from './SynauxIcon.vue';
// Agrupa etiqueta, control y error para que los formularios mantengan el valor ante una validación fallida.
const props = defineProps({ modelValue: [String, Number, Boolean, Array], label: String, type: { type: String, default: 'text' }, options: Array, error: String, hint: String, required: Boolean, autocomplete: String, min: [String, Number], max: [String, Number], step: [String, Number], placeholder: String, disabled: Boolean, minlength: [String, Number], maxlength: [String, Number] });
const emit = defineEmits(['update:modelValue']);
// Cada campo conserva su visibilidad de forma independiente y vuelve a ocultarse al limpiar o deshabilitar el formulario.
const inputId = useId();
const passwordVisible = ref(false);
watch([() => props.modelValue, () => props.disabled, () => props.type], ([value, disabled, type]) => {
    if (!value || disabled || type !== 'password') passwordVisible.value = false;
});
</script>
<template>
    <div class="field">
        <label :for="inputId">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label>
        <textarea :id="inputId" v-if="type === 'textarea'" :value="modelValue" :required="required" :minlength="minlength" :maxlength="maxlength" :disabled="disabled" :placeholder="placeholder" :aria-invalid="!!error" @input="emit('update:modelValue', $event.target.value)" />
        <select :id="inputId" v-else-if="type === 'select'" :value="modelValue" :required="required" :disabled="disabled" :aria-invalid="!!error" @change="emit('update:modelValue', $event.target.value)">
            <!-- Los filtros ya incluyen «Todos» con valor vacío; no se duplica con una opción inicial. -->
            <option v-if="!options?.some(option => (option?.value ?? option?.id ?? option) === '')" value="">Seleccioná una opción</option>
            <option v-for="option in options" :key="option.value ?? option.id ?? option" :value="option.value ?? option.id ?? option">{{ option.label ?? option.name ?? option }}</option>
        </select>
        <!-- El botón solo cambia la presentación; no modifica el valor ni envía el formulario. -->
        <div v-else-if="type === 'password'" class="password-control">
            <input :id="inputId" :value="modelValue" :type="passwordVisible ? 'text' : 'password'" :required="required" :autocomplete="autocomplete" :minlength="minlength" :maxlength="maxlength" :disabled="disabled" :placeholder="placeholder" :aria-invalid="!!error" @input="emit('update:modelValue', $event.target.value)">
            <button type="button" class="password-toggle" :disabled="disabled" :aria-label="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'" :title="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-controls="inputId" :aria-pressed="passwordVisible" @click="passwordVisible = !passwordVisible"><I :name="passwordVisible ? 'eye-off' : 'eye'" /></button>
        </div>
        <input v-else :id="inputId" :value="modelValue" :type="type" :required="required" :autocomplete="autocomplete" :minlength="minlength" :maxlength="maxlength" :min="min" :max="max" :step="step" :disabled="disabled" :placeholder="placeholder" :aria-invalid="!!error" @input="emit('update:modelValue', $event.target.value)">
        <small v-if="error" class="error" role="alert">{{ error }}</small><small v-else-if="hint">{{ hint }}</small>
    </div>
</template>

<style scoped>
/* El control queda dentro del campo y reserva espacio para que el texto nunca se superponga al icono. */
.password-control { position: relative; min-width: 0; }
.password-control input { width: 100%; padding-right: 54px; }
.password-toggle { position: absolute; top: 50%; right: 4px; transform: translateY(-50%); display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 40px; padding: 0; border: 0; border-radius: 6px; color: #153b64; background: transparent; cursor: pointer; }
.password-toggle:hover { background: #f5f9fa; }
.password-toggle:focus-visible { outline: 2px solid #1f6f7f; outline-offset: -2px; }
.password-toggle:disabled { opacity: .5; cursor: default; }
</style>
