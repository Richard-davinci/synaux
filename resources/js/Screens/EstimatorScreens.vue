<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import T from '../Components/SynauxTitle.vue';
import ServiceReference from '../Components/ServiceReference.vue';
import { money, date, useSubmit } from '../support/synaux';

// El formulario conserva sus respuestas al recorrer los pasos; Laravel valida y calcula los importes.
const props = defineProps({ data: Object });
const submit = useSubmit();
const step = ref(props.data.step ?? 1);
const service = ref(props.data.selected?.id ?? props.data.pending?.service?.id ?? 'sitio-institucional');
const saved = computed(() => props.data.screen === 'estimacion');
const selectedService = computed(() => props.data.services.find(item => item.id === service.value));
const initial = {
    ...Object.fromEntries((props.data.pending?.answers ?? []).map(answer => [answer.id, answer.value])),
    ...props.data.initialAnswers,
};
for (const question of props.data.questions ?? []) {
    if (question.input_type === 'multiple_choice' && !initial[question.id]) initial[question.id] = [];
}
const form = useForm({ service: service.value, answers: initial });
const saveForm = useForm({ to_request: false });
const labels = ['Servicio', 'Preguntas', 'Revisión', 'Resultado'];
const serviceGroups = computed(() => {
    const groups = new Map();
    for (const item of props.data.services) {
        if (!groups.has(item.category)) groups.set(item.category, []);
        groups.get(item.category).push(item);
    }
    return groups;
});

// El precio orientativo sigue al segmento elegido, sin alterar los importes históricos del resultado.
const currentReference = computed(() => {
    const reference = props.data.references?.[service.value];
    if (!reference || props.data.selected?.id !== service.value) return reference;
    const selectedLabels = (props.data.questions ?? []).flatMap(question =>
        (question.options ?? []).filter(option => String(option.id) === String(form.answers[question.id])).map(option => option.label)
    );
    const segment = reference.segments?.find(item => selectedLabels.includes(item.label));
    return segment ? { ...reference, min: segment.min, max: segment.max, segment: segment.label } : reference;
});
const result = computed(() => saved.value ? {
    ...props.data.selected,
    service: props.data.services.find(item => item.database_id === props.data.selected.service_id),
    min: props.data.selected.price_min, max: props.data.selected.price_max,
    mode: props.data.selected.status === 'manual' ? 'manual' : 'automatic',
    currency: props.data.selected.currency_snapshot,
    base_min: props.data.selected.base_min_snapshot, base_max: props.data.selected.base_max_snapshot,
    date: props.data.selected.created_at,
} : props.data.pending);

