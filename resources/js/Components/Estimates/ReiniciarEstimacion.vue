<script setup>
import {ref} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import SidePanel from '../UI/SidePanel.vue';
import {useSubmit} from '../../support/useSubmit';

// El mismo control confirma el descarte en la entrada pública y la vista de referencia.
defineProps({deshabilitado: Boolean});
const submit = useSubmit();
const abierto = ref(false);
const form = useForm({confirmed: true});

function reiniciar() {
    submit(form, '/estimador/reiniciar', {preserveState: false, preserveScroll: false, previewTarget: 'estimador-1'});
}
</script>

<template>
    <SidePanel v-model="abierto" title="Empezar de nuevo" max-width="440" :persistent="form.processing">
        <template #activator="{props: activador}">
            <B v-bind="activador" variant="ghost" :disabled="deshabilitado">Empezar de nuevo</B>
        </template>
        <section class="reiniciar-estimacion">
            <h2>¿Querés empezar de nuevo?</h2>
            <p>Se descartará lo que completaste en esta estimación y volverás al primer paso.</p>
            <p v-if="form.errors.confirmed" class="error" role="alert">{{ form.errors.confirmed }}</p>
            <div class="acciones-reinicio">
                <B variant="secondary" :disabled="form.processing" @click="abierto = false">Cancelar</B>
                <B :disabled="form.processing" @click="reiniciar">{{ form.processing ? 'Reiniciando…' : 'Descartar y reiniciar' }}</B>
            </div>
        </section>
    </SidePanel>
</template>

<style scoped>
/* Las acciones del panel mantienen espacio para envolver en pantallas angostas. */
.reiniciar-estimacion { padding: 24px; }
.reiniciar-estimacion h2 { margin: 0 0 12px; font-size: 1.25rem; }
.reiniciar-estimacion p { margin: 0 0 20px; color: #446075; }
.acciones-reinicio { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
