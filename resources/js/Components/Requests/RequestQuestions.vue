<script setup>
import F from '../UI/FormField.vue';

// Las preguntas usan las mismas entradas para el servicio principal y cada adicional.
defineProps({ questions: Array, errors: Object, prefix: String });
const answers = defineModel({ type: Object, required: true });
</script>

<template>
    <template v-for="question in questions" :key="question.id">
        <fieldset v-if="question.input_type === 'multiple_choice'" class="request-question">
            <legend>{{ question.question }}{{ Boolean(question.required ?? true) ? ' *' : ' (opcional)' }}</legend>
            <label v-for="option in question.options" :key="option.id" class="select-row">
                <input v-model="answers[question.id]" type="checkbox" :value="String(option.id)">{{ option.label }}
            </label>
            <small v-if="errors[prefix + question.id]" class="error" role="alert">{{ errors[prefix + question.id] }}</small>
        </fieldset>
        <F v-else v-model="answers[question.id]" :label="question.question"
            :type="question.input_type === 'single_choice' ? 'select' : question.input_type === 'number' ? 'number' : 'textarea'"
            :options="question.options" :min="question.input_type === 'number' ? 1 : undefined" :max="question.input_type === 'number' ? 10000 : undefined"
            maxlength="5000" :error="errors[prefix + question.id]" :required="Boolean(question.required ?? true)" />
    </template>
</template>

<style scoped>
.request-question { min-width: 0; padding: 16px; border: 1px solid #1f6f7f33; border-radius: 10px; margin: 0 0 20px; }
</style>
