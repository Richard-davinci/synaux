<script setup>
defineProps({
    modelValue: {type: [String, Number], default: ''},
    label: {type: String, required: true},
    options: {type: Array, default: () => []},
    error: {type: String, default: ''},
    required: {type: Boolean, default: true},
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <fieldset class="selector-segmentado" :aria-invalid="!!error">
        <legend>{{ label }}{{ required ? ' *' : ' (opcional)' }}</legend>
        <div class="selector-segmentado-opciones" role="radiogroup" :aria-label="label">
            <label v-for="option in options" :key="option.id"
                   class="selector-segmentado-opcion"
                   :class="{ 'esta-seleccionada': String(modelValue) === String(option.id) }">
                <input type="radio" :name="'segmento-' + label" :value="String(option.id)"
                       :checked="String(modelValue) === String(option.id)"
                       @change="emit('update:modelValue', String(option.id))">
                <span>{{ option.label }}</span>
            </label>
        </div>
        <small v-if="error" class="error" role="alert">{{ error }}</small>
    </fieldset>
</template>

<style scoped>
.selector-segmentado {
    min-width: 0;
    padding: 0;
    border: 0;
    margin: 0 0 20px;
}

.selector-segmentado legend {
    margin-bottom: 9px;
    color: #173f68;
    font-size: .95rem;
    font-weight: 600;
}

.selector-segmentado-opciones {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 5px;
    padding: 5px;
    border: 1px solid #c9dce3;
    border-radius: 12px;
    background: #f3f8f9;
}

.selector-segmentado-opcion {
    position: relative;
    display: flex;
    min-height: 42px;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border-radius: 8px;
    color: #496681;
    cursor: pointer;
    font-size: .9rem;
    font-weight: 500;
    text-align: center;
    transition: color .16s ease, background-color .16s ease, box-shadow .16s ease;
}

.selector-segmentado-opcion input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.selector-segmentado-opcion:hover {
    background: #e7f1f3;
    color: #173f68;
}

.selector-segmentado-opcion.esta-seleccionada {
    background: #fff;
    box-shadow: 0 1px 4px #173f6820, inset 0 -3px #25b5a6;
    color: #173f68;
    font-weight: 700;
}

.selector-segmentado-opcion:focus-within {
    outline: 2px solid #267a8c;
    outline-offset: 2px;
}

.error {
    display: block;
    margin-top: 6px;
}

@media (max-width: 480px) {
    .selector-segmentado-opciones { gap: 2px; }
    .selector-segmentado-opcion { padding-inline: 5px; font-size: .82rem; }
}
</style>
