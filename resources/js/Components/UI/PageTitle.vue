<script setup>
// Encabezado común con jerarquía consistente; el slot permite ubicar una acción a la derecha.
defineProps({ eyebrow: String, title: String, description: String });
</script>
<template><div class="page-title"><div><p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p><h1>{{ title }}</h1><p v-if="description">{{ description }}</p></div><slot /></div></template>
