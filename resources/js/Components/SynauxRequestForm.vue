<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import B from './SynauxButton.vue';
import F from './SynauxField.vue';
import I from './SynauxIcon.vue';
import ServicePicker from './SynauxServicePicker.vue';
import { money, date, useSubmit } from '../support/synaux';

// Los borradores existentes se recuperan con sus datos y las solicitudes nuevas parten de la cuenta actual.
const props = defineProps({ data: Object });
const page = usePage();
const old = props.data.selected?.status === 'draft' ? props.data.selected : null;
const estimate = props.data.selectedEstimate ?? props.data.estimates?.find(item => item.id === old?.estimate_id);
const pending = props.data.requestDraft ?? {};
const step = ref(props.data.requestStep ?? (props.data.referenceKey === 'publicar-revision' ? 4 : 1));
const authenticated = computed(() => !!(page.props.auth || props.data.preview));
const handoff = useForm({});
const labels = ['Servicio', 'Tu proyecto', 'Contacto', 'Revisión'];
const submit = useSubmit();
// Un borrador conserva su instantánea, incluso si luego cambia el contacto de la cuenta.
const contact = old?.contact ?? props.data.accountContact ?? page.props.auth ?? {};
const form = useForm({
    id: old?.id ?? null,
    estimate_id: estimate?.id ?? old?.estimate_id ?? null,
    service_id: estimate?.service_id ?? old?.service_id ?? pending.service_id ?? props.data.requestServiceId ?? props.data.services[0]?.database_id,
    title: old?.title ?? pending.title ?? '',
    description: old?.description ?? pending.description ?? props.data.requestInitialDescription ?? '',
    category: old?.category ?? '',
    skills: old?.skills?.join(', ') ?? pending.skills ?? '',
    visibility: page.props.stageTwo ? (old?.visibility ?? 'private') : 'private',
    budget_reference: old?.budget_reference ?? pending.budget_reference ?? '',
    desired_deadline: old?.desired_deadline ?? pending.desired_deadline ?? '',
    contact: {
        name: contact.name ?? '',
        email: contact.email ?? '',
        company: contact.company ?? '',
        phone: contact.phone ?? '',
        address: contact.address ?? '',
    },
    additional_services: (old?.additional_services ?? pending.additional_services ?? []).map(item => ({
        service_id: item.service_id, quantity: item.quantity, notes: item.notes ?? '',
    })),
    answers: { ...(pending.answers ?? props.data.requestInitialAnswers ?? {}) },
    publish: false,
});
const selectedService = computed(() => props.data.services.find(item => item.database_id == form.service_id));
const questions = computed(() => (props.data.requestQuestions?.[form.service_id] ?? []).filter(question => !question.from_project_description));
const initializeAnswers = () => {
    for (const question of questions.value) {
        if (question.input_type === 'multiple_choice' && !Array.isArray(form.answers[question.id])) form.answers[question.id] = [];
    }
};
initializeAnswers();
const extras = computed(() => props.data.services.filter(item => item.database_id != form.service_id));
// Las tarifas nuevas no modifican el importe histórico de la estimación elegida.
const estimateAnswers = computed(() => estimate?.answers ?? old?.answers ?? []);
const reference = computed(() => estimate
    ? (estimate.price_min == null ? null : {
        min: estimate.price_min, max: estimate.price_max ?? estimate.price_min,
        currency: estimate.currency_snapshot, date: estimate.created_at,
    })
    : (() => {
        const published = props.data.references?.[selectedService.value?.id];
        const calculation = props.data.requestCalculation;
        if (calculation?.service?.database_id == form.service_id && calculation.answers.every(answer => {
            const question = (props.data.requestQuestions?.[form.service_id] ?? []).find(item => item.id == answer.id);
            return question?.from_project_description ? form.description === answer.value : JSON.stringify(form.answers[answer.id]) === JSON.stringify(answer.value);
        })) return calculation.min == null ? null : {min:calculation.min,max:calculation.max,currency:calculation.currency,date:calculation.date};
        const labels = questions.value.flatMap(question => (question.options ?? []).filter(option => String(option.id) === String(form.answers[question.id])).map(option => option.label));
        const segment = published?.segments?.find(item => labels.includes(item.label));
        return segment ? {...published,min:segment.min,max:segment.max,segment:segment.label} : published;
    })());
const canSaveDraft = computed(() => form.title.trim().length >= 5 && form.description.trim().length >= 10);


