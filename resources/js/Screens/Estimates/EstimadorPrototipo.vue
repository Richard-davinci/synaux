<script setup>
import {computed, ref} from 'vue';
import {router, useForm} from '@inertiajs/vue3';
import ServicePicker from '../../Components/Catalog/ServicePicker.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import SelectorSegmentado from '../../Components/UI/SelectorSegmentado.vue';
import T from '../../Components/UI/PageTitle.vue';
import ServiceReference from '../../Components/Estimates/ServiceReference.vue';
import EstimateResult from '../../Components/Estimates/EstimateResult.vue';
import ReiniciarEstimacion from '../../Components/Estimates/ReiniciarEstimacion.vue';
import {useSubmit} from '../../support/useSubmit';
import {claveTipoCliente, esSelectorTipoCliente, referenciaPorTipoCliente} from '../../support/visualizacionPregunta';

// El formulario conserva sus respuestas al recorrer los pasos; Laravel valida y calcula los importes.
const props = defineProps({data: {type: Object, required: true}});
const submit = useSubmit();
const step = ref(props.data.step ?? 1);
const service = ref(props.data.selected?.id ?? props.data.pending?.service?.id ?? 'sitio-institucional');
const selectedService = computed(() => props.data.services.find(item => item.id === service.value));
const initial = {
    ...Object.fromEntries((props.data.pending?.answers ?? []).map(answer => [answer.id, answer.value])),
    ...props.data.initialAnswers,
};
for (const question of props.data.questions ?? []) {
    if (question.input_type === 'multiple_choice' && !initial[question.id]) initial[question.id] = [];
}
const form = useForm({service: service.value, answers: initial});
const saveForm = useForm({to_request: false});
const labels = ['Servicio', 'Preguntas', 'Revisión', 'Resultado'];
// El precio orientativo sigue al segmento elegido, sin alterar los importes históricos del resultado.
const currentReference = computed(() => {
    const reference = props.data.references?.[service.value];
    if (!reference || props.data.selected?.id !== service.value) return reference;
    const clave = claveTipoCliente(props.data.questions ?? [], question => form.answers[question.id]);
    return referenciaPorTipoCliente(reference, clave);
});

// Los resúmenes toleran opciones históricas retiradas y respuestas todavía incompletas.
function answerLabel(question) {
    const value = form.answers[question.id];
    if (!question.input_type.includes('choice')) return value || 'Sin completar';
    return (Array.isArray(value) ? value : [value])
        .map(id => (question.options ?? []).find(option => String(option.id) === String(id))?.label)
        .filter(Boolean).join(', ') || 'Sin completar';
}

function choose(id) {
    service.value = id ?? null;
    form.service = id ?? '';
    form.clearErrors('service');
}

function next() {
    if (!selectedService.value) return;
    if (props.data.preview || props.data.selected?.id === service.value) {
        step.value = 2;
        return;
    }
    router.get('/estimador', {servicio: service.value, paso: 2});
}

function calculate() {
    submit(form, '/estimador/calcular', {
        previewTarget: 'estimador-4',
        // Una respuesta exitosa también abre el resultado cuando Inertia conserva la misma página.
        onSuccess: () => {
            step.value = 4;
        },
        onError: errors => {
            step.value = errors.service ? 1 : 2;
        },
    });
}

