<script setup>
// Los cuatro SVG son exportaciones del aprobado; no se reconstruye ni se recolorea la marca.
import colorLogo from '../../../images/brand/synaux-vertical-con-slogan-color.svg';
import whiteLogo from '../../../images/brand/synaux-vertical-con-slogan-blanco.svg';
import compactColorLogo from '../../../images/brand/synaux-vertical-sin-slogan-color.svg';
import compactWhiteLogo from '../../../images/brand/synaux-vertical-sin-slogan-blanco.svg';

// withSlogan elige la composición; variant permite usar el original a color o el blanco.
defineProps({
    withSlogan: {type: Boolean, default: true},
    variant: {
        type: String,
        default: 'color',
        validator: (value) => ['color', 'white'].includes(value),
    },
});
</script>

<template>
    <!-- La variante determina el archivo y su altura nativa; el CSS ajusta el ancho proporcionalmente. -->
    <img
        :src="withSlogan
            ? (variant === 'white' ? whiteLogo : colorLogo)
            : (variant === 'white' ? compactWhiteLogo : compactColorLogo)"
        class="brand-logo"
        width="250"
        :height="withSlogan ? 179 : 168"
        :alt="withSlogan ? 'synaux, Creciendo juntos' : 'synaux'"
        decoding="async"
    >
</template>
