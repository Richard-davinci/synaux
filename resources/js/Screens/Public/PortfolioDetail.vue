<script setup>
import './public-screen.css';
import {computed, ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';

const props = defineProps({data: Object});

// Usa los datos del trabajo y el enlace externo guardado desde Gestión.
const selected = computed(() => props.data.selected ?? {});
const siteLabel = computed(() => selected.value.live_url?.startsWith('https://stitch.google.com/preview/')
    ? 'Ver sitio'
    : 'Visitar proyecto');
const coverLabel = computed(() => 'Portada de ' + selected.value.title);

// El visor conserva la imagen completa y devuelve el foco al control que lo abrió.
const imageOpen = ref(false);
const activeImage = ref(null);
let imageTrigger;

function showImage(path, label, event) {
    imageTrigger = event.currentTarget;
    activeImage.value = {path, label};
    imageOpen.value = true;
}

function restoreImageFocus() {
    imageTrigger?.focus();
}
</script>

<template>
    <section data-public-screen class="page shell public-editorial public-typography">
        <B href="/portfolio" variant="ghost" compact class="project-back">← Volver al portfolio</B>
        <T class="public-page-heading case-heading" :eyebrow="selected.category" :title="selected.title"
           :description="selected.description"/>
        <div v-if="selected.live_url || selected.year" class="case-links">
            <B v-if="selected.live_url" :href="selected.live_url" arrow target="_blank" rel="noopener">
                {{ siteLabel }}
            </B>
            <span v-if="selected.year" class="case-year">Año {{ selected.year }}</span>
        </div>

        <!-- El marco acompaña la proporción de la imagen; el botón abre el visor con la captura completa. -->
        <figure v-if="selected.cover_path" class="case-cover">
            <button type="button" class="case-image" :aria-label="'Ampliar ' + coverLabel.toLowerCase()"
                    @click="showImage(selected.cover_path, coverLabel, $event)">
                <img :src="selected.cover_path" :alt="coverLabel">
            </button>
            <figcaption>
                <span>{{ coverLabel }}</span>
                <span aria-hidden="true">Ampliar imagen</span>
            </figcaption>
        </figure>

        <div class="case-story">
            <section v-if="selected.challenge && selected.challenge !== selected.description">
                <h2>El desafío</h2>
                <p class="text-block">{{ selected.challenge }}</p>
            </section>
            <section v-if="selected.result">
                <h2>La solución</h2>
                <p class="text-block">{{ selected.result }}</p>
            </section>
        </div>

        <section v-if="selected.images?.length" class="case-gallery" aria-labelledby="case-gallery-title">
            <h2 id="case-gallery-title">Galería del trabajo</h2>
            <div class="case-gallery-grid">
                <figure v-for="image in selected.images" :key="image.id">
                    <button type="button" class="case-image" :aria-label="'Ampliar ' + (image.alt_text || 'imagen de la galería')"
                            @click="showImage(image.image_path, image.alt_text || 'Imagen de la galería', $event)">
                        <img :src="image.image_path" :alt="image.alt_text ?? ''" loading="lazy">
                    </button>
                    <figcaption>
                        <span v-if="image.alt_text">{{ image.alt_text }}</span>
                        <span aria-hidden="true">Ampliar imagen</span>
                    </figcaption>
                </figure>
            </div>
        </section>

        <div class="case-contact">
            <B href="/contacto" variant="secondary" arrow>Hablemos de tu proyecto</B>
        </div>

        <v-dialog v-model="imageOpen" max-width="1240" scrollable aria-labelledby="case-viewer-title"
                  @after-leave="restoreImageFocus">
            <v-card v-if="activeImage" class="case-viewer">
                <div class="case-viewer-header">
                    <h2 id="case-viewer-title">{{ activeImage.label }}</h2>
                    <B compact variant="ghost" @click="imageOpen = false">Cerrar</B>
                </div>
                <v-card-text class="case-viewer-content">
                    <img :src="activeImage.path" :alt="activeImage.label">
                </v-card-text>
                <v-card-actions>
                    <B :href="activeImage.path" compact variant="secondary" target="_blank" rel="noopener">
                        Abrir imagen original
                    </B>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
/* El enlace del trabajo queda visible junto a su introducción. */
.case-heading.public-page-heading {
    margin-bottom: 0;
}

.case-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 18px;
    margin-top: 16px;
}

.case-year,
figcaption {
    color: #446075;
    font-size: .9rem;
}

/* Las imágenes conservan su proporción y llevan el borde sobre la captura. */
.case-cover {
    max-width: 1060px;
    margin: 28px auto 0;
}

.case-image {
    display: block;
    width: 100%;
    padding: 0;
    background: transparent;
    border: 0;
    border-radius: 14px;
    cursor: zoom-in;
}

.case-image img {
    display: block;
    width: 100%;
    height: auto;
    border: 1px solid #1f6f7f26;
    border-radius: inherit;
}

.case-cover img {
    box-shadow: 0 6px 20px #153b6412;
}

figcaption {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 4px 12px;
    margin-top: 8px;
}

figcaption [aria-hidden="true"] {
    color: #1f6f7f;
    font-weight: 700;
}

/* El caso se lee antes de la galería, que dispone de todo el ancho en pantallas amplias. */
.case-story {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
    gap: 16px 32px;
    margin-top: 36px;
    overflow-wrap: anywhere;
}

.case-story:empty {
    display: none;
}

.case-gallery {
    margin-top: 32px;
}

.case-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr));
    gap: 24px;
}

.case-gallery-grid figure {
    min-width: 0;
    margin: 0;
}

/* Las miniaturas comparten proporción; el visor sigue usando las dimensiones originales. */
.case-gallery-grid .case-image img {
    aspect-ratio: 4 / 3;
    object-fit: cover;
}

.case-contact {
    margin-top: 32px;
}

/* El visor permite desplazarse en móvil y abrir el archivo original si se necesita más detalle. */
.case-viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
}

.case-viewer-header h2 {
    color: #153b64;
    font: 700 clamp(1.1rem, 3vw, 1.4rem)/1.25 'Space Grotesk', sans-serif;
}

.case-viewer-content img {
    display: block;
    width: 100%;
    height: auto;
}
</style>
