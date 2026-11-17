<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import B from './SynauxButton.vue';
import F from './SynauxField.vue';
import { useSubmit } from '../support/synaux';
// El mismo formulario permite crear, revisar y editar exclusivamente solicitudes propias en borrador.
const props = defineProps({ data: Object });
const page = usePage();
const old = props.data.selected?.status === 'draft' ? props.data.selected : null;
const estimate = props.data.selectedEstimate;
const review = ref(props.data.referenceKey === 'publicar-revision');
const submit = useSubmit();
const form = useForm({ id: old?.id ?? null, estimate_id: estimate?.id ?? old?.estimate_id ?? null, service_id: estimate?.service_id ?? old?.service_id ?? props.data.services[0]?.database_id,
    title: old?.title ?? '', description: old?.description ?? '', category: old?.category ?? 'Desarrollo web', skills: old?.skills?.join(', ') ?? '',
    visibility: page.props.stageTwo ? (old?.visibility ?? 'private') : 'private', budget_reference: old?.budget_reference ?? '', desired_deadline: old?.desired_deadline ?? '', publish: false });
// La categoría sigue al servicio elegido, también en solicitudes creadas desde una estimación.
const selectedService = computed(() => props.data.services.find(service => service.database_id == form.service_id));
const categories = computed(() => [...new Set(props.data.services.map(service => service.category))]);
watch(selectedService, (service) => {
    if (service) form.category = service.category;
}, { immediate: true });

// Regresa al formulario cuando el servidor rechaza un campo y conserva los valores escritos.
function save(publish) {
    form.publish = publish;
    submit(form, 'solicitud', {
        previewTarget: publish ? 'mis-publicaciones' : 'solicitudes',
        onError: () => { review.value = false; },
    });
}
</script>
<template>
    <!-- La revisión permite confirmar la visibilidad antes de exponer una publicación en el muro. -->
    <div class="detail-layout"><v-card class="card form-card"><h2>{{ review ? 'Revisá tu solicitud' : old ? 'Editar borrador' : 'Contanos qué necesitás' }}</h2>
        <div v-if="Object.keys(form.errors).length" class="notice" role="alert"><p>Revisá los campos indicados antes de guardar.</p><ul><li v-for="(error, field) in form.errors" :key="field">{{ error }}</li></ul></div>
        <form v-if="!review" @submit.prevent="review = true"><F v-model="form.title" label="Título del proyecto" placeholder="Ej.: Renovación web para mi estudio" required minlength="5" maxlength="255" :error="form.errors.title" /><F v-model="form.service_id" label="Servicio" type="select" :disabled="!!form.estimate_id" :error="form.errors.service_id" :options="data.services.map(s => ({value:s.database_id,label:s.name}))" required /><F v-model="form.description" type="textarea" label="Descripción y alcance" hint="Contá el objetivo, lo que ya tenés y qué resultado esperás." required minlength="10" maxlength="10000" :error="form.errors.description" /><div class="form-grid"><F v-model="form.category" label="Categoría" type="select" :options="categories" disabled :error="form.errors.category" /><F v-model="form.skills" :error="form.errors.skills" label="Habilidades (separadas por comas)" /></div><div class="form-grid"><F v-model="form.budget_reference" :error="form.errors.budget_reference" label="Presupuesto de referencia (opcional)" /><F v-model="form.desired_deadline" :error="form.errors.desired_deadline" label="Plazo deseado (opcional)" /></div><p v-if="!page.props.stageTwo">Tu solicitud se enviará de forma privada a Synaux.</p><F v-else v-model="form.visibility" label="Visibilidad" type="select" :error="form.errors.visibility" :options="[{value:'private',label:'Privada · revisión de Synaux'},{value:'public',label:'Pública · visible en el muro'}]" /><div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="save(false)">Guardar borrador</B><B type="submit" arrow>Revisar solicitud</B></div></form>
        <template v-else><h3>{{ form.title || 'Tu proyecto' }}</h3><p class="preserve-lines">{{ form.description }}</p><dl class="data-list"><div><dt>Servicio</dt><dd>{{ data.services.find(s => s.database_id == form.service_id)?.name }}</dd></div><div><dt>Visibilidad</dt><dd>{{ form.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div><div><dt>Plazo</dt><dd>{{ form.desired_deadline || 'A coordinar' }}</dd></div><div><dt>Referencia</dt><dd>{{ form.budget_reference || 'A definir' }}</dd></div></dl><div class="tags"><span v-for="tag in form.skills.split(',').filter(Boolean)" :key="tag">{{ tag }}</span></div><div class="form-actions"><B variant="secondary" @click="review = false">Volver a editar</B><B :disabled="form.processing" @click="save(true)">{{ form.visibility === 'public' ? 'Publicar solicitud' : 'Enviar solicitud' }}</B></div></template>
    </v-card><aside><v-card class="card soft-panel"><p class="eyebrow">Antes de enviar</p><h3>Un buen punto de partida</h3><p>Una descripción clara ayuda a Synaux a revisar tu pedido y preparar el presupuesto.</p><p>El presupuesto formal llega después de revisar el alcance. Publicar o enviar una solicitud no implica contratar.</p><p v-if="estimate">Incluimos las respuestas de tu estimación guardada.</p></v-card></aside></div>
</template>
