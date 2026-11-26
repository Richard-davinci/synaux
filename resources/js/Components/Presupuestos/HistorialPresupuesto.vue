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
    <section class="historial-presupuestos" :aria-label="titulo">
        <h3>{{ titulo }} <span>· {{ versiones.length }} {{ versiones.length === 1 ? 'versión' : 'versiones' }}</span></h3>
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
                        <small>{{ date(version.sent_at ?? version.created_at) }} · {{ money(version.total, version.currency_code) }}</small>
                    </span>
                    <I class="historial-flecha" name="arrow"/>
                </a>
            </li>
        </ol>
    </section>
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

.historial-presupuestos h3 {
    margin: 0 0 10px;
    color: #153b64;
    font-size: 1rem;
    font-weight: 700;
}

.historial-presupuestos h3 span {
    color: #446075;
    font-size: .875rem;
    font-weight: 400;
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
    grid-template-columns: minmax(0, 1fr) 18px;
    align-items: center;
    gap: 8px 12px;
    min-height: 64px;
    padding: 10px 12px;
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
    display: grid;
    gap: 5px;
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
    justify-content: space-between;
}

.historial-version small {
    color: #446075;
    font-size: .82rem;
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
