<script setup>
import {computed} from 'vue';
import {usePage} from '@inertiajs/vue3';

// El badge depende de avisos sin leer de la cuenta actual, no de la antigüedad ni del estado del registro.
const props = defineProps({type: String, id: [Number, String]});
const page = usePage();
const isNew = computed(() => !page.props.preview && (page.props.notifications?.unread_entities?.[props.type] ?? []).some(id => Number(id) === Number(props.id)));
</script>

<template><span v-if="isNew" class="new-badge">Nuevo<span class="badge-context"> · actividad sin leer</span></span>
</template>

<style scoped>
/* El texto distingue la novedad del estado operativo y mantiene contraste con la paleta de synaux. */
.new-badge {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    padding: 3px 9px;
    border: 1px solid #2db3a366;
    border-radius: 20px;
    background: #2db3a31a;
    color: #153b64;
    font-size: .75rem;
    line-height: 1.4;
    font-weight: 800;
    vertical-align: middle;
}

.badge-context {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}
</style>
