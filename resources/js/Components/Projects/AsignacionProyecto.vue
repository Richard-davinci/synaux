<script setup>
import {ref, useId} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import SidePanel from '../UI/SidePanel.vue';

const props = defineProps({proyectoId: Number, asignacion: Object, usuarioId: Number});
const abierto = ref(false);
const formularioId = useId();
const form = useForm({responsable_id: ''});

// Ambas acciones usan la misma validación. Reasignar mantiene a quien supervisa el proyecto.
function asignar(id) {
    form.responsable_id = id;
    form.patch('/proyectos/' + props.proyectoId + '/responsable', {
        preserveScroll: true, onSuccess: () => { abierto.value = false; },
    });
}

function elegirProfesional() {
    form.resetAndClearErrors();
    abierto.value = true;
}
</script>

<template>
    <v-card class="card asignacion-proyecto">
        <h2>Equipo del proyecto</h2>
        <dl class="data-list">
            <div><dt>Seguimiento</dt><dd>{{ asignacion.supervisor || 'Por definir' }}</dd></div>
            <div><dt>Responsable de ejecución</dt><dd>{{ asignacion.responsable?.name || 'Sin asignar' }}</dd></div>
        </dl>
        <template v-if="asignacion.editable">
            <div class="acciones-equipo">
                <B compact variant="secondary" :disabled="form.processing || asignacion.responsable?.id === usuarioId"
                   @click="asignar(usuarioId)">Asignármelo</B>
                <B v-if="asignacion.profesionales.length" compact :disabled="form.processing" @click="elegirProfesional">
                    Asignar a un profesional interno
                </B>
            </div>
            <p v-if="!asignacion.profesionales.length">Todavía no hay profesionales internos con cuenta activa.</p>
            <p v-if="!abierto && form.errors.responsable_id" class="error" role="alert">{{ form.errors.responsable_id }}</p>
        </template>
    </v-card>

    <SidePanel v-model="abierto" title="Asignar responsable" :persistent="form.processing" max-width="480">
        <form :id="formularioId" class="formulario-asignacion" @submit.prevent="asignar(form.responsable_id)">
            <p>La persona elegida recibe un aviso y puede consultar el proyecto desde su panel.</p>
            <F v-model="form.responsable_id" label="Profesional interno" type="select" :options="asignacion.profesionales"
               :error="form.errors.responsable_id" required/>
        </form>
        <template #footer>
            <B variant="secondary" :disabled="form.processing" @click="abierto = false">Cancelar</B>
            <B type="submit" :form="formularioId"
               :disabled="form.processing || !form.responsable_id || form.responsable_id === asignacion.responsable?.id">
                {{ form.processing ? 'Guardando…' : 'Guardar asignación' }}
            </B>
        </template>
    </SidePanel>
</template>

<style scoped>
/* La ficha mantiene juntas las acciones y el panel alinea sus campos con el encabezado. */
.asignacion-proyecto { display: grid; gap: 12px; min-width: 0; }
.acciones-equipo { display: flex; flex-wrap: wrap; gap: 8px; }
.formulario-asignacion { display: grid; gap: 16px; padding: 20px clamp(16px, 4vw, 24px); }
.formulario-asignacion p { margin: 0; }
</style>
