<script setup>
import {computed, nextTick, reactive, ref, toRef, watch} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import I from '../UI/AppIcon.vue';
import ServicePicker from '../Catalog/ServicePicker.vue';
import RequestQuestions from './PreguntasSolicitud.vue';
import AdditionalServices from './ServiciosAdicionales.vue';
import RequestReview from './RevisionSolicitud.vue';
import RequestSummary from './ResumenSolicitud.vue';
import {useRequestPricing} from './usePreciosSolicitud';
import {useSubmit} from '../../support/useSubmit';

// Los borradores existentes se recuperan con sus datos y las solicitudes nuevas parten de la cuenta actual.
const props = defineProps({data: Object});
const page = usePage();
const old = props.data.selected?.status === 'draft' ? props.data.selected : null;
const estimate = props.data.selectedEstimate ?? props.data.estimates?.find(item => item.id === old?.estimate_id);
const pending = props.data.requestDraft ?? {};
const step = ref(props.data.requestStep ?? (props.data.referenceKey === 'publicar-revision' ? 4 : 1));
const authenticated = computed(() => !!(page.props.auth || props.data.preview));
const handoff = useForm({});
// Los pasos permanecen visibles y el cambio de paso vuelve al inicio del formulario.
const wizard = ref(null);
watch(step, async () => {
    await nextTick();
    wizard.value?.scrollIntoView({block: 'start', behavior: 'instant'});
});
// El adicional abierto se conserva al volver al paso de servicios o recibir errores.
const expandedExtra = ref(null);

const labels = ['Servicio', 'Tu proyecto', 'Contacto', 'Revisión'];
const submit = useSubmit();
// Un borrador conserva su instantánea, incluso si luego cambia el contacto de la cuenta.
const contact = old?.contact ?? props.data.accountContact ?? page.props.auth ?? {};
// Los borradores guardados traen respuestas con su etiqueta, mientras la sesión conserva los valores del formulario.
const extraQuestions = id => (props.data.requestQuestions?.[id] ?? []).filter(question => question.service_id != null);

function initialExtraAnswers(serviceId, previous = {}) {
    const values = Array.isArray(previous) ? Object.fromEntries(previous.map(answer => [answer.id, answer.value])) : {...previous};
    for (const question of extraQuestions(serviceId)) {
        if (question.input_type === 'multiple_choice') {
            values[question.id] = Array.isArray(values[question.id]) ? values[question.id].map(String) : [];
        }
    }
    return values;
}

const form = useForm({
    id: old?.id ?? null,
    estimate_id: estimate?.id ?? old?.estimate_id ?? null,
    service_id: estimate?.service_id ?? pending.service_id ?? old?.service_id ?? props.data.requestServiceId ?? props.data.services[0]?.database_id,
    title: pending.title ?? old?.title ?? '',
    description: pending.description ?? old?.description ?? props.data.requestInitialDescription ?? '',
    category: old?.category ?? '',
    skills: pending.skills ?? old?.skills?.join(', ') ?? '',
    visibility: page.props.stageTwo ? (old?.visibility ?? 'private') : 'private',
    budget_reference: pending.budget_reference ?? old?.budget_reference ?? '',
    desired_deadline: pending.desired_deadline ?? old?.desired_deadline ?? '',
    contact: {
        name: contact.name ?? '',
        email: contact.email ?? '',
        company: contact.company ?? '',
        phone: contact.phone ?? '',
        address: contact.address ?? '',
    },
    additional_services: (pending.additional_services ?? old?.additional_services ?? []).map(item => ({
        service_id: item.service_id, quantity: item.quantity, notes: item.notes ?? '',
        answers: initialExtraAnswers(item.service_id, item.answers),
    })),
    answers: {...(pending.answers ?? props.data.requestInitialAnswers ?? {})},
    publish: false,
});
watch(() => form.errors, errors => {
    const field = Object.keys(errors).find(field => field.startsWith('additional_services.'));
    if (field) expandedExtra.value = form.additional_services[Number(field.split('.')[1])] ?? null;
}, {deep: true});
const selectedService = computed(() => props.data.services.find(item => item.database_id == form.service_id));
const questions = computed(() => (props.data.requestQuestions?.[form.service_id] ?? []).filter(question => !question.from_project_description));
const initializeAnswers = () => {
    for (const question of questions.value) {
        if (question.input_type === 'multiple_choice' && !Array.isArray(form.answers[question.id])) form.answers[question.id] = [];
    }
};
initializeAnswers();
const extras = computed(() => props.data.services.filter(item => item.database_id != form.service_id));
// La estimación del servidor solo se muestra como actual si coinciden todas las respuestas.
const estimateAnswers = computed(() => estimate?.answers ?? old?.answers ?? []);
const pricing = reactive(useRequestPricing(toRef(props, 'data'), form, {
    estimate,
    old,
    selectedService,
    questions,
    extraQuestions
}));

