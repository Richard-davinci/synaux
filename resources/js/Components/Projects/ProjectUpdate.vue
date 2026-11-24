<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {useForm} from '@inertiajs/vue3';
import {watch} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import {useSubmit} from '../../support/useSubmit';

// El formulario parte del proyecto elegido y conserva sus campos ante errores de validación.
const props = defineProps({project: Object});
const emit = defineEmits(['close']);
const submit = useSubmit();
const form = useForm({
    id: props.project.id, status: props.project.status, completion: props.project.progress,
    start_date: props.project.start_date ?? '', end_date: props.project.end_date ?? '',
    notes: props.project.notes ?? '', next_steps: props.project.next_steps ?? '',
});
// completion evita el nombre reservado de Inertia y se transforma al contrato del servidor.
form.transform(({completion, ...values}) => ({...values, progress: completion}));
watch(() => form.status, status => {
    if (status === 'completed') form.completion = 100;
    if (status === 'pending') form.completion = 0;
});

function save() {
    submit(form, 'proyecto', {previewTarget: 'g-proyectos', onSuccess: () => emit('close')});
}
</script>

<template>
    <SidePanel title="Seguimiento del proyecto" :model-value="true" :persistent="form.processing" max-width="560"
               @update:model-value="!$event && emit('close')">
        <div class="synaux-app">
            <v-card class="card modal-card project-update">
                <p class="eyebrow">Gestión · PRY-{{ project.id }}</p>
                <h2>Actualizar seguimiento</h2>
                <p><strong>{{ project.title }}</strong></p>
                <p>El cliente verá esta actualización y sus próximos pasos. Los avances anteriores quedan en el
                    historial.</p>
                <div v-if="Object.keys(form.errors).length" class="notice" role="alert">
                    <ul>
                        <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                    </ul>
                </div>
                <form @submit.prevent="save">
                    <div class="form-grid">
                        <F v-model="form.status" label="Estado del proyecto" type="select"
                           :options="[{value:'pending',label:'Pendiente de inicio'},{value:'in_progress',label:'En desarrollo'},{value:'completed',label:'Completado'},{value:'cancelled',label:'Cancelado'}]"
                           :error="form.errors.status" required/>
                        <F v-model="form.completion" label="Avance (%)" type="number" min="0" max="100" step="1"
                           :disabled="['pending', 'completed'].includes(form.status)" :error="form.errors.progress"
                           required/>
                    </div>
                    <p class="tracking-help">Pendiente corresponde a 0% y completado a 100%. El porcentaje representa el
                        avance informado por Gestión.</p>
                    <div class="form-grid">
                        <F v-model="form.start_date" label="Fecha de inicio (opcional)" type="date"
                           :error="form.errors.start_date"/>
                        <F v-model="form.end_date" label="Fecha de cierre (opcional)" type="date"
                           :min="form.start_date || undefined" :error="form.errors.end_date"/>
                    </div>
                    <F v-model="form.notes" label="Qué se avanzó" type="textarea" maxlength="10000"
                       hint="Describí los avances y los acuerdos que el cliente necesita conocer."
                       :error="form.errors.notes"/>
                    <F v-model="form.next_steps" label="Próximos pasos" type="textarea" maxlength="5000"
                       hint="Indicá las próximas tareas y si necesitás información o una revisión del cliente."
                       :error="form.errors.next_steps"/>
                    <div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="emit('close')">Cancelar</B><B
                        type="submit"
                        :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar actualización' }}</B>
                    </div>
                </form>
            </v-card>
        </div>
    </SidePanel>
</template>

<style scoped>
/* El diálogo permite recorrer todos los campos sin ocultar errores ni acciones en móvil. */
.project-update {
    max-height: 90vh;
    overflow-y: auto;
}

.project-update h2 {
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
}

.tracking-help {
    font-size: .9rem;
    color: #446075;
}

@media (max-width: 600px) {
    .form-actions {
        flex-wrap: wrap;
        gap: 12px;
    }

    .form-actions .btn {
        flex: 1 1 100%;
    }
}
</style>
