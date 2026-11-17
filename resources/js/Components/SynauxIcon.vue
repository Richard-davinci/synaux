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
        <path v-else-if="name === 'plus'" d="M12 5v14M5 12h14" />
        <g v-else-if="name === 'edit'"><path d="m4 16-1 5 5-1L19 9l-4-4z" /><path d="m13 7 4 4" /></g>
        <g v-else><circle cx="12" cy="12" r="9" /><path d="M12 11v6M12 7h.01" /></g>
    </svg>
</template>
