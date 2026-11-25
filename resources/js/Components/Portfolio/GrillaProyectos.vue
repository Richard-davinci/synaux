<script setup>
import AppIcon from '../UI/AppIcon.vue';

// Las dos vistas comparten la tarjeta y el nivel del título se adapta a cada página.
defineProps({
    trabajos: {type: Array, required: true},
    nivelTitulo: {type: String, default: 'h2'},
    vistaPrevia: {type: Boolean, default: false},
});
</script>

<template>
    <!-- Cada tarjeta usa la portada y la categoría del trabajo y enlaza a su ficha. -->
    <div class="project-grid">
        <a v-for="(trabajo, indice) in trabajos" :key="trabajo.id" class="project-card"
           :href="vistaPrevia ? '/prototipo/trabajo' : `/trabajos/${trabajo.id}`">
            <div class="project-image">
                <img v-if="trabajo.cover_path" :src="trabajo.cover_path" alt=""
                     :loading="indice < 3 ? 'eager' : 'lazy'">
                <AppIcon v-else name="folder" aria-hidden="true"/>
            </div>
            <div class="project-copy">
                <p v-if="trabajo.category" class="project-category">{{ trabajo.category }}</p>
                <component :is="nivelTitulo" class="project-title">{{ trabajo.title }}</component>
                <p v-if="trabajo.description" class="project-description">{{ trabajo.description }}</p>
                <span class="project-action">Ver proyecto <AppIcon name="arrow" aria-hidden="true"/></span>
            </div>
        </a>
    </div>
</template>

<style scoped>
/* La grilla reserva el mismo ancho por tarjeta aunque solo haya un trabajo publicado. */
.project-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
    gap: 20px;
}

.project-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #1f6f7f26;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 4px 16px #153b640d;
    text-decoration: none;
    color: #153b64;
    font-family: 'Nunito', sans-serif;
    line-height: 1.5;
}

.project-card:hover, .project-card:focus-visible {
    border-color: #1f6f7f;
    box-shadow: 0 8px 22px #153b641a;
}

.project-card:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

/* La portada conserva el encuadre del portfolio y tiene una alternativa si falta la imagen. */
.project-image {
    display: grid;
    place-items: center;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #f5f9fa;
    color: #1f6f7f;
}

.project-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.project-image > .icon {
    width: 32px;
    height: 32px;
}

/* El texto mantiene la jerarquía y la acción queda al pie de cada tarjeta. */
.project-copy {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 20px;
    overflow-wrap: anywhere;
}

.project-category {
    margin: 0 0 6px;
    color: #1f6f7f;
    font-size: .76rem;
    font-weight: 700;
    line-height: 1.6;
    letter-spacing: .14em;
    text-transform: uppercase;
}

.project-copy .project-title {
    margin: 0 0 8px;
    color: #153b64;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.25;
    letter-spacing: -.035em;
    text-wrap: balance;
}

.project-description {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
    margin: 0 0 16px;
    color: #446075;
    font-size: 1rem;
    line-height: 1.55;
    text-wrap: pretty;
}

.project-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    font-weight: 700;
    color: #1f6f7f;
}
</style>
