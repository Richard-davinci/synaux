<script setup>
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import { useSubmit } from '../../support/useSubmit';

// El editor de portfolio conserva su propio formulario de publicación y archivos.
const props = defineProps({ data: Object });
const selected = props.data.selected ?? {};
const submit = useSubmit();
const work = useForm({
    id: selected.id ?? null,
    service_id: selected.service_id ?? props.data.services[0]?.database_id,
    title: selected.title ?? '',
    description: selected.description ?? '',
    category: selected.category ?? 'Desarrollo web',
    challenge: selected.challenge ?? '',
    result: selected.result ?? '',
    year: selected.year ?? new Date().getFullYear(),
    live_url: selected.live_url ?? '',
    is_public: !!selected.is_public,
    cover: null,
    gallery: [],
});
</script>

<template>
    <section class="page shell narrow workspace-view workspace-work-editor">
        <B :href="data.area === 'gestion' ? '/gestion/portfolio' : '/profesional/portfolio'" variant="ghost">← Volver al portfolio</B>
        <T eyebrow="Portfolio" :title="selected.id ? 'Editar trabajo' : 'Nuevo trabajo'"
            description="Documentá el desafío, la solución y el resultado del proyecto." />
        <v-card class="card form-card">
            <form @submit.prevent="submit(work, 'trabajo', { forceFormData: true, previewTarget: 'g-portfolio' })">
                <F v-model="work.title" label="Título del trabajo" required />
                <div class="form-grid">
                    <F v-model="work.service_id" label="Servicio" type="select"
                        :options="data.services.map(service => ({ value: service.database_id, label: service.name }))" required />
                    <F v-model="work.category" label="Categoría" required />
                    <F v-model="work.year" label="Año" type="number" min="1990" max="2100" />
                </div>
                <F v-model="work.description" label="Descripción breve" type="textarea" required />
                <F v-model="work.challenge" label="El desafío" type="textarea" />
                <F v-model="work.result" label="La solución y el resultado" type="textarea" />
                <F v-model="work.live_url" type="url" label="Enlace al proyecto (opcional)" />
                <img v-if="selected.cover_path" :src="selected.cover_path" :alt="selected.title" class="upload-preview">
                <label class="field">
                    Imagen de portada (JPG, PNG o WebP · hasta 5 MB)
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="work.cover = $event.target.files[0]">
                </label>
                <label class="field">
                    Galería (hasta 8 imágenes por envío)
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="work.gallery = Array.from($event.target.files)">
                </label>
                <label class="select-row"><input v-model="work.is_public" type="checkbox">Publicar este trabajo en el portfolio</label>
                <B type="submit" :disabled="work.processing">Guardar trabajo</B>
            </form>
        </v-card>
    </section>
</template>
