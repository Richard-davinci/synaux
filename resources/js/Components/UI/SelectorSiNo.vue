<script setup>
import {computed} from 'vue';

// Conserva los IDs de opciones de la pregunta aunque las dos alternativas se presenten como selector binario.
const props = defineProps({question: {type: Object, required: true}, required: Boolean, error: String});
const value = defineModel();
const choices = computed(() => [...(props.question.options ?? [])].sort((a, b) => Number(a.sort_order) - Number(b.sort_order)));
</script>

<template>
    <fieldset class="selector-si-no" :aria-describedby="error ? `error-si-no-${question.id}` : undefined">
        <legend>{{ question.question }}{{ required ? ' *' : ' (opcional)' }}</legend>
        <div class="selector-si-no-opciones">
            <label v-for="option in choices" :key="option.id" :class="{seleccionada: String(value) === String(option.id)}">
                <input v-model="value" type="radio" :name="`pregunta-${question.id}`" :value="String(option.id)" :required="required">
                <span>{{ option.label }}</span>
            </label>
        </div>
        <small v-if="error" :id="`error-si-no-${question.id}`" class="error" role="alert">{{ error }}</small>
    </fieldset>
</template>

<style scoped>
/* El selector conserva controles accesibles y resalta la elección con la paleta Synaux. */
.selector-si-no { min-width:0; margin:0 0 20px; padding:0; border:0; }
.selector-si-no legend { margin-bottom:8px; color:#153b64; font-weight:600; }
.selector-si-no-opciones { display:flex; flex-wrap:wrap; gap:8px; }
.selector-si-no-opciones label { display:flex; align-items:center; gap:8px; min-height:42px; padding:8px 14px; border:1px solid #c5d6df; border-radius:8px; color:#153b64; cursor:pointer; }
.selector-si-no-opciones label.seleccionada { border-color:#1f6f7f; background:#eaf7f6; }
.selector-si-no-opciones input { accent-color:#1f6f7f; }
.error { display:block; margin-top:5px; color:#9b3039; }
</style>
