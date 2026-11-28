<script setup>
import F from '../UI/FormField.vue';
import SelectorSegmentado from '../UI/SelectorSegmentado.vue';
import SelectorSiNo from '../UI/SelectorSiNo.vue';
import {computed, watch} from 'vue';
import {preguntasVisibles as resolverVisibilidad} from '../../support/preguntasVisibles';
import {esSelectorTipoCliente} from '../../support/visualizacionPregunta';

// Las preguntas usan las mismas entradas para el servicio principal y cada adicional.
const props = defineProps({questions: Array, errors: Object, prefix: String});
const answers = defineModel({type: Object, required: true});
const visibles = computed(() => resolverVisibilidad(props.questions ?? [], answers.value));
// Al ocultarse una pregunta, se borra su respuesta para evitar validaciones y cargos residuales.
watch(visibles, lista => {
    const ids = new Set(lista.map(pregunta => String(pregunta.id)));
    for (const id of Object.keys(answers.value)) if (!ids.has(String(id))) delete answers.value[id];
}, {immediate: true});
</script>

<template>
    <template v-for="question in visibles" :key="question.id">
        <SelectorSiNo v-if="question.is_yes_no" v-model="answers[question.id]" :question="question"
                      :required="Boolean(question.required ?? true)" :error="errors[prefix + question.id]"/>
        <template v-else>
            <fieldset v-if="question.input_type === 'multiple_choice'" class="request-question">
                <legend>{{ question.question }}{{ Boolean(question.required ?? true) ? ' *' : ' (opcional)' }}</legend>
                <label v-for="option in question.options" :key="option.id" class="select-row">
                    <input v-model="answers[question.id]" type="checkbox" :value="String(option.id)">{{ option.label }}
                </label>
                <small v-if="errors[prefix + question.id]" class="error" role="alert">{{
                        errors[prefix + question.id]
                    }}</small>
            </fieldset>
            <SelectorSegmentado v-else-if="esSelectorTipoCliente(question)"
                                v-model="answers[question.id]" :label="question.question"
                                :options="question.options" :error="errors[prefix + question.id]"
                                :required="Boolean(question.required ?? true)"/>
            <F v-else v-model="answers[question.id]" :label="question.question"
               :type="question.input_type === 'single_choice' ? 'select' : question.input_type === 'number' ? 'number' : 'textarea'"
               :options="question.options" :min="question.input_type === 'number' ? 1 : undefined"
               :max="question.input_type === 'number' ? 10000 : undefined"
               maxlength="5000" :error="errors[prefix + question.id]" :required="Boolean(question.required ?? true)"/>
        </template>
    </template>
</template>

<style scoped>
.request-question {
    min-width: 0;
    padding: 16px;
    border: 1px solid #1f6f7f33;
    border-radius: 10px;
    margin: 0 0 20px;
}
</style>
