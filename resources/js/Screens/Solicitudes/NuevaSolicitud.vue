<script setup>
import T from '../../Components/UI/PageTitle.vue';
import RequestForm from '../../Components/Solicitudes/FormularioSolicitud.vue';

// La entrada pública usa el mismo formulario con o sin una sesión iniciada.
defineProps({ data: { type: Object, required: true } });
</script>

<template>
    <section class="page shell">
        <T eyebrow="Estimación y solicitud" title="Prepará tu proyecto" description="Elegí los servicios, revisá la referencia de precio y completá tu pedido. Podés comenzar sin registrarte." />
        <RequestForm :data="data" />
    </section>
</template>
