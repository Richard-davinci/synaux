<script setup>
import {useForm} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, ref} from 'vue';
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
    challenge: selected.challenge ?? '',
    result: selected.result ?? '',
    year: selected.year ?? (selected.id ? null : new Date().getFullYear()),
    live_url: selected.live_url ?? '',
    is_public: !!selected.is_public,
    gallery: [],
});

// La categoría se toma del servicio elegido, también cuando se abre un trabajo ya guardado.
const category = computed(() => props.data.services.find(service => Number(service.database_id) === Number(work.service_id))?.category
    ?? (work.service_id === selected.service_id ? selected.category : null));

// El orden, los reemplazos y las bajas se envían junto con los datos del trabajo.
const galleryItems = ref((selected.images ?? []).map(image => ({...image, replacement: null})));
// La portada puede ser un archivo nuevo, una imagen guardada o la portada independiente anterior.
const savedCover = galleryItems.value.find(image => image.image_path === selected.cover_path);
const standaloneCover = Boolean(selected.cover_path && !savedCover);
const coverChoice = ref(savedCover ? 'saved:' + savedCover.id : standaloneCover ? 'current' : 'none');
const newImages = ref([]);

function resetCoverChoice() {
    const firstSaved = galleryItems.value[0];
    coverChoice.value = standaloneCover ? 'current'
        : firstSaved ? 'saved:' + firstSaved.id
            : newImages.value.length ? 'new:0' : 'none';
}

function clearUploadPreviews() {
    newImages.value.forEach(image => URL.revokeObjectURL(image.preview));
}

function selectUploads(files) {
    clearUploadPreviews();
    work.gallery = Array.from(files);
    newImages.value = work.gallery.map(file => ({name: file.name, preview: URL.createObjectURL(file)}));

    if (coverChoice.value.startsWith('new:') || coverChoice.value === 'none') {
        resetCoverChoice();
    }
}

function removeImage(index) {
    const [removed] = galleryItems.value.splice(index, 1);
    if (coverChoice.value === 'saved:' + removed.id) {
        resetCoverChoice();
    }
}

onBeforeUnmount(clearUploadPreviews);

