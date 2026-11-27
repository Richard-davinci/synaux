<script setup>
import './public-screen.css';
import {computed, ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import AppIcon from '../../Components/UI/AppIcon.vue';
import T from '../../Components/UI/PageTitle.vue';

const props = defineProps({data: Object});

// Usa los datos del trabajo y el enlace externo guardado desde Gestión.
const selected = computed(() => props.data.selected ?? {});
const siteLabel = computed(() => selected.value.live_url?.startsWith('https://stitch.google.com/preview/')
    ? 'Ver sitio'
    : 'Visitar proyecto');

// La portada solo aparece dentro de la galería de la ficha, aunque ya esté incluida entre las imágenes.
const galleryImages = computed(() => {
    const {cover_path, images = [], title} = selected.value;
    if (!cover_path || images.some(image => image.image_path === cover_path)) return images;

    return [{id: 'cover', image_path: cover_path, alt_text: 'Portada de ' + title}, ...images];
});

// El visor conserva la imagen completa y devuelve el foco al control que lo abrió.
const imageOpen = ref(false);
const indiceActivo = ref(0);
const imagenActiva = computed(() => galleryImages.value[indiceActivo.value]);
const tituloImagen = computed(() => imagenActiva.value?.alt_text || 'Imagen de la galería');
let imageTrigger;
let inicioDeslizamiento;

function showImage(indice, event) {
    imageTrigger = event.currentTarget;
    indiceActivo.value = indice;
    imageOpen.value = true;
}

// Botones, teclado y gestos recorren el mismo orden y vuelven al principio al llegar al final.
function moverImagen(pasos) {
    const cantidad = galleryImages.value.length;
    if (cantidad > 1) indiceActivo.value = (indiceActivo.value + pasos + cantidad) % cantidad;
}

// El diálogo recibe las flechas incluso cuando el foco aún está en su contenedor.
function navegarConTeclado(event) {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

    event.preventDefault();
    moverImagen(event.key === 'ArrowLeft' ? -1 : 1);
}

// Solo un gesto intencional en sentido horizontal cambia la imagen.
function iniciarDeslizamiento(event) {
    if (!event.isPrimary || event.button !== 0 || galleryImages.value.length < 2) return;

    inicioDeslizamiento = {id: event.pointerId, x: event.clientX, y: event.clientY};
    event.currentTarget.setPointerCapture(event.pointerId);
}

function cancelarDeslizamiento(event) {
    if (inicioDeslizamiento?.id === event.pointerId) inicioDeslizamiento = null;
}

function terminarDeslizamiento(event) {
    if (inicioDeslizamiento?.id !== event.pointerId) return;

    const horizontal = event.clientX - inicioDeslizamiento.x;
    const vertical = event.clientY - inicioDeslizamiento.y;
    inicioDeslizamiento = null;
    if (Math.abs(horizontal) >= 48 && Math.abs(horizontal) > Math.abs(vertical) * 1.3) {
        moverImagen(horizontal < 0 ? 1 : -1);
    }
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

        <section v-if="galleryImages.length" class="case-gallery" aria-labelledby="case-gallery-title">
            <h2 id="case-gallery-title">Galería del trabajo</h2>
            <div class="case-gallery-grid">
                <figure v-for="(image, indice) in galleryImages" :key="image.id">
                    <button type="button" class="case-image"
                            :aria-label="'Ampliar ' + (image.alt_text || 'imagen de la galería')"
                            @click="showImage(indice, $event)">
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

        <v-dialog v-model="imageOpen" fullscreen class="case-gallery-dialog" aria-labelledby="case-viewer-title"
                  :content-props="{ onKeydown: navegarConTeclado }" @after-leave="restoreImageFocus">
            <div v-if="imagenActiva" class="case-viewer">
                <div class="case-viewer-header">
                    <h2 id="case-viewer-title">{{ tituloImagen }}</h2>
                    <B compact variant="ghost" @click="imageOpen = false">Cerrar</B>
                </div>
                <div class="case-viewer-content" @pointerdown="iniciarDeslizamiento"
                     @pointerup="terminarDeslizamiento" @lostpointercapture="cancelarDeslizamiento">
                    <img :src="imagenActiva.image_path" :alt="tituloImagen" draggable="false">
                </div>
                <nav v-if="galleryImages.length > 1" class="case-viewer-navigation" aria-label="Navegación de imágenes">
                    <button type="button" class="case-viewer-arrow" aria-label="Imagen anterior"
                            @click="moverImagen(-1)">
                        <AppIcon name="arrow" class="case-viewer-arrow-back"/>
                    </button>
                    <span role="status">Imagen {{ indiceActivo + 1 }} de {{ galleryImages.length }}</span>
                    <button type="button" class="case-viewer-arrow" aria-label="Imagen siguiente"
                            @click="moverImagen(1)">
                        <AppIcon name="arrow"/>
                    </button>
                </nav>
            </div>
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

/* Las miniaturas abren el visor al seleccionar la captura. */

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

/* Las miniaturas comparten proporción; el visor muestra el encuadre completo. */
.case-gallery-grid .case-image img {
    aspect-ratio: 4 / 3;
    object-fit: cover;
}

.case-contact {
    margin-top: 32px;
}

/* El encabezado reserva su propio espacio y la imagen se ajusta al resto de la pantalla. */
:global(.case-gallery-dialog.v-dialog--fullscreen > .v-overlay__content) {
    overflow: hidden;
}

.case-viewer {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    width: 100%;
    height: 100dvh;
    min-height: 0;
    overflow: hidden;
    background: #f5f9fa;
}

.case-viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: max(8px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) 8px max(16px, env(safe-area-inset-left));
    border-bottom: 1px solid #1f6f7f26;
    background: #fff;
}

.case-viewer-header h2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    min-width: 0;
    margin: 0;
    color: #153b64;
    font: 700 clamp(1rem, 3vw, 1.25rem)/1.25 'Space Grotesk', sans-serif;
    overflow-wrap: anywhere;
}

.case-viewer-header .btn {
    min-height: 44px;
    flex-shrink: 0;
}

.case-viewer-content {
    display: grid;
    grid-template: minmax(0, 1fr) / minmax(0, 1fr);
    touch-action: pan-y pinch-zoom;
    user-select: none;
    padding: 8px max(8px, env(safe-area-inset-right)) max(8px, env(safe-area-inset-bottom)) max(8px, env(safe-area-inset-left));
}

.case-viewer-content img {
    display: block;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    object-fit: contain;
}

/* Las flechas quedan fuera de la imagen para que nunca tapen parte de la captura. */
.case-viewer-navigation {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 4px max(12px, env(safe-area-inset-right)) max(4px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left));
    border-top: 1px solid #1f6f7f26;
    background: #fff;
    color: #153b64;
    font-weight: 700;
}

.case-viewer-arrow {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 1px solid #1f6f7f4d;
    border-radius: 8px;
    color: inherit;
    background: #fff;
    cursor: pointer;
}

.case-viewer-arrow:hover {
    background: #f5f9fa;
}

.case-viewer-arrow:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

.case-viewer-arrow .icon {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.case-viewer-arrow-back {
    transform: rotate(180deg);
}
</style>