function extraAnswerText(item, question) {
    const value = item.answers[question.id];
    if (value === '' || value == null) return '';
    const selected = (Array.isArray(value) ? value : [value]).map(String);
    return question.options?.length
        ? question.options.filter(option => selected.includes(String(option.id))).map(option => option.label).join(', ')
        : String(value);
}

const canSaveDraft = computed(() => form.title.trim().length >= 5 && form.description.trim().length >= 10);

// La categoría se deriva del servicio y un mismo servicio no se repite como adicional.
watch(selectedService, (service, previous) => {
    if (previous && service?.database_id !== previous.database_id) form.answers = {};
    initializeAnswers();
    if (service) {
        form.category = service.category;
        form.additional_services = form.additional_services.filter(item => item.service_id != service.database_id);
    }
}, {immediate: true});

function extraName(id) {
    return props.data.services.find(item => item.database_id == id)?.name ?? 'Elegí un servicio';
}

// El visitante prepara el proyecto antes de ingresar; no se envía una solicitud hasta su confirmación autenticada.
function advance() {
    if (step.value === 1 && !selectedService.value) return;
    if (step.value === 3 && !authenticated.value) return;
    // Al completar el proyecto se obtiene el cálculo real y se conserva el avance antes del contacto.
    if (step.value === 2 && !props.data.preview) {
        prepareJourney('review');
        return;
    }
    step.value += 1;
}

function prepareJourney(destination) {
    handoff.transform(() => ({...form.data(), destination}));
    handoff.post('/solicitudes/preparar', {
        preserveScroll: true,
        // Volver a calcular puede devolver la misma URL y conservar el paso local de Inertia.
        onSuccess: result => {
            if (['publicar', 'solicitud'].includes(result.props.screen) && result.props.requestStep === 3) step.value = 3;
        },
        onError: errors => {
            form.setError(errors);
            step.value = Object.keys(errors).some(field => field === 'service_id' || field.startsWith('answers.') || field.startsWith('additional_services')) ? 1 : 2;
        },
    });
}