// La categoría se deriva del servicio y un mismo servicio no se repite como adicional.
watch(selectedService, (service, previous) => {
    if (previous && service?.database_id !== previous.database_id) form.answers = {};
    initializeAnswers();
    if (service) form.category = service.category;
    form.additional_services = form.additional_services.filter(item => item.service_id != form.service_id);
}, { immediate: true });
function extraName(id) {
    return props.data.services.find(item => item.database_id == id)?.name ?? 'Elegí un servicio';
}
function extraOptions(index) {
    const used = form.additional_services.filter((_, position) => position !== index).map(item => Number(item.service_id));
    return extras.value.filter(item => !used.includes(item.database_id)).map(item => ({ value: item.database_id, label: item.name }));
}
function addService() {
    const used = form.additional_services.map(item => Number(item.service_id));
    const next = extras.value.find(item => !used.includes(item.database_id));
    if (next) form.additional_services.push({ service_id: next.database_id, quantity: 1, notes: '' });
}

// El visitante prepara el proyecto antes de ingresar; no se envía una solicitud hasta su confirmación autenticada.
function advance() {
    if (step.value === 1 && !selectedService.value) return;
    if (step.value === 3 && !authenticated.value) return;
    // Al completar el proyecto se obtiene el cálculo real y se conserva el avance antes del contacto.
    if (step.value === 2 && !estimate && !old && !props.data.preview) { prepareJourney('review'); return; }
    step.value += 1;
}
function prepareJourney(destination) {
    handoff.transform(() => ({...form.data(), destination}));
    handoff.post('/solicitudes/preparar', {
        preserveScroll: true,
        onError: errors => {
            form.setError(errors);
            step.value = Object.keys(errors).some(field => field === 'service_id' || field.startsWith('answers.') || field.startsWith('additional_services')) ? 1 : 2;
        },
    });
}

