<script setup>
import {router, useForm} from '@inertiajs/vue3';
import {computed} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';

// La lista muestra las tareas del cliente y permite cambios solo al equipo asignado o a Gestión.
const props = defineProps({proyectoId: Number, tareas: Array, editable: Boolean, estado: String});
const etapas = ['Planificación', 'Diseño', 'Desarrollo', 'Revisión', 'Entrega', 'Ejecución', 'Otros'];
const nueva = useForm({title: '', etapa: 'Desarrollo'});
const tareasPorEtapa = computed(() => etapas
    .map(etapa => ({etapa, tareas: props.tareas.filter(tarea => (tarea.etapa || 'Ejecución') === etapa)}))
    .filter(grupo => grupo.tareas.length));
const tareasCompletadas = computed(() => props.tareas.filter(tarea => Boolean(tarea.completed)).length);
const porcentaje = computed(() => props.tareas.length
    ? Math.round(tareasCompletadas.value * 100 / props.tareas.length) : 0);
const tareaEstadoNoIniciado = computed(() => props.estado !== 'in_progress');

// Cada tarea queda en una etapa para que el plan sea fácil de recorrer y el avance tenga una base común.
function agregar() {
    nueva.post('/proyectos/' + props.proyectoId + '/tareas', {
        preserveScroll: true,
        onSuccess: () => nueva.reset(),
    });
}

function cambiar(tarea) {
    router.patch('/proyectos/' + props.proyectoId + '/tareas/' + tarea.id, {}, {preserveScroll: true});
}
</script>

<template>
    <v-card class="card tareas-proyecto">
        <div class="tareas-titulo">
            <div><p class="eyebrow">PLAN DE TRABAJO</p>
                <h2>Checklist del proyecto</h2>
                <p class="ayuda-tareas">El avance se calcula según las tareas completadas.</p></div>
            <strong v-if="tareas.length" class="resumen-checklist">{{ tareasCompletadas }}/{{ tareas.length }} · {{ porcentaje }}%</strong>
        </div>
        <div v-if="tareas.length" class="avance-checklist" role="progressbar" :aria-valuenow="porcentaje" aria-valuemin="0" aria-valuemax="100" :aria-label="`Checklist completado: ${porcentaje}%`">
            <span :style="{width: porcentaje + '%'}"></span>
        </div>
        <div v-if="tareas.length" class="grupos-tareas">
            <section v-for="grupo in tareasPorEtapa" :key="grupo.etapa" class="grupo-tarea">
                <h3>{{ grupo.etapa }} <span>{{ grupo.tareas.filter(tarea => tarea.completed).length }}/{{ grupo.tareas.length }}</span></h3>
                <ul class="lista-tareas">
                    <li v-for="tarea in grupo.tareas" :key="tarea.id">
                        <label>
                            <input type="checkbox" :checked="Boolean(tarea.completed)" :disabled="!editable || tareaEstadoNoIniciado"
                                   @change="cambiar(tarea)"/>
                            <span :class="{'tarea-completa': tarea.completed}">{{ tarea.title }}</span>
                        </label>
                        <small v-if="tarea.completed && tarea.completada_por">Completada por {{ tarea.completada_por }}</small>
                    </li>
                </ul>
            </section>
        </div>
        <p v-else class="ayuda-tareas">Todavía no hay tareas. Agregá las etapas y tareas acordadas para este proyecto; al completarlas se actualizará el avance.</p>
        <p v-if="editable && tareaEstadoNoIniciado" class="ayuda-tareas">Podés preparar el checklist antes de iniciar. Las tareas se marcan al comenzar el trabajo.</p>
        <p v-else-if="editable" class="ayuda-tareas">El checklist se puede actualizar mientras el proyecto está en desarrollo.</p>
        <form v-if="editable" class="agregar-tarea" @submit.prevent="agregar">
            <F v-model="nueva.etapa" label="Etapa" type="select" :options="etapas.map(etapa => ({value: etapa, label: etapa}))"
               :error="nueva.errors.etapa" required/>
            <F v-model="nueva.title" label="Nueva tarea" placeholder="Ej.: Aprobar el diseño de inicio"
               :maxlength="180" :error="nueva.errors.title" required/>
            <B compact type="submit" :disabled="nueva.processing || !nueva.title.trim()">Agregar tarea</B>
        </form>
    </v-card>
</template>

<style scoped>
/* Las tareas se leen como una lista breve y los cambios conservan el color accesible del sitio. */
.tareas-proyecto {
    display: grid;
    gap: 16px;
}

.tareas-titulo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.tareas-titulo h2, .tareas-titulo p {
    margin: 0;
}

.tareas-titulo .ayuda-tareas {
    margin-top: 4px;
}

.resumen-checklist {
    flex: 0 0 auto;
    color: #153b64;
    font-variant-numeric: tabular-nums;
}

.avance-checklist {
    height: 9px;
    overflow: hidden;
    border-radius: 99px;
    background: #e5eef1;
}

.avance-checklist span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #2db3a3;
    transition: width 180ms ease;
}

.grupos-tareas {
    display: grid;
    gap: 14px;
}

.grupo-tarea {
    padding: 14px 16px;
    border: 1px solid #d5e5e9;
    border-radius: 12px;
    background: #f5f9fa;
}

.grupo-tarea h3 {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 10px;
    color: #153b64;
    font-size: 1rem;
}

.grupo-tarea h3 span, .grupo-tarea li small {
    color: #446075;
    font-size: .875rem;
    font-weight: 500;
}

.lista-tareas {
    display: grid;
    gap: 8px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.lista-tareas label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.lista-tareas li {
    display: grid;
    gap: 3px;
}

.lista-tareas input {
    accent-color: #1f6f7f;
    margin-top: 5px;
}

.tarea-completa {
    color: #446075;
    text-decoration: line-through;
}

.ayuda-tareas {
    margin: 0;
    color: #446075;
}

.agregar-tarea {
    display: grid;
    grid-template-columns: minmax(150px, .7fr) minmax(0, 1.5fr) auto;
    align-items: end;
    gap: 12px;
}

@media (prefers-reduced-motion: reduce) {
    .avance-checklist span { transition: none; }
}

@media (max-width: 600px) {
    .agregar-tarea {
        grid-template-columns: 1fr;
    }

    .agregar-tarea :deep(.btn) {
        width: 100%;
    }
}
</style>
