<script setup>
import './public-screen.css';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';
import GrillaProyectos from '../../Components/Portfolio/GrillaProyectos.vue';

defineProps({data: Object});
</script>

<template>
    <!-- La grilla se adapta a los trabajos publicados y cada tarjeta lleva a su ficha. -->
    <section data-public-screen class="page shell public-editorial public-typography">
        <T class="public-page-heading" eyebrow="Portfolio" title="Proyectos de synaux"
           description="Conocé el desafío, las decisiones y el resultado de cada trabajo."/>
        <GrillaProyectos v-if="data.works?.length" :trabajos="data.works" :vista-previa="data.preview"/>
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
.portfolio-empty {
    border-top: 3px solid #2db3a3;
}
</style>
