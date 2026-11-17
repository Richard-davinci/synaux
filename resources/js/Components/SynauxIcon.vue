<script setup>
// Iconos del código original de Make, conservados como componente vectorial reutilizable.
defineProps({ name: { type: String, default: 'arrow' } });
</script>
<template>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
        <path v-if="name === 'arrow'" d="M5 12h14M13 6l6 6-6 6" />
        <path v-else-if="name === 'check'" d="m5 12 4 4L19 6" />
        <g v-else-if="name === 'clock'"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></g>
        <path v-else-if="name === 'folder'" d="M3 7h7l2 2h9v10H3z" />
        <g v-else-if="name === 'file'"><path d="M6 3h8l4 4v14H6z" /><path d="M14 3v5h5" /></g>
        <g v-else-if="name === 'user'"><circle cx="12" cy="8" r="4" /><path d="M4 21c1-5 4-7 8-7s7 2 8 7" /></g>
        <path v-else-if="name === 'menu'" d="M4 7h16M4 12h16M4 17h16" />
        <path v-else-if="name === 'close'" d="m6 6 12 12M18 6 6 18" />
        <!-- La campana identifica el centro de novedades sin alterar los recursos originales de marca. -->
        <g v-else-if="name === 'bell'"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></g>
        <!-- Iconos para indicar si la contraseña está visible u oculta. -->
        <g v-else-if="name === 'eye'"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></g>
        <g v-else-if="name === 'eye-off'"><path d="m3 3 18 18M10.6 5.1A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 4M6.3 6.3A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.7-1.7M10 10a3 3 0 0 0 4 4" /></g>
        <path v-else-if="name === 'plus'" d="M12 5v14M5 12h14" />
        <g v-else-if="name === 'edit'"><path d="m4 16-1 5 5-1L19 9l-4-4z" /><path d="m13 7 4 4" /></g>
        <!-- Iconos de servicios con el mismo trazo que los controles existentes. -->
        <g v-else-if="name === 'web'"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 9h18M7 6.5h.01M10 6.5h.01m-1 6-3 2 3 2m6-4 3 2-3 2" /></g>
        <g v-else-if="name === 'palette'"><path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 1-3.7c-.7-.5-.4-1.6.5-1.6H17a4 4 0 0 0 4-4A9 9 0 0 0 12 3Z" /><circle cx="7.5" cy="10" r=".75" /><circle cx="10" cy="6.5" r=".75" /><circle cx="15" cy="6.5" r=".75" /><circle cx="18" cy="10" r=".75" /></g>
        <g v-else-if="name === 'megaphone'"><path d="m4 10 15-5v14L4 14v-4Zm3 5 1 5h3l-1-4M4 10H2v4h2M22 8v8" /></g>
        <g v-else-if="name === 'layout'"><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 8h18M9 8v13" /></g>
        <g v-else><circle cx="12" cy="12" r="9" /><path d="M12 11v6M12 7h.01" /></g>
    </svg>
</template>
