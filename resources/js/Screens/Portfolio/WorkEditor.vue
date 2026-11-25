<script setup>
import {useForm} from '@inertiajs/vue3';
import {ref} from 'vue';
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
    service_id: selected.service_id ?? props.data.services[0]?.database_id,
    title: selected.title ?? '',
    description: selected.description ?? '',
    category: selected.category ?? 'Desarrollo web',
    challenge: selected.challenge ?? '',
    result: selected.result ?? '',
    year: selected.year ?? (selected.id ? null : new Date().getFullYear()),
    live_url: selected.live_url ?? '',
    is_public: !!selected.is_public,
    cover: null,
    gallery: [],
});

// El orden, los reemplazos y las bajas se envían junto con los datos del trabajo.
const galleryItems = ref((selected.images ?? []).map(image => ({...image, replacement: null})));
work.transform(data => ({
    ...data,
    ...(props.data.galleryRevision ? {
        gallery_revision: props.data.galleryRevision,
        gallery_items: JSON.stringify(galleryItems.value.map(({id, alt_text}) => ({id, alt_text}))),
        gallery_replacements: Object.fromEntries(galleryItems.value
            .filter(image => image.replacement)
            .map(image => [image.id, image.replacement])),
    } : {}),
}));

function moveImage(index, direction) {
    const items = galleryItems.value;
    const destination = index + direction;
    [items[index], items[destination]] = [items[destination], items[index]];
}

function saveWork() {
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
                <div class="form-grid">
                    <F v-model="work.service_id" label="Servicio" type="select"
                       :options="data.services.map(service => ({ value: service.database_id, label: service.name }))"
                       required/>
                    <F v-model="work.category" label="Categoría" required/>
                    <F v-model="work.year" label="Año" type="number" min="1990" max="2100"/>
                </div>
                <F v-model="work.description" label="Descripción breve" type="textarea" required/>
                <F v-model="work.challenge" label="El desafío" type="textarea"/>
                <F v-model="work.result" label="La solución y el resultado" type="textarea"/>
                <F v-model="work.live_url" type="url" label="Enlace al proyecto (opcional)"/>
                <img v-if="selected.cover_path" :src="selected.cover_path" :alt="selected.title" class="upload-preview">
                <label class="field">
                    Imagen de portada (JPG, PNG o WebP · hasta 5 MB)
                    <input type="file" accept="image/jpeg,image/png,image/webp"
                           @change="work.cover = $event.target.files[0]">
                </label>
                <section class="work-gallery" aria-labelledby="work-gallery-title">
                    <h2 id="work-gallery-title">Galería</h2>
                    <p v-if="selected.id">Podés ordenar, reemplazar o quitar imágenes. Los cambios se aplican al guardar.</p>
                    <p v-if="work.errors.gallery_items" role="alert" class="gallery-error">{{ work.errors.gallery_items }}</p>
                    <ol v-if="galleryItems.length" class="work-gallery-list">
                        <li v-for="(image, index) in galleryItems" :key="image.id" class="work-gallery-item">
                            <img :src="image.image_path" :alt="image.alt_text ?? ''" class="work-gallery-preview">
                            <div class="work-gallery-fields">
                                <F v-model="image.alt_text" :label="`Texto alternativo de la imagen ${index + 1}`" :maxlength="255"
                                   :error="work.errors[`gallery_items.${index}.alt_text`]"/>
                                <label class="field">
                                    Reemplazar imagen {{ index + 1 }}
                                    <input type="file" accept="image/jpeg,image/png,image/webp"
                                           @change="image.replacement = $event.target.files[0] ?? null">
                                </label>
                                <p v-if="work.errors['gallery_replacements.' + image.id]" class="gallery-error" role="alert">
                                    {{ work.errors['gallery_replacements.' + image.id] }}
                                </p>
                                <div class="work-gallery-actions">
                                    <B compact variant="secondary" :disabled="index === 0"
                                       :aria-label="`Subir imagen ${index + 1}`" @click="moveImage(index, -1)">Subir</B>
                                    <B compact variant="secondary" :disabled="index === galleryItems.length - 1"
                                       :aria-label="`Bajar imagen ${index + 1}`" @click="moveImage(index, 1)">Bajar</B>
                                    <B compact variant="ghost" :aria-label="`Quitar imagen ${index + 1}`"
                                       @click="galleryItems.splice(index, 1)">Quitar</B>
                                </div>
                            </div>
                        </li>
                    </ol>
                    <label class="field">
                        Agregar imágenes a la galería (hasta 8 por envío)
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp"
                               @change="work.gallery = Array.from($event.target.files)">
                    </label>
                </section>
                <label class="select-row"><input v-model="work.is_public" type="checkbox">Publicar este trabajo en el
                    portfolio</label>
                <B type="submit" :disabled="work.processing">Guardar trabajo</B>
            </form>
        </v-card>
    </section>
</template>

<style scoped>
/* Cada imagen se vincula con sus controles y se apila en pantallas pequeñas. */
.work-gallery {
    border-top: 1px solid #1f6f7f26;
    padding-top: 16px;
}

.work-gallery > p {
    margin-block: 8px 16px;
}

.work-gallery-list {
    display: grid;
    gap: 10px;
    margin-block: 16px;
    list-style: none;
    padding: 0;
}

.work-gallery-item {
    display: grid;
    grid-template-columns: 136px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
    padding: 12px;
    background: #f5f9fa;
    border: 1px solid #1f6f7f26;
    border-radius: 12px;
}

.work-gallery-preview {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: contain;
    background: #fff;
    border-radius: 8px;
}

.work-gallery-fields {
    min-width: 0;
}

.work-gallery-fields input[type="file"] {
    max-width: 100%;
}

.work-gallery-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.gallery-error {
    color: rgb(var(--v-theme-error));
}

@media (max-width: 540px) {
    .work-gallery-item {
        grid-template-columns: minmax(0, 1fr);
    }

    .work-gallery-preview {
        width: min(100%, 180px);
    }
}
</style>
