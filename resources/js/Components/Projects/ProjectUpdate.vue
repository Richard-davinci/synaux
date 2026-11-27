<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {useForm} from '@inertiajs/vue3';
import {computed, watch} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import CampoFecha from '../UI/CampoFecha.vue';
import {useSubmit} from '../../support/useSubmit';

// El formulario parte del proyecto elegido y conserva sus campos ante errores de validación.
const props = defineProps({project: Object});
const tieneAnticipoPendiente = props.project.pagos?.some(pago => pago.concepto === 'sena' && pago.estado !== 'confirmado') ?? false;
const tieneChecklist = computed(() => (props.project.tareas?.length ?? 0) > 0);
const avanceChecklist = computed(() => {
    const tareas = props.project.tareas ?? [];
    return tareas.length ? Math.round(tareas.filter(tarea => Boolean(tarea.completed)).length * 100 / tareas.length) : 0;
});
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
                           :options="[{value:'pending',label:'Pendiente de inicio'},{value:'in_progress',label:tieneAnticipoPendiente ? 'En desarrollo · requiere anticipo confirmado' : 'En desarrollo'},{value:'completed',label:'Completado'},{value:'cancelled',label:'Cancelado'}]"
                           :error="form.errors.status" required/>
                        <div v-if="tieneChecklist" class="avance-derivado">
                            <span>Avance del checklist</span>
                            <strong>{{ avanceChecklist }}%</strong>
                            <small>Se calcula con las tareas completadas.</small>
                        </div>
                        <F v-else v-model="form.completion" label="Avance (%)" type="number" min="0" max="100" step="1"
                           :disabled="['pending', 'completed'].includes(form.status)" :error="form.errors.progress" required/>
                    </div>
                    <p v-if="!tieneChecklist" class="tracking-help">Pendiente corresponde a 0% y completado a 100%. Si el proyecto tiene checklist, el porcentaje se calcula automáticamente.</p>
                    <p v-else class="tracking-help">El avance no se edita aquí porque se calcula automáticamente desde el checklist.</p>
                    <p v-if="tieneAnticipoPendiente" class="tracking-help">El anticipo debe estar confirmado antes de
                        iniciar el proyecto.</p>
                    <div class="form-grid">
                        <CampoFecha v-model="form.start_date" label="Fecha de inicio (opcional)"
                                    :error="form.errors.start_date"/>
                        <CampoFecha v-model="form.end_date" label="Fecha de cierre (opcional)"
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

.avance-derivado {
    display: grid;
    align-content: center;
    gap: 3px;
    padding: 10px 14px;
    border: 1px solid #d5e5e9;
    border-radius: 10px;
    background: #f5f9fa;
}

.avance-derivado span, .avance-derivado small {
    color: #446075;
    font-size: .875rem;
}

.avance-derivado strong {
    color: #153b64;
    font-size: 1.25rem;
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
