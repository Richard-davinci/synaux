<script setup>
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import patternSource from '../../../images/brand/synaux-trama.svg?raw';

// Los trazos originales conservan su color y escala; solo se muestran piezas que caben completas.
const surface = ref(null);
const width = ref(0);
const pieces = ref([]);
const scale = 380 / 510;
const visiblePieces = computed(() => pieces.value.filter(piece => piece.right * scale <= width.value && piece.bottom * scale <= 180));
let observer;
onMounted(() => {
    // Se leen los trazos del único SVG aprobado y se descartan los que exceden la ventana visible.
    const document = new DOMParser().parseFromString(patternSource, 'image/svg+xml');
    pieces.value = [...document.querySelector('g[opacity="0.8"]').querySelectorAll('path')].map(path => {
        const d = path.getAttribute('d');
        const coordinates = d.match(/-?\d*\.?\d+(?:[eE][-+]?\d+)?/g).map(Number);
        const x = coordinates.filter((_, index) => index % 2 === 0);
        const y = coordinates.filter((_, index) => index % 2 === 1);
        return {
            d,
            fill: path.getAttribute('fill'),
            left: Math.min(...x),
            top: Math.min(...y),
            right: Math.max(...x),
            bottom: Math.max(...y)
        };
    }).filter(piece => piece.left >= 0 && piece.top >= 0 && piece.right <= 510 && piece.bottom <= 90);
    width.value = surface.value.getBoundingClientRect().width;
    observer = new ResizeObserver(([entry]) => {
        width.value = entry.contentRect.width;
    });
    observer.observe(surface.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <svg ref="surface" width="100%" height="180" aria-hidden="true" focusable="false">
        <g :transform="`scale(${scale})`" opacity="0.8">
            <path v-for="piece in visiblePieces" :key="piece.d" :d="piece.d" :fill="piece.fill"/>
        </g>
    </svg>
</template>
