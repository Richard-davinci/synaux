<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import T from '../Components/SynauxTitle.vue';
import I from '../Components/SynauxIcon.vue';
import { money, date, useSubmit } from '../support/synaux';
// El navegador organiza los pasos; Laravel valida respuestas y calcula con la versión de tarifas vigente.
const props = defineProps({ data: Object });
const submit = useSubmit();
const step = ref(props.data.step ?? 1);
const service = ref(props.data.selected?.id ?? props.data.pending?.service?.id ?? 'sitio-institucional');
const saved = computed(() => props.data.screen === 'estimacion');
const result = computed(() => saved.value ? { ...props.data.selected, service: props.data.services.find(s => s.database_id === props.data.selected.service_id), min: props.data.selected.price_min, max: props.data.selected.price_max, mode: props.data.selected.status === 'manual' ? 'manual' : 'automatic', currency: props.data.selected.currency_snapshot, date: props.data.selected.created_at } : props.data.pending);
const initial = Object.fromEntries((props.data.pending?.answers ?? []).map(a => [a.id, a.value]));
for (const q of props.data.questions ?? []) if (q.input_type === 'multiple_choice' && !initial[q.id]) initial[q.id] = [];
const form = useForm({ service: service.value, answers: initial });
const saveForm = useForm({ to_request: false });
const labels = ['Servicio', 'Preguntas', 'Revisión', 'Resultado'];
function choose(id) { service.value = id; form.service = id; }
function next() {
    if (props.data.preview || props.data.selected?.id === service.value) { step.value = 2; return; }
    router.get('/estimador', { servicio: service.value, paso: 2 });
}
function calculate() { submit(form, '/estimador/calcular', {previewTarget:'estimador-4'}); }
function save(toRequest) { saveForm.to_request = toRequest; submit(saveForm, 'guardar-estimacion', {previewTarget:toRequest ? 'solicitud-borrador' : 'estimaciones'}); }
</script>
<template>
    <section class="page shell estimator">
        <T eyebrow="Presupuestador orientativo" :title="saved ? 'Tu estimación guardada' : 'Estimá tu proyecto'"><span v-if="!saved">Paso {{ step }} de 4</span></T>
        <!-- La secuencia refleja el paso actual y permite volver sin perder las respuestas ingresadas. -->
        <ol v-if="!saved" class="progress" aria-label="Pasos de la estimación"><li v-for="(label, index) in labels" :key="label" :class="{done:step >= index + 1}"><i>{{ step > index + 1 ? '✓' : index + 1 }}</i><span>{{ label }}</span></li></ol>
        <v-card v-if="step === 1 && !saved" class="card form-card"><h2>¿Qué necesitás resolver?</h2><p>Seleccioná un servicio. Podés cambiarlo antes de finalizar.</p><div class="choice-grid"><button v-for="s in data.services" :key="s.id" :class="['choice',{selected:service === s.id}]" :aria-pressed="service === s.id" @click="choose(s.id)"><span>{{ s.category }}</span><strong>{{ s.name }}</strong><small>{{ s.mode }}</small></button></div><div class="form-actions"><span /><B arrow @click="next">Continuar</B></div></v-card>
        <v-card v-else-if="step === 2 && !saved" class="card form-card"><h2>Contanos un poco más</h2><p>Tus respuestas definen componentes y cantidades del cálculo.</p>
            <form @submit.prevent="step = 3"><template v-for="q in data.questions" :key="q.id"><fieldset v-if="q.input_type === 'multiple_choice'" class="question-group"><legend>{{ q.question }}</legend><label v-for="option in q.options" :key="option.id" class="select-row"><input v-model="form.answers[q.id]" type="checkbox" :value="String(option.id)">{{ option.label }}</label></fieldset><F v-else v-model="form.answers[q.id]" :label="q.question" :type="q.input_type === 'single_choice' ? 'select' : q.input_type === 'number' ? 'number' : 'textarea'" :options="q.options?.map(o => ({value:o.id,label:o.label}))" required :min="q.input_type === 'number' ? 1 : undefined" :error="form.errors[`answers.${q.id}`]" /></template><div class="form-actions"><B variant="ghost" @click="step = 1">Volver</B><B type="submit" arrow>Revisar respuestas</B></div></form>
        </v-card>
        <v-card v-else-if="step === 3 && !saved" class="card form-card"><h2>Revisá antes de calcular</h2><div class="review-list"><div><span>Servicio</span><strong>{{ data.services.find(s => s.id === service)?.name }}</strong><button @click="step = 1">Editar</button></div><div v-for="q in data.questions" :key="q.id"><span>{{ q.question }}</span><strong>{{ q.input_type.includes('choice') ? (Array.isArray(form.answers[q.id]) ? form.answers[q.id] : [form.answers[q.id]]).map(v => q.options.find(o => String(o.id) === String(v))?.label).join(', ') : form.answers[q.id] }}</strong><button @click="step = 2">Editar</button></div></div><div class="notice"><I name="info" /><p>El resultado será orientativo. El presupuesto formal se prepara después de revisar tu solicitud.</p></div><div class="form-actions"><B variant="ghost" @click="step = 2">Volver</B><B :disabled="form.processing" @click="calculate">Calcular estimación</B></div></v-card>
        <!-- El resultado es una instantánea del cálculo; recalcular crea una referencia independiente. -->
        <div v-else-if="result" class="result-grid"><v-card class="card result-card"><p class="eyebrow">{{ result.mode === 'manual' ? 'Revisión profesional' : 'Estimación orientativa' }}</p><h2>{{ result.mode === 'manual' ? 'Una propuesta a la medida de tu proyecto' : `${money(result.min,result.currency)} – ${money(result.max,result.currency)}` }}</h2><p>{{ result.mode === 'manual' ? 'Este alcance necesita una revisión profesional antes de definir importes y plazos.' : `Referencia calculada el ${date(result.date)}. No es un presupuesto formal ni implica reserva de precio.` }}</p><div v-if="result.mode !== 'manual'" class="estimate-lines"><div v-for="item in result.items" :key="item.name"><span>{{ item.name }} · {{ item.quantity }}</span><strong>{{ money(Number(item.min)*Number(item.quantity),result.currency) }} – {{ money(Number(item.max)*Number(item.quantity),result.currency) }}</strong></div></div><div class="button-stack"><template v-if="$page.props.auth || data.preview"><B v-if="saved" :href="`/solicitudes/nueva?estimacion=${data.selected.id}`" arrow>Crear solicitud</B><B v-else :disabled="saveForm.processing" arrow @click="save(true)">Crear solicitud</B><B v-if="!saved" variant="secondary" :disabled="saveForm.processing" @click="save(false)">Guardar estimación</B></template><B v-else href="/ingresar" arrow>Ingresar para crear la solicitud</B><B :href="`/estimador?servicio=${result.service?.id ?? service}`" variant="secondary">Recalcular como nueva estimación</B></div></v-card><aside><v-card class="card"><h3>Alcance considerado</h3><dl class="data-list"><div v-for="answer in result.answers" :key="answer.id"><dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd></div></dl></v-card><v-card class="card soft-panel"><I name="info" /><p>Para convertir esta referencia en una propuesta, creá una solicitud. Si no tenés cuenta, tus respuestas se conservarán durante el registro.</p></v-card></aside></div>
        <v-card v-else class="card"><h2>Comenzá por elegir un servicio</h2><p>Necesitamos tus respuestas para preparar la referencia.</p><B href="/estimador">Volver al estimador</B></v-card>
    </section>
</template>
