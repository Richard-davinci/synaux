<script setup>
import './public-screen.css';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';
</script>

<template>
    <!-- La presentación explica cómo trabaja synaux con los recursos visuales de la landing. -->
    <section data-public-screen class="page shell public-editorial public-typography">
        <div class="about-hero">
            <div>
                <T class="public-page-heading" eyebrow="Nosotros" title="De la estrategia a la entrega"
                   description="En synaux conectamos estrategia, diseño y desarrollo para que puedas avanzar con un objetivo y un alcance claros."/>
                <div class="editorial-actions"><B href="/contacto" arrow>Hablemos de tu proyecto</B><B href="/portfolio"
                                                                                                       variant="secondary">Ver
                    portfolio</B></div>
            </div>
            <figure class="about-image"><img src="/images/landing/planificacion.webp"
                                             alt="Planificación de un sitio web con bocetos y una computadora.">
            </figure>
        </div>
        <div class="about-principles">
            <h2>Cómo trabajamos</h2>
            <div class="principles-grid">
                <article class="editorial-panel"><I name="user" class="editorial-icon"/>
                    <h3>Partimos de tu objetivo</h3>
                    <p>Revisamos qué querés resolver, qué recursos ya tenés y qué resultado esperás.</p></article>
                <article class="editorial-panel"><I name="file" class="editorial-icon"/>
                    <h3>Acordamos el alcance</h3>
                    <p>La propuesta reúne los entregables, el precio y las condiciones para que puedas revisarlos antes
                        de decidir.</p></article>
                <article class="editorial-panel"><I name="check" class="editorial-icon"/>
                    <h3>Dejamos registro del avance</h3>
                    <p>Desde tu cuenta podés consultar el presupuesto y seguir los próximos pasos del proyecto.</p>
                </article>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* La fotografía acompaña la presentación y la tarjeta de contacto conserva la acción principal. */
.about-hero {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: center;
    gap: clamp(24px, 5vw, 56px);
}

.about-image {
    aspect-ratio: 3 / 2;
    margin: 0;
}

.about-principles {
    margin-top: clamp(28px, 5vw, 60px);
}

.principles-grid .editorial-panel {
    border-top: 3px solid #2db3a3;
}

.about-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.about-image {
    overflow: hidden;
    border-radius: 16px;
    border: 1px solid #1f6f7f26;
    box-shadow: 0 6px 20px #153b6412;
}

.principles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 270px), 1fr));
    gap: 20px;
}

@media (max-width: 820px) {
    .about-hero {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
