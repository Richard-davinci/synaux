<script setup>
// Las imágenes nuevas y guardadas comparten el grupo de radio y el indicador de portada.
const choice = defineModel({type: String});
defineProps({
    value: {type: String, required: true},
    label: {type: String, default: 'Usar como portada'},
    accessibleLabel: String,
});
</script>

<template>
    <label class="cover-option" :data-selected="choice === value || undefined">
        <input v-model="choice" type="radio" name="portfolio-cover" :value="value"
               :aria-label="accessibleLabel">
        {{ label }}
    </label>
</template>

<style scoped>
/* Un único control de radio identifica la portada entre las imágenes disponibles. */
.cover-option {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    max-width: 100%;
    min-height: 44px;
    padding: 6px 10px;
    cursor: pointer;
    color: #153b64;
    border: 1px solid transparent;
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 700;
    line-height: 1.3;
}

.cover-option:has(input:checked) {
    background: #2db3a31a;
    border-color: #1f6f7f40;
}

.cover-option input[type="radio"] {
    appearance: auto;
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
    min-height: 0;
    margin: 0;
    padding: 0;
    accent-color: #1f6f7f;
    cursor: pointer;
}
</style>
