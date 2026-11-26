<script setup>
import {useForm, router} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';

// La lista muestra las tareas del cliente y permite cambios solo al equipo asignado o a Gestión.
const props = defineProps({proyectoId: Number, tareas: Array, editable: Boolean});
const nueva = useForm({title: ''});

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
            <div><p class="eyebrow">PLAN DE TRABAJO</p><h2>Tareas del proyecto</h2></div>
            <strong v-if="tareas.length">{{ tareas.filter(tarea => tarea.completed).length }}/{{ tareas.length }}</strong>
        </div>
        <ul v-if="tareas.length" class="lista-tareas">
            <li v-for="tarea in tareas" :key="tarea.id">
                <label>
                    <input type="checkbox" :checked="Boolean(tarea.completed)" :disabled="!editable"
                           @change="cambiar(tarea)"/>
                    <span :class="{'tarea-completa': tarea.completed}">{{ tarea.title }}</span>
                </label>
            </li>
        </ul>
        <p v-else class="ayuda-tareas">Todavía no hay tareas. El responsable puede agregarlas a medida que avanza el trabajo.</p>
        <form v-if="editable" class="agregar-tarea" @submit.prevent="agregar">
            <F v-model="nueva.title" label="Nueva tarea" placeholder="Ej.: Aprobar el diseño de inicio"
               :maxlength="180" :error="nueva.errors.title" required/>
            <B compact type="submit" :disabled="nueva.processing || !nueva.title.trim()">Agregar tarea</B>
        </form>
    </v-card>
</template>

<style scoped>
/* Las tareas se leen como una lista breve y los cambios conservan el color accesible del sitio. */
.tareas-proyecto { display: grid; gap: 16px; }
.tareas-titulo { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.tareas-titulo h2, .tareas-titulo p { margin: 0; }
.lista-tareas { display: grid; gap: 8px; list-style: none; margin: 0; padding: 0; }
.lista-tareas label { display: flex; align-items: flex-start; gap: 10px; }
.lista-tareas input { accent-color: #1f6f7f; margin-top: 5px; }
.tarea-completa { color: #446075; text-decoration: line-through; }
.ayuda-tareas { margin: 0; color: #446075; }
.agregar-tarea { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 12px; }
@media (max-width: 600px) { .agregar-tarea { grid-template-columns: 1fr; } }
</style>
