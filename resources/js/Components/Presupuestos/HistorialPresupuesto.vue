<script setup>
import B from '../UI/AppButton.vue';
import S from '../UI/StatusBadge.vue';
import NewBadge from '../Notifications/NewBadge.vue';
import {date, money} from '../../support/formatters';

// Las versiones llegan autorizadas y excluyen la que se muestra como principal.
defineProps({
    versiones: {type: Array, required: true},
    titulo: {type: String, default: 'Historial'},
});
</script>

<template>
    <details class="historial-presupuestos">
        <summary>{{ titulo }} · {{ versiones.length }} {{ versiones.length === 1 ? 'versión' : 'versiones' }}</summary>
        <ol>
            <li v-for="version in versiones" :key="version.id">
                <div class="version-identidad">
                    <strong>PRE-{{ version.budget_id }} · Versión {{ version.version_number }}</strong>
                    <small>{{ date(version.sent_at ?? version.created_at) }} · {{ money(version.total, version.currency_code) }}</small>
                </div>
                <div class="record-heading">
                    <S :status="version.display_status ?? version.status"/>
                    <NewBadge type="presupuesto" :id="version.id"/>
                </div>
                <div class="version-acciones">
                    <B :href="'/presupuestos/' + version.id" variant="ghost" compact
                       :aria-label="'Ver PRE-' + version.budget_id + ', versión ' + version.version_number">Ver versión</B>
                    <slot name="acciones" :version="version"/>
                </div>
            </li>
        </ol>
    </details>
</template>

<style scoped>
/* Cada versión conserva su estado y su enlace en el desplegable del listado o del proyecto. */
.historial-presupuestos {
    padding: 8px 12px;
    border: 1px solid #1f6f7f26;
    border-radius: 8px;
    background: #f5f9fa;
}

.historial-presupuestos summary {
    color: #153b64;
    font-weight: 700;
    cursor: pointer;
}

.historial-presupuestos ol {
    list-style: none;
    margin: 8px 0 0;
    padding: 0;
}

.historial-presupuestos li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px 12px;
    padding: 10px 0;
    border-top: 1px solid #1f6f7f26;
}

.version-identidad {
    grid-column: 1 / -1;
}

.version-acciones {
    grid-column: 1 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.version-identidad small {
    display: block;
    color: #446075;
}
</style>
