<script setup>
import {onBeforeUnmount, ref} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import OpcionPortada from './OpcionPortada.vue';

const props = defineProps({
    images: {type: Array, default: () => []},
    coverPath: String,
    revision: String,
    errors: {type: Object, required: true},
});

// La elección usa el identificador de la imagen guardada o el índice del archivo nuevo.
const galleryItems = ref(props.images.map(image => ({...image, replacement: null})));
const savedCover = galleryItems.value.find(image => image.image_path === props.coverPath);
const standaloneCover = Boolean(props.coverPath && !savedCover);
const coverChoice = ref(savedCover ? 'saved:' + savedCover.id : standaloneCover ? 'current' : 'none');
const newImages = ref([]);

function resetCoverChoice() {
    const firstSaved = galleryItems.value[0];
    coverChoice.value = standaloneCover ? 'current'
        : firstSaved ? 'saved:' + firstSaved.id
            : newImages.value.length ? 'new:0' : 'none';
}

// Las vistas previas solo viven mientras los archivos están elegidos en el formulario.
function clearUploadPreviews() {
    newImages.value.forEach(image => URL.revokeObjectURL(image.preview));
}

function selectUploads(files) {
    clearUploadPreviews();
    newImages.value = Array.from(files, file => ({file, preview: URL.createObjectURL(file)}));
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

function moveImage(index, direction) {
    const items = galleryItems.value;
    const destination = index + direction;
    [items[index], items[destination]] = [items[destination], items[index]];
}

onBeforeUnmount(clearUploadPreviews);

// El formulario principal guarda la galería junto con el trabajo en un solo envío.
function payload() {
    return {
        gallery: newImages.value.map(image => image.file),
        cover_choice: coverChoice.value,
        ...(props.revision ? {
            gallery_revision: props.revision,
            gallery_items: JSON.stringify(galleryItems.value.map(({id, alt_text}) => ({id, alt_text}))),
            gallery_replacements: Object.fromEntries(galleryItems.value
                .filter(image => image.replacement)
                .map(image => [image.id, image.replacement])),
        } : {}),
    };
}

defineExpose({payload});
</script>

<template>
    <section class="work-gallery" aria-labelledby="work-gallery-title">
        <h2 id="work-gallery-title">Galería</h2>
        <p>Marcá «Usar como portada» en una imagen para mostrarla en la tarjeta del portfolio.</p>
        <div v-if="standaloneCover" class="work-gallery-item">
            <img :src="coverPath" alt="Portada actual" class="work-gallery-preview">
            <OpcionPortada v-model="coverChoice" value="current" label="Conservar portada actual"/>
        </div>
        <p v-if="errors.cover_choice" role="alert" class="gallery-error">{{ errors.cover_choice }}</p>
        <p v-if="errors.gallery_items" role="alert" class="gallery-error">{{ errors.gallery_items }}</p>
        <ol v-if="galleryItems.length" class="work-gallery-list">
            <li v-for="(image, index) in galleryItems" :key="image.id" class="work-gallery-item">
                <img :src="image.image_path" :alt="image.alt_text ?? ''" class="work-gallery-preview">
                <div class="work-gallery-fields">
                    <OpcionPortada v-model="coverChoice" :value="'saved:' + image.id"
                                   :accessible-label="'Usar como portada: imagen ' + (index + 1) + (image.alt_text ? ' · ' + image.alt_text : '')"/>
                    <F v-model="image.alt_text" :label="`Texto alternativo de la imagen ${index + 1}`" :maxlength="255"
                       :error="errors[`gallery_items.${index}.alt_text`]"/>
                    <label class="field">
                        Reemplazar imagen {{ index + 1 }}
                        <input type="file" accept="image/jpeg,image/png,image/webp"
                               @change="image.replacement = $event.target.files[0] ?? null">
                    </label>
                    <p v-if="errors['gallery_replacements.' + image.id]" class="gallery-error" role="alert">
                        {{ errors['gallery_replacements.' + image.id] }}
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
                    <p class="upload-name">{{ image.file.name }}</p>
                    <OpcionPortada v-model="coverChoice" :value="'new:' + index"
                                   :accessible-label="'Usar como portada: ' + image.file.name"/>
                </div>
            </li>
        </ol>
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

.work-gallery-item:has(.cover-option[data-selected]) {
    border-color: #1f6f7f66;
}

.work-gallery-preview {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: contain;
    background: #fff;
    border-radius: 8px;
}

/* Los campos usan su propio espaciado dentro de las tarjetas. El selector de archivos usa el estilo global. */
.work-gallery-fields {
    display: grid;
    gap: 12px;
    min-width: 0;
}

.work-gallery .field {
    margin-bottom: 0;
}

.work-gallery-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
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
