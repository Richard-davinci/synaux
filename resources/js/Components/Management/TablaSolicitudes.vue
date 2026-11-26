<script setup>
import AccionIcono from '../UI/AccionIcono.vue';
import B from '../UI/AppButton.vue';
import S from '../UI/StatusBadge.vue';
import NewBadge from '../Notifications/NewBadge.vue';
import {date} from '../../support/formatters';

// El resumen y el listado usan las mismas columnas para comparar solicitudes.
const props = defineProps({rows: Array, quotes: {type: Array, default: () => []}, label: String, preview: Boolean});

// Las filas por revisar y las ya revisadas abren el mismo detalle con acciones diferenciadas.
const porRevisar = item => item.status === 'open' && !item.reviewed_at;

// El estado visible sigue el siguiente paso real: revisar, preparar el presupuesto o continuar su borrador.
function presupuestoDe(item) {
    return props.quotes.filter(quote => Number(quote.request_id) === Number(item.id))
        .sort((a, b) => Number(b.version_number) - Number(a.version_number) || Number(b.id) - Number(a.id))[0] ?? null;
}

function etapa(item) {
    const presupuesto = presupuestoDe(item);
    if (presupuesto) {
        const estado = presupuesto.display_status ?? presupuesto.status;
        const etiquetas = {
            draft: 'Presupuesto en preparación', sent: 'Presupuesto enviado', issued: 'Presupuesto emitido',
            accepted: 'Presupuesto aceptado', rejected: 'Presupuesto rechazado', expired: 'Presupuesto vencido',
        };
        return {status: estado, etiqueta: etiquetas[estado] ?? estado};
    }
    if (item.status === 'open' && item.reviewed_at) return {status: 'review', etiqueta: 'Pendiente de presupuesto'};
    if (item.status === 'open') return {status: 'open', etiqueta: 'Pendiente de revisión'};
    return {status: item.status};
}

// La acción conduce al paso vigente y evita crear un segundo presupuesto si ya existe uno.
function accion(item) {
    const presupuesto = presupuestoDe(item);
    if (presupuesto?.status === 'draft') return {
        href: '/editor/presupuesto/' + presupuesto.id, texto: 'Continuar borrador', principal: true,
    };
    const estadoPresupuesto = presupuesto?.display_status ?? presupuesto?.status;
    if (presupuesto && ['rejected', 'expired'].includes(estadoPresupuesto)) return {
        href: '/editor/presupuesto/' + presupuesto.id, texto: 'Preparar nueva versión', principal: true,
    };
    if (presupuesto) return {href: '/presupuestos/' + presupuesto.id, texto: 'Ver presupuesto'};
    if (item.status === 'open' && item.reviewed_at) return {
        href: '/editor/presupuesto?solicitud=' + item.id, texto: 'Preparar presupuesto', principal: true,
    };
    return null;
}

function detailHref(item) {
    return props.preview ? '/prototipo/gestion-solicitud' : '/solicitudes/' + item.id;
}
</script>

<template>
    <div class="table-scroll workspace-table request-table" role="region" :aria-label="label" tabindex="0">
        <table :aria-label="label">
            <thead>
            <tr>
                <th scope="col">Solicitud</th>
                <th scope="col" class="request-table-secondary">Cliente</th>
                <th scope="col" class="request-table-secondary">Fecha</th>
                <th scope="col">Estado</th>
                <th scope="col" class="request-table-secondary">Acción</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in rows" :key="item.id">
                <th scope="row">
                        <span class="request-table-title">
                            <span class="request-table-desktop-title">{{ item.title }}</span>
                            <a :href="detailHref(item)" class="request-table-mobile-title">{{ item.title }}</a>
                            <NewBadge type="solicitud" :id="item.id"/>
                        </span>
                    <small>SOL-{{ item.id }} · {{ item.service_name }}</small>
                    <small class="request-table-mobile-meta">{{
                            item.contact?.company || item.contact?.name || 'Cliente'
                        }} · {{ date(item.created_at) }}</small>
                </th>
                <td class="request-table-secondary">
                    {{ item.contact?.company || item.contact?.name || 'Cliente' }}
                    <small v-if="item.contact?.company && item.contact.name">{{ item.contact.name }}</small>
                </td>
                <td class="request-table-secondary request-table-date">{{ date(item.created_at) }}</td>
                <td>
                    <S :status="etapa(item).status" :request="!presupuestoDe(item)" :etiqueta="etapa(item).etiqueta"/>
                </td>
                <td class="request-table-secondary">
                    <B v-if="accion(item)" compact :variant="accion(item).principal ? 'primary' : 'secondary'"
                       :href="props.preview ? detailHref(item) : accion(item).href" arrow>
                        {{ props.preview ? 'Revisar solicitud' : accion(item).texto }}
                    </B>
                    <AccionIcono v-else :href="detailHref(item)" :icono="porRevisar(item) ? 'file' : 'eye'"
                                 :texto="porRevisar(item) ? 'Revisar solicitud' : 'Ver detalle'"
                                 :etiqueta="(porRevisar(item) ? 'Revisar solicitud ' : 'Ver detalle de ') + item.title"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
/* La tabla conserva sus columnas y se desplaza dentro del panel cuando falta ancho. */
.request-table {
    min-width: 0;
}

.request-table:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

.request-table table {
    min-width: 690px;
}

.request-table tbody th {
    width: 38%;
    color: #153b64;
    background: #fff;
    font-size: .95rem;
    font-weight: 700;
}

.request-table tbody th, .request-table td {
    vertical-align: middle;
    padding: 12px 16px;
}

.request-table tbody tr:last-child th {
    border-bottom: 0;
}

.request-table tbody tr:hover > :is(th, td) {
    background: #f5f9fa;
}

.request-table small {
    display: block;
    margin-top: 4px;
    color: #446075;
    font-weight: 500;
    font-size: .8125rem;
}

.request-table-title {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    overflow-wrap: anywhere;
}

.request-table-date {
    white-space: nowrap;
}

.request-table .request-table-mobile-title, .request-table .request-table-mobile-meta {
    display: none;
}

@media (max-width: 720px) {
    .request-table table {
        min-width: 0;
    }

    .request-table .request-table-secondary, .request-table .request-table-desktop-title {
        display: none;
    }

    .request-table .request-table-mobile-title {
        display: inline-block;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .request-table .request-table-mobile-meta {
        display: block;
    }

    .request-table tbody th {
        width: auto;
    }

    .request-table tbody th, .request-table td {
        padding: 10px 12px;
    }
}
</style>
