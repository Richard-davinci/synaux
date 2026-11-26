<script setup>
import {computed, nextTick, reactive, ref, toRef, watch} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import I from '../UI/AppIcon.vue';
import ServicePicker from '../Catalog/ServicePicker.vue';
import RequestQuestions from './PreguntasSolicitud.vue';
import AdditionalServices from './ServiciosAdicionales.vue';
import RequestSummary from './ResumenSolicitud.vue';
import ReiniciarEstimacion from '../Estimates/ReiniciarEstimacion.vue';
import {useRequestPricing} from './usePreciosSolicitud';
import {useSubmit} from '../../support/useSubmit';

// Los borradores existentes se recuperan con sus datos y las solicitudes nuevas parten de la cuenta actual.
const props = defineProps({data: Object});
const page = usePage();
const old = props.data.selected?.status === 'draft' ? props.data.selected : null;
const estimate = props.data.selectedEstimate ?? props.data.estimates?.find(item => item.id === old?.estimate_id);
const pending = props.data.requestDraft ?? {};
// La vista de referencia anterior abre el nuevo paso final, igual que una solicitud retomada.
const step = ref(props.data.requestStep ?? (props.data.referenceKey === 'publicar-revision' ? 3 : 1));
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

// Los datos de contacto se confirman en el mismo paso en que se envía la solicitud.
const labels = ['Servicios', 'Proyecto', 'Confirmar y enviar'];
const titulos = ['Elegí los servicios', 'Contanos qué necesitás', 'Confirmá tus datos de contacto'];
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

// La acción conserva el texto de publicación cuando el cliente eligió visibilidad pública.
const textoEnvio = computed(() => form.visibility === 'public' ? 'Publicar solicitud' : 'Solicitar presupuesto');

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
// El servicio sugerido y las respuestas vacías forman parte del estado inicial.
form.defaults();
const puedeReiniciar = computed(() => !old && Boolean(
    step.value > 1 || form.isDirty || estimate || props.data.requestDraft || props.data.pending
));

function extraName(id) {
    return props.data.services.find(item => item.database_id == id)?.name ?? 'Elegí un servicio';
}

// El visitante prepara el proyecto antes de ingresar; no se envía una solicitud hasta su confirmación autenticada.
function advance() {
    if (step.value === 1 && !selectedService.value) return;
    if (form.processing || handoff.processing) return;
    if (step.value === 3) {
        if (authenticated.value) save(true);
        return;
    }
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
        <!-- Los tres pasos quedan en una fila y se fijan debajo de las barras del sitio. -->
        <ol class="request-steps" aria-label="Pasos para solicitar presupuesto">
            <li v-for="(label, index) in labels" :key="label">
                <button type="button" :disabled="index + 1 > step || form.processing || handoff.processing"
                        :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">
                    <span class="request-step-number">{{ index + 1 }}</span>
                    <span>{{ label }}</span>
                </button>
            </li>
        </ol>
        <RequestSummary :form="form" :pricing="pricing" :selected-service="selectedService"
                        :estimacion-guardada="estimate" :extra-name="extraName"/>
        <div class="request-main">
            <v-card class="card request-card">
                <h2 class="request-step-title">{{ titulos[step - 1] }}</h2>
                <div v-if="Object.keys(form.errors).length" class="notice" role="alert">
                    <ul>
                        <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                    </ul>
                </div>

                <!-- El último paso valida el contacto antes de enviar; Laravel comprueba la solicitud completa. -->
                <form @submit.prevent="advance">
                    <template v-if="step === 1">
                        <!-- El servicio heredado aparece como dato guardado, con los adicionales aún editables. -->
                        <div v-if="form.estimate_id && selectedService" class="request-saved-service">
                            <small>Servicio de tu estimación</small>
                            <strong>{{ selectedService.name }}</strong>
                        </div>
                        <ServicePicker v-else v-model="form.service_id" :services="data.services"
                                       :disabled="!!form.estimate_id" :error="form.errors.service_id"/>
                        <RequestQuestions v-if="!estimate" v-model="form.answers" :questions="questions"
                                          :errors="form.errors" prefix="answers."/>
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
                        <div class="request-fields">
                            <F v-model="form.budget_reference" label="Presupuesto disponible (opcional)" maxlength="255"
                               :error="form.errors.budget_reference"/>
                            <F v-model="form.desired_deadline" label="Plazo deseado (opcional)" maxlength="255"
                               :error="form.errors.desired_deadline"/>
                        </div>
                        <F v-if="data.allowPublicRequest || old?.visibility === 'public'" v-model="form.visibility"
                           label="Visibilidad" type="select"
                           :options="[{ value:'private', label:'Privada · revisión de synaux' }, { value:'public', label:'Pública · visible en el muro' }]"/>
                        <p v-else class="request-note">Tu solicitud se enviará de forma privada a synaux.</p>
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
                        <div class="request-fields">
                            <F v-model="form.contact.name" label="Nombre de contacto" autocomplete="name" maxlength="255"
                               required :error="form.errors['contact.name']"/>
                            <F v-model="form.contact.email" label="Correo de contacto" type="email" autocomplete="email"
                               maxlength="254" required :error="form.errors['contact.email']"/>
                            <F v-model="form.contact.company" label="Empresa u organización (opcional)"
                               autocomplete="organization" maxlength="255" :error="form.errors['contact.company']"/>
                            <F v-model="form.contact.phone" label="Teléfono (opcional)" type="tel" autocomplete="tel"
                               maxlength="50" :error="form.errors['contact.phone']"/>
                        </div>
                        <F v-model="form.contact.address" label="Domicilio para este pedido (opcional)" autocomplete="street-address"
                           hint="Se completa con el domicilio guardado en Mi perfil. Podés editarlo para este pedido."
                           maxlength="500" :error="form.errors['contact.address']"/>
                    </template>
                    <div v-if="step !== 3 || authenticated" class="form-actions">
                        <B v-if="step > 1" variant="secondary" :disabled="form.processing || handoff.processing"
                           @click="step -= 1">Anterior</B>
                        <B type="submit" arrow :disabled="!selectedService || form.processing || handoff.processing">
                            <template v-if="step === 3">{{ form.processing && form.publish ? 'Enviando…' : textoEnvio }}</template>
                            <template v-else>Continuar</template>
                        </B>
                    </div>
                </form>

                <!-- El reinicio se ofrece en el flujo nuevo, con confirmación si hay datos para descartar. -->
                <div class="acciones-borrador" v-if="(step >= 2 && authenticated) || puedeReiniciar">
                    <p v-if="step >= 2 && authenticated" class="draft-help">
                        Si guardás ahora, vas a encontrar la preparación en Estimaciones. Al enviarla a synaux, pasará a Solicitudes.
                    </p>
                    <div class="acciones-borrador-botones">
                        <B v-if="step >= 2 && authenticated" variant="secondary"
                           :disabled="form.processing || handoff.processing || !canSaveDraft"
                           @click="save(false)">{{ form.processing && !form.publish ? 'Guardando…' : 'Guardar en Estimaciones' }}</B>
                        <ReiniciarEstimacion v-if="puedeReiniciar"
                                             :deshabilitado="form.processing || handoff.processing"/>
                    </div>
                </div>
                <p v-if="step >= 2 && authenticated && !canSaveDraft" class="draft-help">Completá el título y la
                    descripción para guardar el borrador.</p>
            </v-card>
        </div>
    </div>
</template>

<style scoped>

/* El progreso y la vista previa se mantienen visibles sin cubrir los campos. */
.request-wizard {
    --steps-height: 61px;
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 16px clamp(16px, 2.5vw, 28px);
    align-items: start;
    scroll-margin-top: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px));
}

