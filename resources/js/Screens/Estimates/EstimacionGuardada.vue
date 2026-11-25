<script setup>
import {computed} from 'vue';
import DeleteEstimateButton from '../../Components/Estimates/DeleteEstimateButton.vue';
import EstimateResult from '../../Components/Estimates/EstimateResult.vue';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';

const props = defineProps({data: {type: Object, required: true}});

// La estimación guardada se reconstruye con sus importes y moneda originales.
const result = computed(() => {
    const estimate = props.data.selected;

    return {
        ...estimate,
        service: props.data.services.find(item => item.database_id === estimate.service_id),
        min: estimate.price_min,
        max: estimate.price_max,
        mode: estimate.status === 'manual' ? 'manual' : 'automatic',
        currency: estimate.currency_snapshot,
        base_min: estimate.base_min_snapshot,
        base_max: estimate.base_max_snapshot,
        date: estimate.created_at,
    };
});

// Si el servicio ya no está disponible, se abre el recorrido general de estimación.
const newEstimateHref = computed(() => result.value.service
    ? '/estimador?servicio=' + encodeURIComponent(result.value.service.id)
    : '/estimador');
</script>

<template>
    <section class="page shell estimator workspace-view">
        <B v-if="data.area !== 'cliente'" href="/cliente/estimaciones" compact variant="ghost"
           class="workspace-back">← Volver a estimaciones</B>
        <T eyebrow="Estimación orientativa" title="Tu estimación guardada"/>

        <EstimateResult :result="result" :preview="Boolean(data.preview)">
            <template v-if="$page.props.auth || data.preview">
                <B :href="'/solicitudes/nueva?estimacion=' + data.selected.id" arrow>Continuar con la solicitud</B>
                <DeleteEstimateButton :estimate="data.selected"/>
            </template>
            <B v-else href="/ingresar" arrow>Ingresar para solicitar presupuesto</B>
            <B :href="newEstimateHref" variant="secondary">Nueva estimación</B>
        </EstimateResult>
    </section>
</template>

<style scoped>
.estimator {
    max-width: 1180px;
}

/* El importe conserva prioridad dentro de la escala del panel. */
.estimator :deep(.result-card h2) {
    font-size: clamp(1.4rem, 2.5vw, 2rem);
}
</style>