function save(toRequest) {
    saveForm.to_request = toRequest;
    submit(saveForm, 'guardar-estimacion', {previewTarget: toRequest ? 'solicitud-borrador' : 'estimaciones'});
}
</script>
<template>
    <section class="page shell estimator">
        <T eyebrow="Estimación orientativa" title="Estimá tu proyecto">
            <span>Paso {{ step }} de 4</span>
        </T>
        <ol class="estimator-progress" aria-label="Pasos de la estimación">
            <li v-for="(label, index) in labels" :key="label">
                <button type="button" :disabled="index + 1 > step"
                        :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">
                    <span class="step-number">{{ index + 1 }}</span>{{ label }}
                </button>
            </li>
        </ol>

        <div v-if="step < 4" class="estimator-workflow">
            <v-card v-if="step === 1" class="card form-card">
                <h2>¿Qué necesitás resolver?</h2>
                <p>Elegí el servicio para conocer su alcance y preparar la estimación.</p>
                <form class="estimator-service-form" @submit.prevent="next">
                    <ServicePicker :model-value="service" :services="data.services" label="Servicio" value-key="id"
                                   :error="form.errors.service" @update:model-value="choose"/>
                    <p v-if="selectedService" class="service-mode">{{ selectedService.mode }}</p>
                    <div class="form-actions"><span/><B type="submit" arrow :disabled="!selectedService">Continuar</B>
                    </div>
                </form>
            </v-card>

            <v-card v-else-if="step === 2" class="card form-card">
                <h2>Definí el alcance</h2>
                <p>Revisá las opciones de {{ selectedService?.name }}. La referencia se actualiza según tu elección.</p>
                <form @submit.prevent="step = 3">
                    <template v-for="question in data.questions" :key="question.id">
                        <fieldset v-if="question.input_type === 'multiple_choice'" class="question-group"
                                  :aria-invalid="!!form.errors['answers.' + question.id]">
                            <legend>{{ question.question }}</legend>
                            <label v-for="option in question.options" :key="option.id" class="select-row"><input
                                v-model="form.answers[question.id]" type="checkbox"
                                :value="String(option.id)">{{ option.label }}</label>
                            <small v-if="form.errors['answers.' + question.id]" class="error"
                                   role="alert">{{ form.errors['answers.' + question.id] }}</small>
                        </fieldset>
                        <SelectorSegmentado v-else-if="esSelectorTipoCliente(question)"
                                            v-model="form.answers[question.id]" :label="question.question"
                                            :options="question.options" :error="form.errors['answers.' + question.id]"/>
                        <F v-else v-model="form.answers[question.id]" :label="question.question"
                           :type="question.input_type === 'single_choice' ? 'select' : question.input_type === 'number' ? 'number' : 'textarea'"
                           :options="question.options?.map(option => ({ value: option.id, label: option.label }))"
                           required :min="question.input_type === 'number' ? 1 : undefined"
                           :max="question.input_type === 'number' ? 10000 : undefined"
                           :maxlength="question.input_type === 'text' ? 5000 : undefined"
                           :error="form.errors['answers.' + question.id]"/>
                    </template>
                    <div class="form-actions"><B variant="secondary" @click="step = 1">Anterior</B><B type="submit"
                                                                                                      arrow>Revisar
                        respuestas</B></div>
                </form>
            </v-card>

            <v-card v-else class="card form-card">
                <h2>Revisá tu estimación</h2>
                <div class="estimator-review">
                    <div><span>Servicio</span><strong>{{ selectedService?.name }}</strong><B variant="ghost"
                                                                                             @click="step = 1">Cambiar
                        servicio</B></div>
                    <div v-for="question in data.questions" :key="question.id"><span>{{
                            question.question
                        }}</span><strong>{{ answerLabel(question) }}</strong><B variant="ghost"
                                                                                :aria-label="'Editar respuesta: ' + question.question"
                                                                                @click="step = 2">Editar</B></div>
                </div>
                <p class="estimate-note">Al confirmar, synaux calcula la referencia y conserva las respuestas para tu
                    solicitud de presupuesto.</p>
                <div class="form-actions"><B variant="secondary" @click="step = 2">Anterior</B><B
                    :disabled="form.processing" arrow
                    @click="calculate">{{ form.processing ? 'Calculando…' : 'Calcular estimación' }}</B></div>
            </v-card>

            <!-- La vista de referencia permite reiniciar también antes de calcular. -->
            <div v-if="step > 1 || form.isDirty" class="estimator-restart">
                <ReiniciarEstimacion :deshabilitado="form.processing"/>
            </div>

            <aside class="estimator-reference">
                <ServiceReference :service="selectedService" :reference="currentReference"/>
                <v-card v-if="!selectedService" class="card reference-empty"><h3>Referencia del servicio</h3>
                    <p>Elegí un servicio para ver su alcance y la referencia de precio disponible.</p></v-card>
                <p class="estimate-note">Podés revisar los datos antes de enviar una solicitud a synaux.</p>
            </aside>
        </div>

        <EstimateResult v-else-if="data.pending" :result="data.pending" :preview="Boolean(data.preview)"
                        show-deliverables>
            <template v-if="$page.props.auth || data.preview">
                <B :disabled="saveForm.processing" arrow @click="save(true)">Continuar con la solicitud</B>
                <B variant="secondary" :disabled="saveForm.processing" @click="save(false)">Guardar estimación</B>
            </template>
            <B v-else href="/ingresar" arrow>Ingresar para solicitar presupuesto</B>
            <B variant="secondary" @click="step = 2">Editar respuestas</B>
            <ReiniciarEstimacion :deshabilitado="saveForm.processing"/>
        </EstimateResult>
        <v-card v-else class="card"><h2>Comenzá por elegir un servicio</h2>
            <p>Necesitamos tus respuestas para preparar la referencia.</p><B href="/estimador">Volver al estimador</B>
        </v-card>
    </section>
