<script setup>
import T from '../../Components/UI/PageTitle.vue';
import RequestForm from '../../Components/Solicitudes/FormularioSolicitud.vue';

// La entrada pública usa el mismo formulario con o sin una sesión iniciada.
defineProps({data: {type: Object, required: true}});
</script>

<template>
    <section class="page shell workspace-view nueva-solicitud">
        <T title="Nueva estimación"
           :description="data.area === 'cliente' ? 'Elegí los servicios y completá los datos del proyecto. Podés guardar la preparación en Estimaciones; al enviarla, pasará a Solicitudes para que synaux la revise.'
               : 'Elegí los servicios y completá los datos del proyecto. Podés revisar la estimación antes de enviarla.'"/>
        <RequestForm :data="data"/>
    </section>
</template>

<style scoped>
/* El encabezado deja el progreso y el formulario visibles desde el primer vistazo. */
.nueva-solicitud :deep(.page-title) {
    margin-bottom: 14px;
}
</style>
