<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import B from './SynauxButton.vue';
import F from './SynauxField.vue';
import ServiceReference from './ServiceReference.vue';
import { money, useSubmit } from '../support/synaux';

// Los borradores existentes se recuperan con sus datos y las solicitudes nuevas parten de la cuenta actual.
const props = defineProps({ data: Object });
const page = usePage();
const old = props.data.selected?.status === 'draft' ? props.data.selected : null;
const estimate = props.data.selectedEstimate ?? props.data.estimates?.find(item => item.id === old?.estimate_id);
const step = ref(props.data.referenceKey === 'publicar-revision' ? 4 : 1);
const labels = ['Servicio', 'Tu proyecto', 'Contacto', 'Revisión'];
const submit = useSubmit();
const form = useForm({
    id: old?.id ?? null,
    estimate_id: estimate?.id ?? old?.estimate_id ?? null,
    service_id: estimate?.service_id ?? old?.service_id ?? props.data.services[0]?.database_id,
    title: old?.title ?? '',
    description: old?.description ?? '',
    category: old?.category ?? '',
    skills: old?.skills?.join(', ') ?? '',
    visibility: page.props.stageTwo ? (old?.visibility ?? 'private') : 'private',
    budget_reference: old?.budget_reference ?? '',
    desired_deadline: old?.desired_deadline ?? '',
    contact: {
        name: old?.contact?.name ?? page.props.auth?.name ?? '',
        email: old?.contact?.email ?? page.props.auth?.email ?? '',
        company: old?.contact?.company ?? '',
        phone: old?.contact?.phone ?? '',
        address: old?.contact?.address ?? '',
    },
    additional_services: (old?.additional_services ?? []).map(item => ({
        service_id: item.service_id, quantity: item.quantity, notes: item.notes ?? '',
    })),
    publish: false,
});
const selectedService = computed(() => props.data.services.find(item => item.database_id == form.service_id));
const extras = computed(() => props.data.services.filter(item => item.database_id != form.service_id));
const reference = computed(() => props.data.references?.[selectedService.value?.id]);

