<script setup>
import {usePage} from '@inertiajs/vue3';
import BrandLogo from '../Brand/BrandLogo.vue';
import {referenceHref} from '../../support/prototype';

defineProps({
    eyebrow: {type: String, required: true},
    title: {type: String, required: true},
    description: {type: String, required: true},
    alternative: {type: Object, required: true},
});

const page = usePage();
</script>

<template>
    <section class="auth-page">
        <v-card class="card auth-card">
            <BrandLogo/>
            <p class="eyebrow">{{ eyebrow }}</p>
            <h1>{{ title }}</h1>
            <p>{{ description }}</p>

            <slot/>

            <!-- El enlace secundario también funciona en la galería de referencia. -->
            <p class="auth-alt">
                <span>{{ alternative.prompt }}</span>
                <a :href="referenceHref(alternative.href, page.props)">{{ alternative.label }}</a>
            </p>
        </v-card>
    </section>
</template>

<style scoped>
/* La pregunta admite varias líneas y mantiene separación legible respecto del enlace de navegación. */
.auth-alt {
    display: block;
    margin: 25px 0 0;
    text-align: center;
    line-height: 1.7;
}

.auth-alt a {
    display: inline-block;
    margin-inline-start: 4px;
    color: #1f6f7f;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.auth-alt a:hover {
    color: #153b64;
}

.auth-alt a:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
    border-radius: 2px;
}
</style>