// Un error vuelve al paso correspondiente y conserva los datos que el cliente escribió.
function save(publish) {
    if (!authenticated.value) {
        step.value = 3;
        return;
    }
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
    <div ref="wizard" class="request-wizard">
        <!-- La navegación ocupa su propia fila y se fija debajo de las barras reales del sitio. -->
        <ol class="request-steps" aria-label="Pasos para solicitar presupuesto">
            <li v-for="(label, index) in labels" :key="label">
                <button type="button" :disabled="index + 1 > step || form.processing || handoff.processing"
                        :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">{{
                        index + 1
                    }}. {{ label }}
                </button>
            </li>
        </ol>
        <RequestSummary :form="form" :pricing="pricing" :selected-service="selectedService"
                        :estimacion-guardada="estimate" :extra-name="extraName"/>
        <div class="request-main">
            <v-card class="card">
                <p class="eyebrow">Paso {{ step }} de 4</p>
                <h2>{{
                        ['Elegí el servicio', 'Contanos qué necesitás', 'Revisá tus datos de contacto', 'Revisá tu solicitud'][step - 1]
                    }}</h2>
                <div v-if="Object.keys(form.errors).length" class="notice" role="alert">
                    <ul>
                        <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                    </ul>
                </div>

                <!-- Solo se valida el paso visible al avanzar; Laravel comprueba el pedido completo al guardarlo. -->
                <form v-if="step < 4" @submit.prevent="advance">
                    <template v-if="step === 1">
                        <ServicePicker v-model="form.service_id" :services="data.services"
                                       :disabled="!!form.estimate_id" :error="form.errors.service_id"/>
                        <RequestQuestions v-if="!estimate" v-model="form.answers" :questions="questions"
                                          :errors="form.errors" prefix="answers."/>
                        <p v-if="estimate" class="request-origin"><I name="check"/> Servicio y respuestas recuperados de
                            tu estimación. Podés agregar otros servicios a la consulta.</p>
                        <section v-if="estimateAnswers.length" class="request-answers"><h3>Respuestas de tu
                            estimación</h3>
                            <dl>
                                <div v-for="answer in estimateAnswers" :key="answer.id">
                                    <dt>{{ answer.question }}</dt>
                                    <dd>{{ answer.label }}</dd>
                                </div>
                            </dl>
                        </section>
                        <AdditionalServices v-model:expanded="expandedExtra" :form="form" :extras="extras"
                                            :additional-prices="pricing.additionalPrices"
                                            :summary-currency="pricing.summaryCurrency"
                                            :extra-name="extraName" :extra-questions="extraQuestions"
                                            :initial-extra-answers="initialExtraAnswers"/>
                    </template>
                    <template v-else-if="step === 2">
                        <F v-model="form.title" label="Título del proyecto"
                           placeholder="Ej.: Renovación web para mi estudio" minlength="5" maxlength="255" required
                           :error="form.errors.title"/>
                        <F v-model="form.description" label="Descripción y alcance" type="textarea"
                           hint="Contá el objetivo, lo que ya tenés y qué resultado esperás. Incluí los adicionales que necesites."
                           minlength="10" maxlength="10000" required :error="form.errors.description"/>
                        <F v-model="form.skills" label="Habilidades deseadas (opcional, separadas por comas)"
                           maxlength="1000" :error="form.errors.skills"/>
                        <F v-model="form.budget_reference" label="Presupuesto disponible (opcional)" maxlength="255"
                           :error="form.errors.budget_reference"/>
                        <F v-model="form.desired_deadline" label="Plazo deseado (opcional)" maxlength="255"
                           :error="form.errors.desired_deadline"/>
                        <F v-if="data.allowPublicRequest || old?.visibility === 'public'" v-model="form.visibility"
                           label="Visibilidad" type="select"
                           :options="[{ value:'private', label:'Privada · revisión de synaux' }, { value:'public', label:'Pública · visible en el muro' }]"/>
                        <p v-else>Tu solicitud se enviará de forma privada a synaux.</p>
                    </template>
                    <template v-else-if="step === 3 && !authenticated">
                        <div class="request-auth-gate"><I name="user"/>
                            <h3>Guardá tu proyecto en tu cuenta</h3>
                            <p>Ingresá o creá una cuenta para revisar tus datos de contacto y confirmar el envío. Vamos
                                a conservar el servicio, las respuestas y todo lo que completaste.</p>
                            <p>Tu solicitud todavía no se enviará a synaux.</p>
                            <div class="button-stack"><B :disabled="handoff.processing"
                                                         @click="prepareJourney('login')">{{
                                    handoff.processing ? 'Preparando…' : 'Ingresar y continuar'
                                }}</B><B variant="secondary" :disabled="handoff.processing"
                                         @click="prepareJourney('register')">Crear cuenta y continuar</B><B
                                variant="ghost" :disabled="handoff.processing" @click="step = 2">Volver al proyecto</B>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="step === 3">
                        <F v-model="form.contact.name" label="Nombre de contacto" autocomplete="name" maxlength="255"
                           required :error="form.errors['contact.name']"/>
                        <F v-model="form.contact.email" label="Correo de contacto" type="email" autocomplete="email"
                           maxlength="254" required :error="form.errors['contact.email']"/>
                        <F v-model="form.contact.company" label="Empresa u organización (opcional)"
                           autocomplete="organization" maxlength="255" :error="form.errors['contact.company']"/>
                        <F v-model="form.contact.phone" label="Teléfono (opcional)" type="tel" autocomplete="tel"
                           maxlength="50" :error="form.errors['contact.phone']"/>
                        <F v-model="form.contact.address" label="Domicilio para este pedido (opcional)" autocomplete="street-address"
                           hint="Se completa con el domicilio guardado en Mi perfil. Podés editarlo para este pedido."
                           maxlength="500" :error="form.errors['contact.address']"/>
                        <p>Estos datos acompañan el pedido para que synaux pueda preparar tu presupuesto.</p>
                    </template>
                    <div v-if="step !== 3 || authenticated" class="form-actions"><B v-if="step > 1" variant="secondary"
                                                                                    @click="step -= 1">Anterior</B><span
                        v-else/><B type="submit" arrow :disabled="!selectedService || handoff.processing">{{
                            step === 3 ? 'Revisar solicitud' : 'Continuar'
                        }}</B></div>
                </form>

                <RequestReview v-else :form="form" :selected-service="selectedService"
                               :extra-name="extraName" :extra-questions="extraQuestions"
                               :extra-answer-text="extraAnswerText"
                               @go="step = $event" @publish="save(true)"/>
                <B v-if="step >= 2 && authenticated" class="draft-action" variant="ghost"
                   :disabled="form.processing || !canSaveDraft"
                   @click="save(false)">{{ form.processing && !form.publish ? 'Guardando…' : 'Guardar borrador' }}</B>
                <p v-if="step >= 2 && authenticated && !canSaveDraft" class="draft-help">Completá el título y la
                    descripción para guardar el borrador.</p>
            </v-card>
        </div>
    </div>
