<script setup>
import S from '../UI/StatusBadge.vue';
import NewBadge from '../Notifications/NewBadge.vue';
import I from '../UI/AppIcon.vue';
import {date, money} from '../../support/formatters';

// Las versiones autorizadas se presentan como una línea de tiempo; cada hito abre su documento completo.
defineProps({
    versiones: {type: Array, required: true},
    titulo: {type: String, default: 'Historial de versiones'},
});
</script>

<template>
    <details class="historial-presupuestos" :aria-label="titulo">
        <summary class="historial-resumen">
            <span class="historial-titulo">{{ titulo }} <span>· {{ versiones.length }} {{ versiones.length === 1 ? 'versión' : 'versiones' }}</span></span>
            <I class="historial-control" name="chevron-down"/>
        </summary>
        <ol>
            <li v-for="version in versiones" :key="version.id" :class="{'version-borrador': version.status === 'draft'}">
                <a class="historial-version" :href="'/presupuestos/' + version.id"
                   :aria-label="'Ver versión ' + version.version_number + ', ' + (version.display_status ?? version.status)">
                    <span class="historial-marcador" aria-hidden="true"></span>
                    <span class="historial-contenido">
                        <span class="historial-encabezado">
                            <strong>Versión {{ version.version_number }}</strong>
                            <span class="historial-estados">
                                <S :status="version.display_status ?? version.status"/>
                                <NewBadge type="presupuesto" :id="version.id"/>
                            </span>
                        </span>
                        <span class="historial-detalle">
                            <time :datetime="version.sent_at ?? version.created_at">{{ date(version.sent_at ?? version.created_at) }}</time>
                            <span aria-hidden="true">·</span>
                            <strong>{{ money(version.total, version.currency_code) }}</strong>
                        </span>
                    </span>
                    <I class="historial-flecha" name="arrow"/>
                </a>
            </li>
        </ol>
    </details>
</template>

<style scoped>
/* La línea de tiempo comparte jerarquía y estados entre Gestión, el cliente y el seguimiento del proyecto. */
.historial-presupuestos {
    min-width: 0;
    padding: 16px;
    border: 1px solid #1f6f7f26;
    border-radius: 10px;
    background: #f5f9fa;
}

.historial-resumen {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #153b64;
    cursor: pointer;
    list-style: none;
}

.historial-resumen::-webkit-details-marker {
    display: none;
}

.historial-titulo {
    color: #153b64;
    font-size: 1rem;
    font-weight: 700;
}

.historial-titulo > span {
    color: #446075;
    font-size: .875rem;
    font-weight: 400;
}

.historial-control {
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
    color: #1f6f7f;
    transition: transform .16s ease;
}

.historial-presupuestos[open] .historial-resumen {
    margin-bottom: 10px;
}

.historial-presupuestos[open] .historial-control {
    transform: rotate(180deg);
}

.historial-resumen:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
    border-radius: 4px;
}

.historial-presupuestos ol {
    list-style: none;
    margin: 0;
    padding: 0;
}

.historial-presupuestos li {
    position: relative;
    padding: 0 0 10px 18px;
}

.historial-presupuestos li:last-child {
    padding-bottom: 0;
}

.historial-presupuestos li::before {
    position: absolute;
    top: 20px;
    bottom: -1px;
    left: 5px;
    width: 1px;
    background: #c9dce2;
    content: '';
}

.historial-presupuestos li:last-child::before {
    display: none;
}

.historial-marcador {
    position: absolute;
    z-index: 1;
    top: 17px;
    left: 0;
    width: 11px;
    height: 11px;
    border: 2px solid #1f6f7f;
    border-radius: 50%;
    background: #fff;
}

.version-borrador .historial-marcador {
    border-color: #2db3a3;
    background: #e7f7f4;
}

.historial-version {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px 16px;
    min-height: 76px;
    padding: 12px 14px;
    border: 1px solid #d4e3e8;
    border-radius: 9px;
    background: #fff;
    color: #153b64;
    text-decoration: none;
    transition: background-color .16s ease, border-color .16s ease, box-shadow .16s ease;
}

.historial-version:hover {
    border-color: #1f6f7f66;
    background: #f5f9fa;
    box-shadow: 0 3px 10px #153b640c;
}

.historial-version:focus-visible {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}

.historial-contenido {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 7px;
    min-width: 0;
}

.historial-encabezado,
.historial-estados {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.historial-encabezado {
    width: 100%;
    justify-content: flex-start;
    gap: 8px 14px;
}

.historial-detalle {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.historial-detalle time,
.historial-detalle > strong {
    color: #446075;
    font-size: .82rem;
}

.historial-detalle > strong {
    color: #153b64;
    font-weight: 700;
}

.historial-flecha {
    width: 18px;
    height: 18px;
    color: #1f6f7f;
}

@media (max-width: 520px) {
    .historial-presupuestos {
        padding: 12px;
    }

    .historial-encabezado {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