</template>
<style scoped>
/* Los pasos mantienen etiquetas visibles y controles cómodos en escritorio y móvil. */
.estimator {
    max-width: 1180px;
}

.estimator h2, .estimator h3 {
    font-weight: 700;
}

.estimator-progress {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
    list-style: none;
    padding: 0;
    margin: 0 0 1.75rem;
}

.estimator-progress button {
    display: flex;
    align-items: center;
    gap: .6rem;
    min-height: 48px;
    width: 100%;
    padding: .65rem;
    border: 1px solid rgb(31 111 127 / 25%);
    border-radius: .75rem;
    background: #fff;
    color: #153b64;
    font: inherit;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
}

.estimator-progress button[aria-current='step'] {
    background: #153b64;
    color: #fff;
}

.estimator-progress button:disabled {
    color: #446075;
    cursor: default;
}

.step-number {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1px solid currentColor;
}

.estimator-workflow {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
    gap: 1.5rem;
    align-items: start;
}

/* La acción secundaria queda debajo del formulario de referencia. */
.estimator-restart {
    grid-row: 2;
    justify-self: start;
}

.estimator-reference {
    grid-column: 2;
    grid-row: 1;
    position: sticky;
    top: 1.5rem;
}

.estimator-reference :deep(.service-reference) {
    margin-top: 0;
}

/* Los controles ocupan una altura estable y la lista de opciones se desplaza dentro del menú. */
.estimator-service-form {
    margin-top: 24px;
}

.service-mode {
    display: inline-block;
    padding: 6px 10px;
    margin: 12px 0;
    border-radius: 8px;
    color: #1f6f7f;
    background: #f5f9fa;
    font-size: .9rem;
    font-weight: 700;
}

.reference-empty p {
    margin-bottom: 0;
}

.estimator button:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

.estimator-review > div {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .5rem 1rem;
    padding-block: 1rem;
    border-bottom: 1px solid rgb(31 111 127 / 18%);
}

.estimator-review span {
    color: #446075;
}

.estimator-review strong {
    grid-column: 1;
    overflow-wrap: anywhere;
}

.estimator-review .btn {
    grid-column: 2;
    grid-row: 1 / 3;
    align-self: center;
}

.estimate-note {
    color: #446075;
    margin-top: 1.25rem;
    line-height: 1.6;
}

@media (max-width: 850px) {
    .estimator-workflow {
        grid-template-columns: minmax(0, 1fr);
    }

    .estimator-reference {
        grid-column: 1;
        grid-row: auto;
        position: static;
    }
}

@media (max-width: 600px) {
    .estimator-progress {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .form-actions {
        align-items: stretch;
    }

    .form-actions .btn {
        flex: 1;
    }
}
</style>