// Un error vuelve al paso correspondiente y conserva los datos que el cliente escribió.
function save(publish) {
    if (!authenticated.value) { step.value = 3; return; }
    form.publish = publish;
    submit(form, 'solicitud', {
        previewTarget: publish ? 'solicitud' : 'solicitud-borrador',
        onError: (errors) => {
            const fields = Object.keys(errors);
            step.value = fields.some(field => field.startsWith('contact')) ? 3
                : fields.some(field => field === 'service_id' || field === 'estimate_id' || field.startsWith('answers.') || field.startsWith('additional_services')) ? 1 : 2;
        },
    });
}
</script>
<template>
    <div class="request-wizard">
        <div>
            <ol class="request-steps" aria-label="Pasos para solicitar presupuesto">
                <li v-for="(label, index) in labels" :key="label">
                    <button type="button" :disabled="index + 1 > step || form.processing || handoff.processing" :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">{{ index + 1 }}. {{ label }}</button>
                </li>
            </ol>
            <v-card class="card">
                <p class="eyebrow">Paso {{ step }} de 4</p>
                <h2>{{ ['Elegí el servicio', 'Contanos qué necesitás', 'Revisá tus datos de contacto', 'Revisá tu solicitud'][step - 1] }}</h2>
                <div v-if="Object.keys(form.errors).length" class="notice" role="alert"><ul><li v-for="(error, field) in form.errors" :key="field">{{ error }}</li></ul></div>

                <!-- Solo se valida el paso visible al avanzar; Laravel comprueba el pedido completo al guardarlo. -->
                <form v-if="step < 4" @submit.prevent="advance">
                    <template v-if="step === 1">
                        <ServicePicker v-model="form.service_id" :services="data.services" :disabled="!!form.estimate_id" :error="form.errors.service_id" />
                        <template v-if="!estimate">
                            <template v-for="question in questions" :key="question.id">
                                <fieldset v-if="question.input_type === 'multiple_choice'" class="request-question">
                                    <legend>{{ question.question }}</legend>
                                    <label v-for="option in question.options" :key="option.id" class="select-row"><input v-model="form.answers[question.id]" type="checkbox" :value="String(option.id)">{{ option.label }}</label>
                                    <small v-if="form.errors['answers.' + question.id]" class="error" role="alert">{{ form.errors['answers.' + question.id] }}</small>
                                </fieldset>
                                <F v-else v-model="form.answers[question.id]" :label="question.question" :type="question.input_type === 'single_choice' ? 'select' : question.input_type === 'number' ? 'number' : 'textarea'"
                                    :options="question.options" :min="question.input_type === 'number' ? 1 : undefined" :max="question.input_type === 'number' ? 10000 : undefined" :maxlength="5000"
                                    :error="form.errors['answers.' + question.id]" required />
                            </template>
                        </template>
                        <p v-if="estimate" class="request-origin"><I name="check" /> Servicio y respuestas recuperados de tu estimación. Podés agregar otros servicios a la consulta.</p>
                        <section v-if="estimateAnswers.length" class="request-answers"><h3>Respuestas de tu estimación</h3><dl><div v-for="answer in estimateAnswers" :key="answer.id"><dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd></div></dl></section>
                        <h3>Servicios adicionales</h3>
                        <p>Agregá otros servicios que quieras incluir en la consulta.</p>
                        <div v-for="(item, index) in form.additional_services" :key="index" class="request-extra">
                            <F v-model="item.service_id" :label="'Servicio adicional ' + (index + 1)" type="select" :options="extraOptions(index)" required :error="form.errors['additional_services.' + index + '.service_id']" />
                            <F v-model="item.quantity" label="Cantidad" type="number" min="1" max="1000" step="1" required :error="form.errors['additional_services.' + index + '.quantity']" />
                            <F v-model="item.notes" label="Qué necesitás de este servicio" type="textarea" maxlength="2000" :error="form.errors['additional_services.' + index + '.notes']" />
                            <B variant="ghost" @click="form.additional_services.splice(index, 1)">Quitar servicio</B>
                        </div>
                        <B variant="secondary" :disabled="form.additional_services.length >= Math.min(extras.length, 20)" @click="addService">Agregar servicio</B>
                    </template>
                    <template v-else-if="step === 2">
                        <F v-model="form.title" label="Título del proyecto" placeholder="Ej.: Renovación web para mi estudio" minlength="5" maxlength="255" required :error="form.errors.title" />
                        <F v-model="form.description" label="Descripción y alcance" type="textarea" hint="Contá el objetivo, lo que ya tenés y qué resultado esperás. Incluí los adicionales que necesites." minlength="10" maxlength="10000" required :error="form.errors.description" />
                        <F v-model="form.skills" label="Habilidades deseadas (opcional, separadas por comas)" maxlength="1000" :error="form.errors.skills" />
                        <F v-model="form.budget_reference" label="Presupuesto disponible (opcional)" maxlength="255" :error="form.errors.budget_reference" />
                        <F v-model="form.desired_deadline" label="Plazo deseado (opcional)" maxlength="255" :error="form.errors.desired_deadline" />
                        <F v-if="data.allowPublicRequest || old?.visibility === 'public'" v-model="form.visibility" label="Visibilidad" type="select" :options="[{ value:'private', label:'Privada · revisión de Synaux' }, { value:'public', label:'Pública · visible en el muro' }]" />
                        <p v-else>Tu solicitud se enviará de forma privada a Synaux.</p>
                    </template>
                    <template v-else-if="step === 3 && !authenticated">
                        <div class="request-auth-gate"><I name="user" /><h3>Guardá tu proyecto en tu cuenta</h3><p>Ingresá o creá una cuenta para revisar tus datos de contacto y confirmar el envío. Vamos a conservar el servicio, las respuestas y todo lo que completaste.</p><p>Tu solicitud todavía no se enviará a Synaux.</p><div class="button-stack"><B :disabled="handoff.processing" @click="prepareJourney('login')">{{ handoff.processing ? 'Preparando…' : 'Ingresar y continuar' }}</B><B variant="secondary" :disabled="handoff.processing" @click="prepareJourney('register')">Crear cuenta y continuar</B><B variant="ghost" :disabled="handoff.processing" @click="step = 2">Volver al proyecto</B></div></div>
                    </template>
                    <template v-else-if="step === 3">
                        <F v-model="form.contact.name" label="Nombre de contacto" autocomplete="name" maxlength="255" required :error="form.errors['contact.name']" />
                        <F v-model="form.contact.email" label="Correo de contacto" type="email" autocomplete="email" maxlength="254" required :error="form.errors['contact.email']" />
                        <F v-model="form.contact.company" label="Empresa u organización (opcional)" autocomplete="organization" maxlength="255" :error="form.errors['contact.company']" />
                        <F v-model="form.contact.phone" label="Teléfono (opcional)" type="tel" autocomplete="tel" maxlength="50" :error="form.errors['contact.phone']" />
                        <F v-model="form.contact.address" label="Dirección (opcional)" autocomplete="street-address" maxlength="500" :error="form.errors['contact.address']" />
                        <p>Estos datos acompañan el pedido para que Synaux pueda preparar tu presupuesto.</p>
                    </template>
                    <div v-if="step !== 3 || authenticated" class="form-actions"><B v-if="step > 1" variant="secondary" @click="step -= 1">Anterior</B><span v-else /><B type="submit" arrow :disabled="!selectedService || handoff.processing">{{ step === 3 ? 'Revisar solicitud' : 'Continuar' }}</B></div>
                </form>

                <template v-else>
                    <div class="review-section-heading"><h3>Tu proyecto</h3><B variant="ghost" @click="step = 2">Editar proyecto</B></div>
                    <strong>{{ form.title || 'Tu proyecto' }}</strong>
                    <p class="preserve-lines">{{ form.description }}</p>
                    <div class="review-section-heading"><h3>Servicios y contacto</h3><B variant="ghost" @click="step = 1">Editar servicios</B><B variant="ghost" @click="step = 3">Editar contacto</B></div><dl class="data-list">
                        <div><dt>Servicio principal</dt><dd>{{ selectedService?.name }}</dd></div>
                        <div v-for="(item, index) in form.additional_services" :key="index"><dt>{{ extraName(item.service_id) }} × {{ item.quantity }}</dt><dd>{{ item.notes || 'Alcance a revisar' }}</dd></div>
                        <div><dt>Contacto</dt><dd>{{ form.contact.name }}<br>{{ form.contact.email }}</dd></div>
                        <div v-if="form.contact.company"><dt>Organización</dt><dd>{{ form.contact.company }}</dd></div>
                        <div v-if="form.contact.phone"><dt>Teléfono</dt><dd>{{ form.contact.phone }}</dd></div>
                        <div v-if="form.contact.address"><dt>Dirección</dt><dd>{{ form.contact.address }}</dd></div>
                        <div v-if="form.skills"><dt>Habilidades deseadas</dt><dd>{{ form.skills }}</dd></div><div><dt>Plazo</dt><dd>{{ form.desired_deadline || 'A coordinar' }}</dd></div>
                        <div><dt>Presupuesto disponible</dt><dd>{{ form.budget_reference || 'A definir' }}</dd></div>
                        <div><dt>Visibilidad</dt><dd>{{ form.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div>
                    </dl>
                    <p class="request-origin"><I name="info" /> Al enviar, Synaux revisará el alcance y preparará tu presupuesto. Esta solicitud no confirma una contratación.</p><div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="step = 3">Anterior</B><B :disabled="form.processing" arrow @click="save(true)">{{ form.processing ? 'Enviando…' : form.visibility === 'public' ? 'Publicar solicitud' : 'Solicitar presupuesto' }}</B></div>
                </template>
                <B v-if="step >= 2 && authenticated" class="draft-action" variant="ghost" :disabled="form.processing || !canSaveDraft" @click="save(false)">{{ form.processing && !form.publish ? 'Guardando…' : 'Guardar borrador' }}</B><p v-if="step >= 2 && authenticated && !canSaveDraft" class="draft-help">Completá el título y la descripción para guardar el borrador.</p>
            </v-card>
        </div>

        <!-- El resumen se actualiza con los mismos valores del formulario y se apila debajo en móvil. -->
        <aside class="request-summary">
            <v-card class="card">
                <p class="eyebrow">Tu solicitud</p><h2>Resumen</h2><div v-if="form.title" class="summary-extra"><strong>{{ form.title }}</strong><p v-if="form.desired_deadline">Plazo deseado: {{ form.desired_deadline }}</p></div>
                <h3>{{ selectedService?.name }}</h3>
                <p>{{ selectedService?.summary }}</p>
                <ul v-if="selectedService?.includes?.length"><li v-for="item in selectedService.includes" :key="item">{{ item }}</li></ul>
                <div class="request-price"><p>{{ estimate ? 'Estimación guardada · servicio principal' : 'Referencia del servicio principal' }}</p><strong v-if="reference">{{ money(reference.min, reference.currency) }}<template v-if="reference.max !== reference.min"> – {{ money(reference.max, reference.currency) }}</template></strong><strong v-else>A cotizar según el alcance</strong><p v-if="estimate">{{ date(estimate.created_at) }}</p><p v-else-if="reference?.segment">{{ reference.segment }}</p><small v-if="form.additional_services.length">Los servicios adicionales se cotizan por separado y no están sumados a este importe.</small></div>
                <dl v-if="estimateAnswers.length" class="summary-answers"><div v-for="answer in estimateAnswers" :key="answer.id"><dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd></div></dl>
                <div v-for="(item, index) in form.additional_services" :key="index" class="summary-extra"><strong>{{ extraName(item.service_id) }} × {{ item.quantity }}</strong><p>{{ item.notes || 'A cotizar con el alcance del proyecto.' }}</p></div>
                <p v-if="form.contact.name">{{ form.contact.name }}<br>{{ form.contact.email }}</p>
                <p class="summary-note">Synaux revisará el pedido y enviará un presupuesto formal con el total, los plazos y las condiciones.</p>
            </v-card>
        </aside>
    </div>
</template>
<style scoped>
/* La grilla mantiene el resumen a la vista sin cubrir los campos ni el pie de página. */
.request-wizard { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 2rem; align-items: start; }
.request-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; padding: 0; margin: 0 0 1.25rem; list-style: none; }
.request-steps button { width: 100%; min-height: 48px; font-weight: 700; padding: .6rem .75rem; border: 1px solid rgb(31 111 127 / 20%); border-radius: .5rem; font: inherit; color: #153b64; background: #fff; cursor: pointer; }
.request-steps button[aria-current='step'] { background: #153b64; color: #fff; }
.request-steps button:disabled { color: #446075; cursor: default; }
.request-summary { position: sticky; top: 1.5rem; }
.request-summary ul { padding-left: 1.25rem; margin-bottom: 1rem; }
.request-extra, .summary-extra { border-top: 1px solid rgb(31 111 127 / 18%); padding-top: 1rem; margin-top: 1rem; }
.draft-action { margin-top: 1rem; }
.summary-note { color: #446075; margin-top: 1.25rem; }
@media (max-width: 900px) { .request-wizard { grid-template-columns: minmax(0, 1fr); } .request-summary { position: static; } }
/* La revisión y los importes comparten la jerarquía del estimador y admiten textos largos. */
.request-wizard :deep(h2) { font-size: clamp(1.5rem, 2.3vw, 2rem); font-weight: 700; line-height: 1.2; }
.request-wizard :deep(h3) { font-weight: 700; }
.request-steps button:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 3px; }
.request-steps button:disabled { background: #f5f9fa; opacity: .65; }
.request-origin { display: flex; gap: 10px; align-items: flex-start; padding: 14px; background: #f5f9fa; border-radius: 10px; }
.request-origin .icon { flex: 0 0 22px; color: #1f6f7f; }
.request-answers dl, .summary-answers { margin: 0 0 20px; }
.request-answers dl > div, .summary-answers > div { padding: 12px 0; border-bottom: 1px solid #1f6f7f22; }
.request-answers dd, .summary-answers dd { margin: 4px 0 0; color: #153b64; font-weight: 700; overflow-wrap: anywhere; }
.request-price { padding: 18px; background: #f5f9fa; border: 1px solid #1f6f7f22; border-radius: 12px; margin: 20px 0; }
.request-price strong { display: block; font-family: 'Space Grotesk', sans-serif; font-size: 1.65rem; line-height: 1.3; overflow-wrap: anywhere; }
.request-price p { margin: 0 0 8px; }
.request-price small { display: block; margin-top: 12px; }
.review-section-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 18px; }
.review-section-heading h3 { flex: 1 1 auto; margin: 0; }
.request-wizard .data-list dd, .request-summary { overflow-wrap: anywhere; min-width: 0; }
.request-auth-gate { padding: 20px; border: 1px solid #1f6f7f33; border-radius: 12px; background: #f5f9fa; }
.request-auth-gate > .icon { width: 28px; height: 28px; color: #1f6f7f; margin-bottom: 14px; }
.request-question { padding: 16px; border: 1px solid #1f6f7f33; border-radius: 10px; margin-bottom: 20px; }
.draft-help { font-size: .9rem; margin: 8px 0 0; }
@media (max-width: 600px) { .request-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } .request-wizard :deep(.form-actions) { flex-wrap: wrap; gap: 12px; } .request-wizard :deep(.form-actions .btn) { flex: 1 1 100%; } }
</style>
