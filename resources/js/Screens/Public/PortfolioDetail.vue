<script setup>
import './public-screen.css';
import {computed} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';

const props = defineProps({data: Object});

// Usa el trabajo seleccionado por Laravel para la ficha y su galería.
const selected = computed(() => props.data.selected ?? {});

// La vista previa de Stitch se identifica como prototipo en el enlace de la ficha.
const isStitchPreview = computed(() => selected.value.live_url?.startsWith('https://stitch.google.com/preview/'));
</script>

<template>
    <!-- El caso de portfolio separa los datos del proyecto de su historia. -->
    <section data-public-screen class="page shell public-editorial public-typography">
        <B href="/portfolio" variant="ghost" compact class="project-back">← Volver al portfolio</B>
        <T class="public-page-heading" :eyebrow="selected.category" :title="selected.title"
           :description="selected.description"/>
        <div v-if="selected.cover_path" class="case-cover"><img :src="selected.cover_path" :alt="selected.title"></div>
        <div class="editorial-split">
            <article class="case-story">
                <template v-if="selected.challenge && selected.challenge !== selected.description"><h2>El desafío</h2>
                    <p class="text-block">{{ selected.challenge }}</p></template>
                <template v-if="selected.result"><h2>La solución</h2>
                    <p class="text-block">{{ selected.result }}</p></template>
                <div v-if="selected.images?.length" class="case-gallery"><img v-for="image in selected.images"
                                                                              :key="image.id" :src="image.image_path"
                                                                              :alt="image.alt_text ?? ''"
                                                                              loading="lazy"></div>
                <B href="/contacto" arrow>Hablemos de tu proyecto</B>
            </article>
            <aside class="editorial-panel case-facts">
                <h2>Ficha del proyecto</h2>
                <dl>
                    <div>
                        <dt>Área</dt>
                        <dd>{{ selected.category }}</dd>
                    </div>
                    <div v-if="selected.year">
                        <dt>Año</dt>
                        <dd>{{ selected.year }}</dd>
                    </div>
                </dl>
                <B v-if="selected.live_url" :href="selected.live_url" variant="secondary">
                    {{ isStitchPreview ? 'Ver prototipo' : 'Visitar proyecto' }}
                </B>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.case-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.case-cover {
    overflow: hidden;
    border-radius: 16px;
    border: 1px solid #1f6f7f26;
    box-shadow: 0 6px 20px #153b6412;
}

.case-cover {
    max-height: 520px;
    margin-bottom: 28px;
}

.case-cover img {
    max-height: 520px;
    object-fit: contain;
    background: #f5f9fa;
}

.case-story {
    min-width: 0;
    overflow-wrap: anywhere;
}

.case-facts dl {
    margin: 0 0 16px;
}

.case-facts dl > div {
    padding-block: 8px;
    border-bottom: 1px solid #1f6f7f22;
}

.case-facts dt {
    font-size: .875rem;
    color: #446075;
}

.case-facts dd {
    margin: 0;
    font-weight: 700;
}

.case-gallery {
    display: grid;
    gap: 16px;
    margin-bottom: 24px;
}

.case-gallery img {
    width: 100%;
    border-radius: 12px;
}
</style>
