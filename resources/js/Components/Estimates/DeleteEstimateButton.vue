<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {ref, useId} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import {demoNotice} from '../../support/prototype';

// La confirmación identifica el registro y solo envía el borrado cuando el cliente lo acepta explícitamente.
const props = defineProps({estimate: Object, compact: Boolean});
const page = usePage();
const open = ref(false);
const titleId = useId();
const form = useForm({confirmed: false});

function ask() {
    form.reset();
    form.clearErrors();
    open.value = true;
}

function close() {
    if (!form.processing) open.value = false;
}

function remove() {
    if (page.props.preview) {
        demoNotice.value = 'Vista de referencia: no se eliminan estimaciones reales.';
        close();
        return;
    }
    form.confirmed = true;
    form.delete(`/estimaciones/${props.estimate.id}`, {
        preserveScroll: true, onSuccess: () => {
            open.value = false;
        }
    });
}
</script>
<template>
    <B variant="ghost" :compact="compact" :aria-label="'Eliminar estimación EST-' + estimate.id" @click="ask">Eliminar</B>
    <SidePanel title="Eliminar estimación" :model-value="open" max-width="440" :persistent="form.processing"
               @update:model-value="value => { if (!value) close(); }" :aria-labelledby="titleId">
        <div class="synaux-app">
            <v-card class="card modal-card">
                <h2 :id="titleId">¿Eliminar esta estimación?</h2>
                <p><strong>EST-{{ estimate.id }} · {{ estimate.summary }}</strong></p>
                <p>Se quitará de Mis estimaciones. Las solicitudes y los presupuestos vinculados conservarán su
                    información.</p>
                <p v-for="(error, field) in form.errors" :key="field" class="error" role="alert">{{ error }}</p>
                <div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="close">Cancelar</B><B
                    :disabled="form.processing"
                    @click="remove">{{ form.processing ? 'Eliminando…' : 'Eliminar estimación' }}</B></div>
            </v-card>
        </div>
    </SidePanel>
</template>