// Los resúmenes toleran opciones históricas retiradas y respuestas todavía incompletas.
function answerLabel(question) {
    const value = form.answers[question.id];
    if (!question.input_type.includes('choice')) return value || 'Sin completar';
    return (Array.isArray(value) ? value : [value])
        .map(id => (question.options ?? []).find(option => String(option.id) === String(id))?.label)
        .filter(Boolean).join(', ') || 'Sin completar';
}
function priceRange(min, max, currency) {
    return Number(min) === Number(max) ? money(min, currency) : money(min, currency) + ' – ' + money(max, currency);
}
function choose(id) {
    service.value = id;
    form.service = id;
}
function next() {
    if (props.data.preview || props.data.selected?.id === service.value) {
        step.value = 2;
        return;
    }
    router.get('/estimador', { servicio: service.value, paso: 2 });
}
function calculate() {
    submit(form, '/estimador/calcular', {
        previewTarget: 'estimador-4',
        // Una respuesta exitosa también abre el resultado cuando Inertia conserva la misma página.
        onSuccess: () => { step.value = 4; },
        onError: errors => { step.value = errors.service ? 1 : 2; },
    });
}
function save(toRequest) {
    saveForm.to_request = toRequest;
    submit(saveForm, 'guardar-estimacion', { previewTarget: toRequest ? 'solicitud-borrador' : 'estimaciones' });
}
</script>
<template>
    <section class="page shell estimator">
        <T eyebrow="Estimación orientativa" :title="saved ? 'Tu estimación guardada' : 'Estimá tu proyecto'">
            <span v-if="!saved">Paso {{ step }} de 4</span>
        </T>
        <ol v-if="!saved" class="estimator-progress" aria-label="Pasos de la estimación">
            <li v-for="(label, index) in labels" :key="label">
                <button type="button" :disabled="index + 1 > step" :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">
                    <span class="step-number">{{ index + 1 }}</span>{{ label }}
                </button>
            </li>
        </ol>

        <div v-if="step < 4 && !saved" class="estimator-workflow">
            <v-card v-if="step === 1" class="card form-card">
                <h2>¿Qué necesitás resolver?</h2><p>Elegí el servicio para conocer su alcance y preparar la estimación.</p>
                <p v-if="form.errors.service" class="error" role="alert">{{ form.errors.service }}</p>
                <section v-for="[category, items] in serviceGroups" :key="category" class="estimator-service-group">
                    <h3>{{ category }}</h3>
                    <div class="choice-grid">
                        <button v-for="item in items" :key="item.id" type="button" :class="['choice', { selected: service === item.id }]" :aria-pressed="service === item.id" @click="choose(item.id)">
                            <strong>{{ item.name }}</strong><small>{{ item.mode }}</small>
                        </button>
                    </div>
                </section>
                <div class="form-actions"><span /><B arrow @click="next">Continuar</B></div>
            </v-card>

            <v-card v-else-if="step === 2" class="card form-card">
                <h2>Definí el alcance</h2><p>Revisá las opciones de {{ selectedService?.name }}. La referencia se actualiza según tu elección.</p>
                <form @submit.prevent="step = 3">
                    <template v-for="question in data.questions" :key="question.id">
                        <fieldset v-if="question.input_type === 'multiple_choice'" class="question-group" :aria-invalid="!!form.errors['answers.' + question.id]">
                            <legend>{{ question.question }}</legend>
                            <label v-for="option in question.options" :key="option.id" class="select-row"><input v-model="form.answers[question.id]" type="checkbox" :value="String(option.id)">{{ option.label }}</label>
                            <small v-if="form.errors['answers.' + question.id]" class="error" role="alert">{{ form.errors['answers.' + question.id] }}</small>
                        </fieldset>
                        <F v-else v-model="form.answers[question.id]" :label="question.question" :type="question.input_type === 'single_choice' ? 'select' : question.input_type === 'number' ? 'number' : 'textarea'" :options="question.options?.map(option => ({ value: option.id, label: option.label }))" required :min="question.input_type === 'number' ? 1 : undefined" :max="question.input_type === 'number' ? 10000 : undefined" :maxlength="question.input_type === 'text' ? 5000 : undefined" :error="form.errors['answers.' + question.id]" />
                    </template>
                    <div class="form-actions"><B variant="secondary" @click="step = 1">Anterior</B><B type="submit" arrow>Revisar respuestas</B></div>
                </form>
            </v-card>

            <v-card v-else class="card form-card">
                <h2>Revisá tu estimación</h2>
                <div class="estimator-review">
                    <div><span>Servicio</span><strong>{{ selectedService?.name }}</strong><B variant="ghost" @click="step = 1">Cambiar servicio</B></div>
                    <div v-for="question in data.questions" :key="question.id"><span>{{ question.question }}</span><strong>{{ answerLabel(question) }}</strong><B variant="ghost" :aria-label="'Editar respuesta: ' + question.question" @click="step = 2">Editar</B></div>
                </div>
                <p class="estimate-note">Al confirmar, Synaux calcula la referencia y conserva las respuestas para tu solicitud de presupuesto.</p>
                <div class="form-actions"><B variant="secondary" @click="step = 2">Anterior</B><B :disabled="form.processing" arrow @click="calculate">{{ form.processing ? 'Calculando…' : 'Calcular estimación' }}</B></div>
            </v-card>

            <aside class="estimator-reference">
                <ServiceReference :service="selectedService" :reference="currentReference" />
                <p class="estimate-note">Podés revisar los datos antes de enviar una solicitud a Synaux.</p>
            </aside>
        </div>

        <!-- El resultado guardado siempre muestra los valores de su versión original. -->
        <div v-else-if="result" class="result-grid">
            <v-card class="card result-card">
                <p class="eyebrow">{{ result.mode === 'manual' ? 'Revisión de alcance' : 'Tu estimación orientativa' }}</p>
                <h2>{{ result.mode === 'manual' ? 'Preparemos una propuesta para tu proyecto' : priceRange(result.min, result.max, result.currency) }}</h2>
                <p>{{ result.mode === 'manual' ? 'Synaux necesita revisar el alcance para definir el importe y los plazos. Podés continuar con la solicitud.' : 'Calculada el ' + date(result.date) + '. El precio y las condiciones finales se confirman en el presupuesto formal.' }}</p>
                <div v-if="result.mode !== 'manual'" class="estimate-lines">
                    <div><span>Base del servicio</span><strong>{{ priceRange(result.base_min,result.base_max,result.currency) }}</strong></div>
                    <div v-for="item in result.items" :key="item.name"><span>{{ item.name }}<template v-if="item.unit !== 'segmento'"> · {{ item.quantity }}</template></span><strong>{{ priceRange(Number(item.min)*Number(item.quantity),Number(item.max)*Number(item.quantity),result.currency) }}</strong></div>
                </div>
                <div class="button-stack">
                    <template v-if="$page.props.auth || data.preview">
                        <B v-if="saved" :href="'/solicitudes/nueva?estimacion=' + data.selected.id" arrow>Continuar con la solicitud</B>
                        <B v-else :disabled="saveForm.processing" arrow @click="save(true)">Continuar con la solicitud</B>
                        <B v-if="!saved" variant="secondary" :disabled="saveForm.processing" @click="save(false)">Guardar estimación</B>
                    </template>
                    <B v-else href="/ingresar" arrow>Ingresar para solicitar presupuesto</B>
                    <B v-if="!saved" variant="secondary" @click="step = 2">Editar respuestas</B>
                    <B v-else :href="'/estimador?servicio=' + (result.service?.id ?? service)" variant="secondary">Nueva estimación</B>
                </div>
            </v-card>
            <aside class="result-aside">
                <v-card class="card"><h3>Alcance considerado</h3><p>{{ result.service?.name ?? data.selected?.summary }}</p>
                    <ul v-if="!saved"><li v-for="deliverable in result.service?.includes ?? []" :key="deliverable">{{ deliverable }}</li></ul>
                    <dl class="data-list"><div v-for="answer in result.answers" :key="answer.id"><dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd></div></dl>
                </v-card>
                <v-card class="card soft-panel"><h3>El próximo paso</h3><p>Completá el pedido y tus datos de contacto. Synaux revisará el alcance y te enviará un presupuesto formal.</p><p v-if="!$page.props.auth && !data.preview">Tus respuestas se conservan al ingresar o crear tu cuenta.</p></v-card>
            </aside>
        </div>
        <v-card v-else class="card"><h2>Comenzá por elegir un servicio</h2><p>Necesitamos tus respuestas para preparar la referencia.</p><B href="/estimador">Volver al estimador</B></v-card>
    </section>
