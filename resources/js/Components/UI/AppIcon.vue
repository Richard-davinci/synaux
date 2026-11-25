<script setup>
// Iconos del código original de Make, conservados como componente vectorial reutilizable.
defineProps({name: {type: String, default: 'arrow'}});
</script>
<template>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
        <path v-if="name === 'arrow'" d="M5 12h14M13 6l6 6-6 6"/>
        <path v-else-if="name === 'chevron-down'" d="m6 9 6 6 6-6"/>
        <path v-else-if="name === 'check'" d="m5 12 4 4L19 6"/>
        <!-- Calendario para seleccionar fechas del proyecto. -->
        <g v-else-if="name === 'calendar'">
            <rect x="3" y="5" width="18" height="16" rx="2"/>
            <path d="M7 3v4m10-4v4M3 10h18"/>
        </g>
        <g v-else-if="name === 'clock'">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 7v5l3 2"/>
        </g>
        <path v-else-if="name === 'folder'" d="M3 7h7l2 2h9v10H3z"/>
        <g v-else-if="name === 'file'">
            <path d="M6 3h8l4 4v14H6z"/>
            <path d="M14 3v5h5"/>
        </g>
        <g v-else-if="name === 'user'">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21c1-5 4-7 8-7s7 2 8 7"/>
        </g>
        <!-- El marcador identifica los datos de ubicación del perfil. -->
        <g v-else-if="name === 'location'">
            <path d="M19 10c0 5-7 10-7 10S5 15 5 10a7 7 0 1 1 14 0Z"/>
            <circle cx="12" cy="10" r="2.5"/>
        </g>
        <path v-else-if="name === 'menu'" d="M4 7h16M4 12h16M4 17h16"/>
        <path v-else-if="name === 'close'" d="m6 6 12 12M18 6 6 18"/>
        <!-- La puerta y la flecha identifican la salida de la cuenta. -->
        <path v-else-if="name === 'logout'" d="M11 4H5v16h6m4-13 5 5-5 5m5-5H9"/>
        <!-- La campana identifica el centro de novedades sin alterar los recursos originales de marca. -->
        <g v-else-if="name === 'bell'">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
        </g>
        <!-- Iconos para indicar si la contraseña está visible u oculta. -->
        <g v-else-if="name === 'eye'">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
            <circle cx="12" cy="12" r="3"/>
        </g>
        <g v-else-if="name === 'eye-off'">
            <path
                d="m3 3 18 18M10.6 5.1A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 4M6.3 6.3A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.7-1.7M10 10a3 3 0 0 0 4 4"/>
        </g>
        <!-- El cesto identifica acciones de eliminación con confirmación. -->
        <g v-else-if="name === 'trash'">
            <path d="M4 7h16m-10 4v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/>
        </g>
        <path v-else-if="name === 'plus'" d="M12 5v14M5 12h14"/>
        <g v-else-if="name === 'edit'">
            <path d="m4 16-1 5 5-1L19 9l-4-4z"/>
            <path d="m13 7 4 4"/>
        </g>
        <!-- El icono de compartir distingue el enlace de activación de la edición del rol. -->
        <g v-else-if="name === 'share'">
            <circle cx="5" cy="12" r="3"/><circle cx="19" cy="5" r="3"/><circle cx="19" cy="19" r="3"/>
            <path d="m7.7 10.7 8.6-4.4m-8.6 7 8.6 4.4"/>
        </g>
        <!-- Las dos hojas indican que la nueva versión toma los datos de una versión existente. -->
        <g v-else-if="name === 'copy'">
            <rect x="8" y="8" width="12" height="12" rx="2"/>
            <path d="M4 16V6a2 2 0 0 1 2-2h10"/>
        </g>
        <!-- Iconos de servicios con el mismo trazo que los controles existentes. -->
        <g v-else-if="name === 'web'">
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <path d="M3 9h18M7 6.5h.01M10 6.5h.01m-1 6-3 2 3 2m6-4 3 2-3 2"/>
        </g>
        <g v-else-if="name === 'palette'">
            <path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 1-3.7c-.7-.5-.4-1.6.5-1.6H17a4 4 0 0 0 4-4A9 9 0 0 0 12 3Z"/>
            <circle cx="7.5" cy="10" r=".75"/>
            <circle cx="10" cy="6.5" r=".75"/>
            <circle cx="15" cy="6.5" r=".75"/>
            <circle cx="18" cy="10" r=".75"/>
        </g>
        <g v-else-if="name === 'megaphone'">
            <path d="m4 10 15-5v14L4 14v-4Zm3 5 1 5h3l-1-4M4 10H2v4h2M22 8v8"/>
        </g>
        <g v-else-if="name === 'layout'">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M3 8h18M9 8v13"/>
        </g>
        <!-- Los módulos de administración y gestión usan símbolos distintos para cada función. -->
        <g v-else-if="name === 'grid'">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
        </g>
        <g v-else-if="name === 'tag'">
            <path d="M3 4h8l10 10-7 7L3 10z"/>
            <circle cx="7.5" cy="8" r="1"/>
        </g>
        <g v-else-if="name === 'calculator'">
            <rect x="5" y="3" width="14" height="18" rx="2"/>
            <path d="M8 7h8M8 11h1m3 0h1m3 0h.01M8 15h1m3 0h1m3 0v3M8 18h1m3 0h1"/>
        </g>
        <g v-else-if="name === 'receipt'">
            <path d="M5 3h14v18l-3.5-2-3.5 2-3.5-2L5 21zM9 8h6m-6 4h6m-6 4h3"/>
        </g>
        <path v-else-if="name === 'message'" d="M4 4h16v12H9l-5 4zm4 4h8m-8 4h5"/>
        <g v-else>
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 11v6M12 7h.01"/>
        </g>
    </svg>
</template>
