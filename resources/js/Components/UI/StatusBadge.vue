<script setup>
import {computed} from 'vue';
// Las etiquetas traducen estados persistidos; una pantalla puede precisar el texto sin cambiar el estado ni su color.
const props = defineProps({value: String, status: String, request: Boolean, etiqueta: String});
const value = computed(() => props.status ?? props.value);
const labels = {
    enabled: 'Activo',
    disabled: 'Inactivo',
    published: 'Publicado',
    unpublished: 'Privado',
    draft: 'Borrador',
    open: 'Publicada',
    closed: 'Cerrada',
    cancelled: 'Cancelada',
    submitted: 'Recibida',
    sent: 'Enviado',
    issued: 'Emitido',
    accepted: 'Aceptado',
    rejected: 'Rechazado',
    expired: 'Vencido',
    withdrawn: 'Retirada',
    pending: 'Pendiente',
    declined: 'Rechazada',
    in_progress: 'En desarrollo',
    completed: 'Completado',
    calculated: 'Calculada',
    preparation_saved: 'Preparación guardada',
    manual: 'Revisión profesional',
    review: 'En revisión'
};
const label = computed(() => props.etiqueta ?? (props.request && value.value === 'open' ? 'Enviada' : ({
    available: 'Disponible',
    busy: 'Con agenda',
    unavailable: 'No disponible', ...labels
})[value.value] ?? value.value));
</script>
<template><span :class="['status', `status-${value}`]"><span/>{{ label }}</span></template>