work.transform(data => ({
    ...data,
    cover_choice: coverChoice.value,
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
                    <div class="field">
                        <span>Categoría del servicio</span>
                        <p class="work-category" aria-live="polite">{{ category || 'Seleccioná un servicio' }}</p>
                    </div>
                    <F v-model="work.year" label="Año" type="number" min="1990" max="2100"/>
                </div>
                <F v-model="work.description" label="Descripción breve" type="textarea" required/>
                <F v-model="work.challenge" label="El desafío" type="textarea"/>
                <F v-model="work.result" label="La solución y el resultado" type="textarea"/>
                <F v-model="work.live_url" type="url" label="Enlace al proyecto (opcional)"/>
                <section class="work-gallery" aria-labelledby="work-gallery-title">
                    <h2 id="work-gallery-title">Galería</h2>
                    <p>Marcá «Usar como portada» en una imagen para mostrarla en la tarjeta del portfolio.</p>
                    <div v-if="standaloneCover" class="work-gallery-item">
                        <img :src="selected.cover_path" alt="Portada actual" class="work-gallery-preview">
                        <label class="cover-option">
                            <input v-model="coverChoice" type="radio" name="portfolio-cover" value="current">
                            Conservar portada actual
                        </label>
                    </div>
                    <p v-if="work.errors.cover_choice" role="alert" class="gallery-error">{{ work.errors.cover_choice }}</p>
                    <p v-if="work.errors.gallery_items" role="alert" class="gallery-error">{{ work.errors.gallery_items }}</p>
                    <ol v-if="galleryItems.length" class="work-gallery-list">
                        <li v-for="(image, index) in galleryItems" :key="image.id" class="work-gallery-item">
                            <img :src="image.image_path" :alt="image.alt_text ?? ''" class="work-gallery-preview">
                            <div class="work-gallery-fields">
                                <label class="cover-option">
                                    <input v-model="coverChoice" type="radio" name="portfolio-cover" :value="'saved:' + image.id"
                                           :aria-label="'Usar como portada: imagen ' + (index + 1) + (image.alt_text ? ' · ' + image.alt_text : '')">
                                    Usar como portada
                                </label>
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
                                       @click="removeImage(index)">Quitar</B>
                                </div>
                            </div>
                        </li>
                    </ol>
                    <label class="field">
                        Agregar imágenes a la galería
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp"
                               aria-describedby="work-gallery-formats" @change="selectUploads($event.target.files)">
                        <small id="work-gallery-formats">JPG, PNG o WebP. Hasta 8 archivos de 5 MB cada uno.</small>
                    </label>
                    <ol v-if="newImages.length" class="work-gallery-list" aria-label="Imágenes nuevas">
                        <li v-for="(image, index) in newImages" :key="image.preview" class="work-gallery-item">
                            <img :src="image.preview" alt="" class="work-gallery-preview">
                            <div class="work-gallery-fields">
                                <p class="upload-name">{{ image.name }}</p>
                                <label class="cover-option">
                                    <input v-model="coverChoice" type="radio" name="portfolio-cover" :value="'new:' + index"
                                           :aria-label="'Usar como portada: ' + image.name">
                                    Usar como portada
                                </label>
                            </div>
                        </li>
                    </ol>
                </section>
                <label class="select-row"><input v-model="work.is_public" type="checkbox">Publicar este trabajo en el
                    portfolio</label>
                <B type="submit" :disabled="work.processing">Guardar trabajo</B>
            </form>
        </v-card>
    </section>
</template>

<style scoped>
/* La categoría se muestra como dato del catálogo, junto a la selección del servicio. */
.work-category {
    display: flex;
    align-items: center;
    min-height: 40px;
    padding: 8px 12px;
    color: #446075;
    background: #f5f9fa;
    border: 1px solid #1f6f7f26;
    border-radius: 8px;
}

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
    gap: 12px;
    margin-block: 16px;
    list-style: none;
    padding: 0;
}

.work-gallery-item {
    display: grid;
    grid-template-columns: 148px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
    padding: 16px;
    background: #f5f9fa;
    border: 1px solid #1f6f7f26;
    border-radius: 12px;
}

.work-gallery-item:has(.cover-option input:checked) {
    border-color: #1f6f7f66;
}

.work-gallery-preview {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: contain;
    background: #fff;
    border-radius: 8px;
}

/* Los campos usan su propio espaciado dentro de las tarjetas, y el archivo conserva el control nativo. */
.work-gallery-fields {
    display: grid;
    gap: 12px;
    min-width: 0;
}

.work-gallery .field {
    margin-bottom: 0;
}

.work-gallery input[type="file"] {
    max-width: 100%;
    padding: 4px 6px;
    font-size: .875rem;
}

.work-gallery input[type="file"]::file-selector-button {
    min-height: 34px;
    margin-inline-end: 8px;
    padding-inline: 10px;
    color: #153b64;
    background: #1f6f7f12;
    border: 0;
    border-radius: 6px;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.work-gallery input[type="file"]::file-selector-button:hover {
    background: #1f6f7f22;
}

.work-gallery-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

/* Un único control de radio identifica la portada entre las imágenes disponibles. */
.cover-option {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    max-width: 100%;
    min-height: 44px;
    padding: 6px 10px;
    cursor: pointer;
    color: #153b64;
    border: 1px solid transparent;
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 700;
    line-height: 1.3;
}

.cover-option:has(input:checked) {
    background: #2db3a31a;
    border-color: #1f6f7f40;
}

.cover-option input[type="radio"] {
    appearance: auto;
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
    min-height: 0;
    margin: 0;
    padding: 0;
    accent-color: #1f6f7f;
    cursor: pointer;
}

.upload-name {
    overflow-wrap: anywhere;
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