// La categoría se deriva del servicio y un mismo servicio no se repite como adicional.
watch(selectedService, (service) => {
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

// Un error vuelve al paso correspondiente y conserva los datos que el cliente escribió.
function save(publish) {
    form.publish = publish;
    submit(form, 'solicitud', {
        previewTarget: publish ? 'solicitud' : 'solicitud-borrador',
        onError: (errors) => {
            const fields = Object.keys(errors);
            step.value = fields.some(field => field.startsWith('contact')) ? 3
                : fields.some(field => field === 'service_id' || field === 'estimate_id' || field.startsWith('additional_services')) ? 1 : 2;
        },
    });
}
</script>
<template>
    <div class="request-wizard">
        <div>
            <ol class="request-steps" aria-label="Pasos para solicitar presupuesto">
                <li v-for="(label, index) in labels" :key="label">
                    <button type="button" :disabled="index + 1 > step" :aria-current="step === index + 1 ? 'step' : undefined" @click="step = index + 1">{{ index + 1 }}. {{ label }}</button>
                </li>
            </ol>
            <v-card class="card">
                <p class="eyebrow">Paso {{ step }} de 4</p>
                <h2>{{ ['Elegí el servicio', 'Contanos qué necesitás', 'Revisá tus datos de contacto', 'Revisá tu solicitud'][step - 1] }}</h2>
                <div v-if="Object.keys(form.errors).length" class="notice" role="alert"><ul><li v-for="(error, field) in form.errors" :key="field">{{ error }}</li></ul></div>

                <!-- Solo se valida el paso visible al avanzar; Laravel comprueba el pedido completo al guardarlo. -->
                <form v-if="step < 4" @submit.prevent="step += 1">
                    <template v-if="step === 1">
                        <F v-model="form.service_id" label="Servicio principal" type="select" :disabled="!!form.estimate_id" :options="data.services.map(item => ({ value: item.database_id, label: item.name }))" :error="form.errors.service_id" required />
                        <ServiceReference :service="selectedService" :reference="reference" />
                        <h3>Servicios adicionales</h3>
                        <p>Agregá otros servicios que quieras incluir en la consulta.</p>
                        <div v-for="(item, index) in form.additional_services" :key="index" class="request-extra">
                            <F v-model="item.service_id" :label="'Servicio adicional ' + (index + 1)" type="select" :options="extraOptions(index)" required :error="form.errors['additional_services.' + index + '.service_id']" />
                            <F v-model="item.quantity" label="Cantidad" type="number" min="1" max="1000" step="1" required :error="form.errors['additional_services.' + index + '.quantity']" />
                            <F v-model="item.notes" label="Qué necesitás de este servicio" type="textarea" maxlength="2000" />
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
                        <F v-if="page.props.stageTwo" v-model="form.visibility" label="Visibilidad" type="select" :options="[{ value:'private', label:'Privada · revisión de Synaux' }, { value:'public', label:'Pública · visible en el muro' }]" />
                        <p v-else>Tu solicitud se enviará de forma privada a Synaux.</p>
                    </template>
                    <template v-else-if="step === 3">
                        <F v-model="form.contact.name" label="Nombre de contacto" autocomplete="name" maxlength="255" required :error="form.errors['contact.name']" />
                        <F v-model="form.contact.email" label="Correo de contacto" type="email" autocomplete="email" maxlength="254" required :error="form.errors['contact.email']" />
                        <F v-model="form.contact.company" label="Empresa u organización (opcional)" autocomplete="organization" maxlength="255" />
                        <F v-model="form.contact.phone" label="Teléfono (opcional)" type="tel" autocomplete="tel" maxlength="50" />
                        <F v-model="form.contact.address" label="Dirección (opcional)" autocomplete="street-address" maxlength="500" />
                        <p>Estos datos acompañan el pedido para que Synaux pueda preparar tu presupuesto.</p>
                    </template>
                    <div class="form-actions"><B v-if="step > 1" variant="secondary" @click="step -= 1">Anterior</B><span v-else /><B type="submit" arrow>{{ step === 3 ? 'Revisar solicitud' : 'Continuar' }}</B></div>
                </form>

                <template v-else>
                    <h3>{{ form.title || 'Tu proyecto' }}</h3>
                    <p class="preserve-lines">{{ form.description }}</p>
                    <dl class="data-list">
                        <div><dt>Servicio principal</dt><dd>{{ selectedService?.name }}</dd></div>
                        <div v-for="(item, index) in form.additional_services" :key="index"><dt>{{ extraName(item.service_id) }} × {{ item.quantity }}</dt><dd>{{ item.notes || 'Alcance a revisar' }}</dd></div>
                        <div><dt>Contacto</dt><dd>{{ form.contact.name }}<br>{{ form.contact.email }}</dd></div>
                        <div v-if="form.contact.company"><dt>Organización</dt><dd>{{ form.contact.company }}</dd></div>
                        <div v-if="form.contact.phone"><dt>Teléfono</dt><dd>{{ form.contact.phone }}</dd></div>
                        <div v-if="form.contact.address"><dt>Dirección</dt><dd>{{ form.contact.address }}</dd></div>
                        <div><dt>Plazo</dt><dd>{{ form.desired_deadline || 'A coordinar' }}</dd></div>
                        <div><dt>Presupuesto disponible</dt><dd>{{ form.budget_reference || 'A definir' }}</dd></div>
                        <div><dt>Visibilidad</dt><dd>{{ form.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div>
                    </dl>
                    <div class="form-actions"><B variant="secondary" @click="step = 3">Anterior</B><B :disabled="form.processing" arrow @click="save(true)">{{ form.visibility === 'public' ? 'Publicar solicitud' : 'Solicitar presupuesto' }}</B></div>
                </template>
                <B v-if="step >= 2" class="draft-action" variant="ghost" :disabled="form.processing" @click="save(false)">Guardar borrador</B>
            </v-card>
        </div>

        <!-- El resumen se actualiza con los mismos valores del formulario y se apila debajo en móvil. -->
        <aside class="request-summary">
            <v-card class="card">
                <p class="eyebrow">Tu solicitud</p><h2>Resumen</h2>
                <h3>{{ selectedService?.name }}</h3>
                <p>{{ selectedService?.summary }}</p>
                <ul v-if="selectedService?.includes?.length"><li v-for="item in selectedService.includes" :key="item">{{ item }}</li></ul>
                <template v-if="estimate"><p>Estimación guardada</p><strong>{{ money(estimate.price_min, estimate.currency_snapshot) }}<template v-if="estimate.price_max != null && estimate.price_max !== estimate.price_min"> – {{ money(estimate.price_max, estimate.currency_snapshot) }}</template></strong></template>
                <template v-else-if="reference"><p>Base orientativa del servicio principal</p><strong>{{ money(reference.min, reference.currency) }}<template v-if="reference.max !== reference.min"> – {{ money(reference.max, reference.currency) }}</template></strong><p v-if="reference.segment">{{ reference.segment }}</p></template>
                <p v-else>Precio a definir al revisar el alcance.</p>
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
.request-steps { display: flex; flex-wrap: wrap; gap: .5rem; padding: 0; margin: 0 0 1.25rem; list-style: none; }
.request-steps button { padding: .6rem .75rem; border: 1px solid rgb(31 111 127 / 20%); border-radius: .5rem; font: inherit; color: #153b64; background: #fff; cursor: pointer; }
.request-steps button[aria-current='step'] { background: #153b64; color: #fff; }
.request-steps button:disabled { color: #446075; cursor: default; }
.request-summary { position: sticky; top: 1.5rem; }
.request-summary ul { padding-left: 1.25rem; margin-bottom: 1rem; }
.request-extra, .summary-extra { border-top: 1px solid rgb(31 111 127 / 18%); padding-top: 1rem; margin-top: 1rem; }
.draft-action { margin-top: 1rem; }
.summary-note { color: #446075; margin-top: 1.25rem; }
@media (max-width: 900px) { .request-wizard { grid-template-columns: minmax(0, 1fr); } .request-summary { position: static; } }
</style>
