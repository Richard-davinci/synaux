<script setup>
import './public-screen.css';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';

defineProps({data: Object});
</script>

<template>
    <!-- La grilla se adapta a los trabajos publicados y cada tarjeta lleva a su ficha. -->
    <section data-public-screen class="page shell public-editorial public-typography">
        <T class="public-page-heading" eyebrow="Portfolio" title="Proyectos de synaux"
           description="Conocé el desafío, las decisiones y el resultado de cada trabajo."/>
        <div v-if="data.works?.length" class="project-grid">
            <a v-for="(work, index) in data.works" :key="work.id" class="project-card"
               :href="data.preview ? '/prototipo/trabajo' : `/trabajos/${work.id}`">
                <div class="project-image">
                    <img v-if="work.cover_path" :src="work.cover_path" alt="" :loading="index < 3 ? 'eager' : 'lazy'">
                    <I v-else name="folder"/>
                </div>
                <div class="project-copy">
                    <p class="eyebrow">{{ work.category }}</p>
                    <h2>{{ work.title }}</h2>
                    <p v-if="work.description" class="project-description">{{ work.description }}</p>
                    <span class="project-action">Ver proyecto <I name="arrow"/></span>
                </div>
            </a>
        </div>
        <div v-else class="editorial-panel portfolio-empty">
            <I name="folder" class="editorial-icon"/>
            <h2>Todavía no hay proyectos publicados</h2>
            <p>Mientras tanto, podés explorar los servicios o contarnos qué necesitás.</p>
            <div class="editorial-actions"><B href="/plataforma" arrow>Explorar servicios</B><B href="/contacto"
                                                                                                variant="secondary">Contanos
                tu proyecto</B></div>
        </div>
        <p v-if="data.preview" class="photo-credit">Fotografías de Airlangga Jati, Creatvise y Branislav Rodman en
            Unsplash.</p>
    </section>
</template>

<style scoped>
/* Las fichas mantienen un tamaño útil con pocos o muchos proyectos. */
.project-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 270px), 1fr));
    gap: 20px;
}

.project-card {
    border: 1px solid #1f6f7f26;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 4px 16px #153b640d;
}

.project-grid {
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
}

.project-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    text-decoration: none;
    color: #153b64;
}

.project-card:hover, .project-card:focus-visible {
    border-color: #1f6f7f;
    box-shadow: 0 8px 22px #153b641a;
}

.project-image {
    display: grid;
    place-items: center;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #f5f9fa;
    color: #1f6f7f;
}

.project-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.project-image > .icon {
    width: 32px;
    height: 32px;
}

.project-copy {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 20px;
    overflow-wrap: anywhere;
}

.project-copy .eyebrow {
    margin-bottom: 6px;
}

.project-description {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.project-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    font-weight: 700;
    color: #1f6f7f;
}

.portfolio-empty {
    border-top: 3px solid #2db3a3;
}
</style>
