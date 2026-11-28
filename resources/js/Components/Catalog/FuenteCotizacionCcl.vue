<script setup>
// Solo atribuye el CCL cuando el dato aplicado proviene del proveedor.
defineProps({fuente: String});
</script>

<template>
    <small v-if="fuente === 'dolarapi'" class="fuente-ccl">
        Fuente CCL:
        <a href="https://dolarapi.com/" target="_blank" rel="noopener noreferrer">DolarApi</a>
    </small>
</template>

<style scoped>
.fuente-ccl { color: #446075; }
.fuente-ccl a { color: #1f6f7f; text-decoration: underline; }
</style>