</template>
<style scoped>
/* Los pasos mantienen etiquetas visibles y controles cómodos en escritorio y móvil. */
.estimator { max-width: 1180px; }
.estimator h2, .estimator h3 { font-weight: 700; }
.estimator-progress { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .75rem; list-style: none; padding: 0; margin: 0 0 1.75rem; }
.estimator-progress button { display: flex; align-items: center; gap: .6rem; min-height: 48px; width: 100%; padding: .65rem; border: 1px solid rgb(31 111 127 / 25%); border-radius: .75rem; background: #fff; color: #153b64; font: inherit; font-weight: 600; text-align: left; cursor: pointer; }
.estimator-progress button[aria-current='step'] { background: #153b64; color: #fff; }
.estimator-progress button:disabled { color: #446075; cursor: default; }
.step-number { display: grid; place-items: center; flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; border: 1px solid currentColor; }
.estimator-workflow { display: grid; grid-template-columns: minmax(0,1.3fr) minmax(0,1fr); gap: 1.5rem; align-items: start; }
.estimator-reference { position: sticky; top: 1.5rem; }
.estimator-reference :deep(.service-reference) { margin-top: 0; }
.estimator-service-group + .estimator-service-group { margin-top: 1.5rem; }
.estimator-service-group h3 { margin-bottom: .75rem; font-size: 1.1rem; }
.estimator-service-group .choice-grid { grid-template-columns: repeat(auto-fit,minmax(min(100%,12rem),1fr)); }
.estimator-service-group .choice { min-height: 88px; }
.estimator .choice.selected { border-color: #1f6f7f; background: #f5f9fa; }
.estimator button:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 3px; }
.estimator-review > div { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .5rem 1rem; padding-block: 1rem; border-bottom: 1px solid rgb(31 111 127 / 18%); }
.estimator-review span { color: #446075; }
.estimator-review strong { grid-column: 1; overflow-wrap: anywhere; }
.estimator-review .btn { grid-column: 2; grid-row: 1 / 3; align-self: center; }
.estimate-note { color: #446075; margin-top: 1.25rem; line-height: 1.6; }
.result-aside ul { padding-left: 1.25rem; margin-bottom: 1rem; }
.result-aside p:last-child { margin-bottom: 0; }
.estimator .result-card h2 { overflow-wrap: anywhere; }
@media (max-width: 850px) { .estimator-workflow { grid-template-columns: minmax(0,1fr); } .estimator-reference { position: static; } }
@media (max-width: 600px) { .estimator-progress { grid-template-columns: repeat(2,minmax(0,1fr)); } .form-actions { align-items: stretch; } .form-actions .btn { flex: 1; } }
</style>