.request-main {
    grid-column: 1;
    min-width: 0;
}

.request-main .request-card {
    padding: clamp(16px, 2.2vw, 26px);
}

.request-main .request-step-title {
    margin: 0 0 18px;
    font-size: clamp(1.25rem, 2vw, 1.5rem);
    font-weight: 700;
    line-height: 1.2;
}

.request-steps {
    grid-column: 1 / -1;
    position: sticky;
    top: calc(var(--navbar-height, 108px) + var(--workspace-bar-height, 0px));
    z-index: 45;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    padding: 8px 0;
    margin: 0;
    list-style: none;
    background: #fff;
    border-bottom: 1px solid #1f6f7f22;
}

.request-steps button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 44px;
    padding: 4px 6px;
    border: 1px solid #1f6f7f26;
    border-radius: 8px;
    font: 700 .875rem/1.2 'Nunito', sans-serif;
    color: #153b64;
    background: #fff;
    cursor: pointer;
}

.request-step-number {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    color: #153b64;
    background: #e8f3f4;
}

.request-steps button[aria-current='step'] {
    border-color: #153b64;
    background: #153b64;
    color: #fff;
}

.request-steps button[aria-current='step'] .request-step-number {
    background: #fff;
}

.request-steps button:disabled {
    color: #446075;
    background: #f5f9fa;
    opacity: 1;
    cursor: default;
}

.request-steps button:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

/* Los campos relacionados comparten fila y las acciones se alinean al pie de la tarjeta. */
.request-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 16px;
}

.request-note {
    margin: 0;
    color: #446075;
    font-size: .9rem;
}

.request-main :deep(.form-actions) {
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px 12px;
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #1f6f7f22;
}

.request-main :deep(.form-actions > .btn) {
    flex: 0 1 auto;
}

.request-main :deep(.form-actions > .btn:first-child:not(:last-child)) {
    margin-right: auto;
}

.request-main :deep(h3) {
    font-weight: 700;
}

.acciones-borrador {
    display: grid;
    gap: 12px;
    margin-top: 16px;
    padding: 14px 16px;
    border: 1px solid #1f6f7f2e;
    border-left: 3px solid #1f6f7f;
    border-radius: 10px;
    background: #f5f9fa;
}

.acciones-borrador-botones {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

@media (max-width: 900px) {
    .request-wizard {
        grid-template-columns: minmax(0, 1fr);
        row-gap: 12px;
    }
}

.request-saved-service {
    display: grid;
    gap: 3px;
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #1f6f7f26;
    border-radius: 8px;
    background: #f5f9fa;
    overflow-wrap: anywhere;
}

.request-saved-service small {
    color: #446075;
}

.request-answers dl {
    margin: 0;
}

.request-answers dl > div {
    padding: 12px 0;
    border-bottom: 1px solid #1f6f7f22;
}

.request-answers dl > div:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.request-answers dd {
    margin: 4px 0 0;
    color: #153b64;
    font-weight: 700;
    overflow-wrap: anywhere;
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
    margin: 0;
}

@media (max-width: 520px) {
    .acciones-borrador-botones {
        justify-content: stretch;
    }

    .acciones-borrador-botones :deep(.btn) {
        flex: 1 1 auto;
    }
}

@media (max-width: 600px) {
    .request-wizard {
        --steps-height: 69px;
    }

    .request-steps {
        gap: 4px;
    }

    .request-steps button {
        flex-direction: column;
        gap: 3px;
        min-height: 52px;
        font-size: .75rem;
    }

    .request-fields {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
