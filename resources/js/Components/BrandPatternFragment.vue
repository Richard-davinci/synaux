<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import pieces from '../../images/brand/synaux-trama-fragmento.json';

// Los trazos originales conservan su color y escala; solo se muestran piezas que caben completas.
const surface = ref(null);
const width = ref(0);
const scale = 380 / 510;
const visiblePieces = computed(() => pieces.filter(piece => piece.right * scale <= width.value && piece.bottom * scale <= 180));
let observer;
onMounted(() => {
    width.value = surface.value.getBoundingClientRect().width;
    observer = new ResizeObserver(([entry]) => { width.value = entry.contentRect.width; });
    observer.observe(surface.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <svg ref="surface" width="100%" height="180" aria-hidden="true" focusable="false">
        <g :transform="`scale(${scale})`" opacity="0.8">
            <path v-for="piece in visiblePieces" :key="piece.d" :d="piece.d" :fill="piece.fill" />
        </g>
    </svg>
</template>