</template>

<style scoped>
/* La grilla mantiene el resumen a la vista sin cubrir los campos ni el pie de página. */
.request-wizard {
    --steps-height: 72px;
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 20px 2rem;
    align-items: start;
    scroll-margin-top: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px));
}

.request-main {
    grid-column: 1;
    min-width: 0;
}

.request-steps {
    grid-column: 1 / -1;
    position: sticky;
    top: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px));
    z-index: 45;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .5rem;
    padding: 12px 0;
    margin: 0;
    list-style: none;
    background: #fff;
    border-bottom: 1px solid #1f6f7f22;
}

.request-steps button {
    width: 100%;
    min-height: 48px;
    font-weight: 700;
    padding: .6rem .75rem;
    border: 1px solid rgb(31 111 127 / 20%);
    border-radius: .5rem;
    font: inherit;
    color: #153b64;
    background: #fff;
    cursor: pointer;
}

.request-steps button[aria-current='step'] {
    background: #153b64;
    color: #fff;
}

.request-steps button:disabled {
    color: #446075;
    cursor: default;
}

.draft-action {
    margin-top: 1rem;
}

@media (max-width: 900px) {
    .request-wizard {
        grid-template-columns: minmax(0, 1fr);
    }
}

/* La revisión y los importes comparten la jerarquía del estimador y admiten textos largos. */
.request-wizard :deep(h2) {
    font-size: clamp(1.5rem, 2.3vw, 2rem);
    font-weight: 700;
    line-height: 1.2;
}

.request-wizard :deep(h3) {
    font-weight: 700;
}

.request-steps button:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

.request-steps button:disabled {
    background: #f5f9fa;
    opacity: .65;
}

.request-origin {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 14px;
    background: #f5f9fa;
    border-radius: 10px;
}

.request-origin .icon {
    flex: 0 0 22px;
    color: #1f6f7f;
}

.request-answers dl {
    margin: 0 0 20px;
}

.request-answers dl > div {
    padding: 12px 0;
    border-bottom: 1px solid #1f6f7f22;
}

.request-answers dd {
    margin: 4px 0 0;
    color: #153b64;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.request-wizard :deep(.data-list dd) {
    overflow-wrap: anywhere;
    min-width: 0;
}

.request-auth-gate {
    padding: 20px;
    border: 1px solid #1f6f7f33;
    border-radius: 12px;
    background: #f5f9fa;
}

.request-auth-gate > .icon {
    width: 28px;
    height: 28px;
    color: #1f6f7f;
    margin-bottom: 14px;
}

.draft-help {
    font-size: .9rem;
    margin: 8px 0 0;
}

@media (max-width: 600px) {
    .request-wizard {
        --steps-height: 128px;
    }

    .request-steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .request-wizard :deep(.form-actions) {
        flex-wrap: wrap;
        gap: 12px;
    }

    .request-wizard :deep(.form-actions .btn) {
        flex: 1 1 100%;
    }
}
</style>
