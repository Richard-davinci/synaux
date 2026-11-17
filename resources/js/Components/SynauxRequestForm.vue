<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from './SynauxButton.vue';
import F from './SynauxField.vue';
import { useSubmit } from '../support/synaux';
// El mismo formulario permite crear, revisar y editar exclusivamente solicitudes propias en borrador.
const props = defineProps({ data: Object });
const old = props.data.selected?.status === 'draft' ? props.data.selected : null;
const estimate = props.data.selectedEstimate;
const review = ref(props.data.referenceKey === 'publicar-revision');
const submit = useSubmit();
const form = useForm({ id: old?.id ?? null, estimate_id: estimate?.id ?? old?.estimate_id ?? null, service_id: estimate?.service_id ?? old?.service_id ?? props.data.services[0]?.database_id,
    title: old?.title ?? '', description: old?.description ?? '', category: old?.category ?? 'Desarrollo web', skills: old?.skills?.join(', ') ?? '',
    visibility: old?.visibility ?? 'private', budget_reference: old?.budget_reference ?? '', desired_deadline: old?.desired_deadline ?? '', publish: false });
function save(publish) { form.publish = publish; submit(form, 'solicitud', { previewTarget: publish ? 'mis-publicaciones' : 'solicitudes' }); }
</script>
<template>
    <!-- La revisión permite confirmar la visibilidad antes de exponer una publicación en el muro. -->
    <div class="detail-layout"><v-card class="card form-card"><h2>{{ review ? 'Revisá tu solicitud' : old ? 'Editar borrador' : 'Contanos qué necesitás' }}</h2>
        <form v-if="!review" @submit.prevent="review = true"><F v-model="form.title" label="Título del proyecto" placeholder="Ej.: Renovación web para mi estudio" required minlength="5" :error="form.errors.title" /><F v-model="form.service_id" label="Servicio" type="select" :disabled="!!estimate" :options="data.services.map(s => ({value:s.database_id,label:s.name}))" required /><F v-model="form.description" type="textarea" label="Descripción y alcance" hint="Contá el objetivo, lo que ya tenés y qué resultado esperás." required :error="form.errors.description" /><div class="form-grid"><F v-model="form.category" label="Categoría" type="select" :options="['Desarrollo web','Diseño','Marketing digital']" /><F v-model="form.skills" label="Habilidades (separadas por comas)" /></div><div class="form-grid"><F v-model="form.budget_reference" label="Presupuesto de referencia (opcional)" /><F v-model="form.desired_deadline" label="Plazo deseado (opcional)" /></div><F v-model="form.visibility" label="Visibilidad" type="select" :options="[{value:'private',label:'Privada · revisión e invitaciones'},{value:'public',label:'Pública · visible en el muro'}]" /><div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="save(false)">Guardar borrador</B><B type="submit" arrow>Revisar solicitud</B></div></form>
        <template v-else><h3>{{ form.title || 'Renovación web para Estudio Norte' }}</h3><p class="preserve-lines">{{ form.description }}</p><dl class="data-list"><div><dt>Servicio</dt><dd>{{ data.services.find(s => s.database_id == form.service_id)?.name }}</dd></div><div><dt>Visibilidad</dt><dd>{{ form.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div><div><dt>Plazo</dt><dd>{{ form.desired_deadline || 'A coordinar' }}</dd></div><div><dt>Referencia</dt><dd>{{ form.budget_reference || 'A definir' }}</dd></div></dl><div class="tags"><span v-for="tag in form.skills.split(',').filter(Boolean)" :key="tag">{{ tag }}</span></div><div class="form-actions"><B variant="secondary" @click="review = false">Volver a editar</B><B :disabled="form.processing" @click="save(true)">{{ form.visibility === 'public' ? 'Publicar solicitud' : 'Enviar solicitud' }}</B></div></template>
    </v-card><aside><v-card class="card soft-panel"><p class="eyebrow">Antes de enviar</p><h3>Un buen punto de partida</h3><p>Una descripción clara ayuda a recibir propuestas acordes a tu proyecto.</p><p>El presupuesto formal llega después de revisar el alcance. Publicar o enviar una solicitud no implica contratar.</p><p v-if="estimate">Incluimos las respuestas de tu estimación guardada.</p></v-card></aside></div>
</template>
