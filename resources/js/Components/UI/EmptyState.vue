<script setup>
import AppButton from './AppButton.vue';
// Estado real de listas vacías; la acción propone cómo crear el primer registro.
defineProps({title: String, text: String, href: String, action: String});
</script>
<template>
    <v-card class="card empty-state"><h2>{{ title }}</h2>
        <p>{{ text }}</p>
        <AppButton v-if="href" :href="href">{{ action }}</AppButton>
        <slot/>
    </v-card>
</template>
