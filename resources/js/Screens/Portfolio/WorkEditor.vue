<script setup>
import {useForm} from '@inertiajs/vue3';
import {ref} from 'vue';
import EditorGaleria from '../../Components/Portfolio/EditorGaleria.vue';
import ServicePicker from '../../Components/Catalog/ServicePicker.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {useSubmit} from '../../support/useSubmit';

// El editor de portfolio conserva su propio formulario de publicación y archivos.
const props = defineProps({data: Object});
const selected = props.data.selected ?? {};
const submit = useSubmit();
const work = useForm({
    id: selected.id ?? null,
    service_id: selected.service_id ?? null,
    title: selected.title ?? '',
    description: selected.description ?? '',
    challenge: selected.challenge ?? '',
    result: selected.result ?? '',
    year: selected.year ?? (selected.id ? null : new Date().getFullYear()),
    live_url: selected.live_url ?? '',
    is_public: !!selected.is_public,
});

const galleryEditor = ref(null);

function saveWork() {
    work.transform(values => ({...values, ...galleryEditor.value.payload()}));
    submit(work, 'trabajo', {forceFormData: true, preserveState: 'errors', previewTarget: 'g-portfolio'});
}
</script>

<template>
    <section class="page shell narrow workspace-view workspace-work-editor">
        <B :href="data.area === 'gestion' ? '/gestion/portfolio' : '/profesional/portfolio'" variant="ghost">← Volver al
            portfolio</B>
        <T eyebrow="Portfolio" :title="selected.id ? 'Editar trabajo' : 'Nuevo trabajo'"
           description="Documentá el desafío, la solución y el resultado del proyecto."/>
        <v-card class="card form-card">
            <form @submit.prevent="saveWork">
                <F v-model="work.title" label="Título del trabajo" required/>
                <ServicePicker v-model="work.service_id" :services="data.services" label="Servicio"
                               :error="work.errors.service_id"/>
                <F class="work-year" v-model="work.year" label="Año" type="number" min="1990" max="2100"/>
                <F v-model="work.description" label="Descripción breve" type="textarea" required/>
                <F v-model="work.challenge" label="El desafío" type="textarea"/>
                <F v-model="work.result" label="La solución y el resultado" type="textarea"/>
                <F v-model="work.live_url" type="url" label="Enlace al proyecto (opcional)"/>
                <EditorGaleria ref="galleryEditor" :images="selected.images" :cover-path="selected.cover_path"
                               :revision="data.galleryRevision" :errors="work.errors"/>
                <label class="select-row"><input v-model="work.is_public" type="checkbox">Publicar este trabajo en el
                    portfolio</label>
                <B type="submit" :disabled="work.processing">Guardar trabajo</B>
            </form>
        </v-card>
    </section>
</template>

<style scoped>
/* El año conserva un ancho acorde a sus cuatro dígitos. */
.work-year {
    max-width: 170px;
}
</style>
