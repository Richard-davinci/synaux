<script setup>
import {usePage} from '@inertiajs/vue3';
import {referenceHref} from '../../support/prototype';

// Solo los niveles anteriores son enlaces; la página actual queda identificada para lectores de pantalla.
defineProps({niveles: {type: Array, required: true}});
const page = usePage();
</script>

<template>
    <nav class="shell migas-navegacion" aria-label="Migas de pan">
        <ol>
            <li v-for="([nombre, href], indice) in niveles" :key="href || nombre">
                <span v-if="indice" class="migas-separador" aria-hidden="true">/</span>
                <a v-if="href" :href="referenceHref(href, page.props)">{{ nombre }}</a>
                <span v-else aria-current="page">{{ nombre }}</span>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
/* La ruta conserva el margen del contenido y puede pasar a otra línea en pantallas angostas. */
.migas-navegacion {
    width: min(1180px, calc(100% - 48px));
    padding-top: clamp(18px, 2.5vw, 30px);
    color: #446075;
    font-size: .875rem;
}

.migas-navegacion ol,
.migas-navegacion li {
    display: flex;
    align-items: center;
    gap: 5px 10px;
}

.migas-navegacion ol {
    flex-wrap: wrap;
    margin: 0;
    padding: 0;
    list-style: none;
}

.migas-navegacion a {
    color: #1f6f7f;
    font-weight: 700;
}

.migas-navegacion a:hover {
    text-decoration: underline;
}

.migas-navegacion a:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.migas-navegacion [aria-current="page"] {
    color: #153b64;
    font-weight: 700;
}
</style>
