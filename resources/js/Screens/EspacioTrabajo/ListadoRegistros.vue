<script setup>
import {computed, ref} from 'vue';
import AppButton from '../../Components/UI/AppButton.vue';
import FormField from '../../Components/UI/FormField.vue';
import PageTitle from '../../Components/UI/PageTitle.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';
import EmptyState from '../../Components/UI/EmptyState.vue';
import DeleteEstimateButton from '../../Components/Estimates/DeleteEstimateButton.vue';
import NewBadge from '../../Components/Notifications/NewBadge.vue';
import {date, money} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});
const seccion = computed(() => props.data.section);

// Cada listado conserva sus datos y acciones, con un encabezado y una estructura de filas comunes.
const listados = {
    estimaciones: {
        coleccion: 'estimates', titulo: 'Mis estimaciones', prefijo: 'EST',
        descripcion: 'Consultá y retomá las estimaciones que guardaste.',
        vacio: 'Guardá una estimación para continuar con tu solicitud más adelante.',
        estados: [['calculated', 'Calculada'], ['manual', 'Revisión profesional']],
        crear: {href: '/estimador', etiqueta: 'Nueva estimación'},
    },
    solicitudes: {
        coleccion: 'requests', titulo: 'Mis solicitudes', prefijo: 'SOL', aviso: 'solicitud',
        descripcion: 'Revisá el estado de tus pedidos y retomá tus borradores.',
        vacio: 'Tus borradores y las solicitudes que envíes aparecerán acá.',
        estados: [['draft', 'Borrador'], ['open', 'Enviada'], ['closed', 'Cerrada'], ['cancelled', 'Cancelada']],
        crear: {href: '/solicitudes/nueva', etiqueta: 'Nueva solicitud'},
    },
    presupuestos: {
        coleccion: 'quotes', titulo: 'Mis presupuestos', prefijo: 'PRE', aviso: 'presupuesto',
        descripcion: 'Revisá las propuestas, sus importes y sus versiones.',
        vacio: 'Tus presupuestos aparecerán acá cuando estén disponibles.',
        estados: [['draft', 'Borrador'], ['sent', 'Enviado'], ['accepted', 'Aceptado'], ['rejected', 'Rechazado'], ['expired', 'Vencido']],
    },
    proyectos: {
        coleccion: 'projects', titulo: 'Mis proyectos', prefijo: 'PRY', aviso: 'proyecto',
        descripcion: 'Consultá el avance y los próximos pasos de cada proyecto.',
        vacio: 'Acá vas a poder seguir los proyectos que hayan comenzado a partir de un presupuesto aceptado.',
        estados: [['pending', 'Pendiente de inicio'], ['in_progress', 'En desarrollo'], ['completed', 'Completado'], ['cancelled', 'Cancelado']],
    },
};
const listado = computed(() => listados[seccion.value]);
const busqueda = ref('');
const estado = ref('');
const filtrosActivos = computed(() => Boolean(busqueda.value.trim() || estado.value));
const registros = computed(() => props.data[listado.value.coleccion] ?? []);
const opcionesEstado = computed(() => [
    {value: '', label: 'Todos'},
    ...listado.value.estados.map(([value, label]) => ({value, label})),
]);

function identificador(registro) {
    return listado.value.prefijo + '-' + (registro.budget_id ?? registro.id);
}

// La búsqueda acepta el título o el identificador tal como aparece en la fila.
const filas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();
    return registros.value.filter(registro => {
        const contenido = [registro.title ?? registro.summary, identificador(registro), registro.id].join(' ').toLowerCase();
        return contenido.includes(texto) && (!estado.value || (registro.display_status ?? registro.status) === estado.value);
    });
});

function detalle(registro) {
    if (seccion.value === 'presupuestos') {
        return 'Versión ' + registro.version_number + ' · ' + money(registro.total, registro.currency_code);
    }
    if (seccion.value !== 'estimaciones') return registro.service_name;
    if (registro.price_min == null) return 'Importe a cotizar';

    const minimo = money(registro.price_min, registro.currency_snapshot);
    return registro.price_max == null || Number(registro.price_min) === Number(registro.price_max)
        ? minimo
        : minimo + ' – ' + money(registro.price_max, registro.currency_snapshot);
}

function limpiarFiltros() {
    busqueda.value = '';
    estado.value = '';
}
</script>

<template>
    <section class="page shell workspace-view listado-cliente">
        <PageTitle :eyebrow="data.area === 'profesional' ? 'Área profesional' : 'Área cliente'"
                   :title="listado.titulo" :description="listado.descripcion">
            <AppButton v-if="listado.crear" :href="listado.crear.href" arrow>{{ listado.crear.etiqueta }}</AppButton>
        </PageTitle>
        <div v-if="registros.length || filtrosActivos" class="card filters workspace-filters filtros-registros">
            <FormField v-model="busqueda" label="Buscar" placeholder="Título o identificador"/>
            <FormField v-model="estado" label="Estado" type="select" :options="opcionesEstado"/>
            <AppButton variant="secondary" :disabled="!filtrosActivos" @click="limpiarFiltros">Limpiar filtros</AppButton>
        </div>

        <ul v-if="filas.length" class="card filas-registros" :aria-label="listado.titulo">
            <li v-for="registro in filas" :key="registro.id" class="fila-registro">
                <div class="fila-identidad">
                    <small>{{ identificador(registro) }} · {{ date(registro.created_at) }}</small>
                    <div class="record-heading">
                        <h2>{{ registro.title ?? registro.summary }}</h2>
                        <NewBadge :type="listado.aviso" :id="registro.id"/>
                    </div>
                    <p v-if="detalle(registro)">{{ detalle(registro) }}</p>
                    <div v-if="seccion === 'proyectos'" class="fila-avance">
                        <span>Avance · {{ registro.progress }}%</span>
                        <progress max="100" :value="registro.progress" :aria-label="'Avance de ' + registro.title"/>
                        <p v-if="registro.next_steps">{{ registro.next_steps }}</p>
                    </div>
                </div>
                <StatusBadge :status="registro.display_status ?? registro.status" :request="seccion === 'solicitudes'"/>
                <div class="fila-acciones">
                    <AppButton :href="'/' + seccion + '/' + registro.id" variant="ghost" compact>
                        {{ registro.status === 'draft' && seccion === 'solicitudes' ? 'Continuar borrador' : 'Ver detalle' }}
                    </AppButton>
                    <DeleteEstimateButton v-if="seccion === 'estimaciones'" :estimate="registro" compact/>
                </div>
            </li>
        </ul>

        <EmptyState v-else-if="filtrosActivos" title="Sin resultados" text="Probá con otro título, identificador o estado."/>
        <EmptyState v-else :title="'Todavía no tenés ' + seccion" :text="listado.vacio"/>
    </section>
</template>

<style scoped>
/* Los filtros son compactos y todos los registros forman una sola lista, como en el resumen. */
.listado-cliente .filtros-registros {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
    align-items: end;
}

.listado-cliente .filtros-registros > :deep(.field) {
    margin-bottom: 0;
}

.filas-registros {
    list-style: none;
    margin: 0;
    padding: 0 clamp(16px, 3vw, 26px);
}

.fila-registro {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(130px, auto) minmax(172px, auto);
    align-items: center;
    gap: 10px 16px;
    padding-block: 16px;
}

.fila-registro + .fila-registro {
    border-top: 1px solid #1f6f7f22;
}

.fila-identidad {
    min-width: 0;
    overflow-wrap: anywhere;
}

.fila-identidad small {
    color: #446075;
}

.listado-cliente .fila-identidad h2 {
    margin: 3px 0 0;
    font-size: 1.05rem;
    line-height: 1.3;
}

.fila-identidad p {
    margin: 4px 0 0;
}

.fila-avance {
    margin-top: 8px;
    max-width: 280px;
    color: #446075;
    font-size: .85rem;
}

.fila-avance progress {
    display: block;
    width: 100%;
    height: 8px;
    margin-top: 4px;
}

.fila-acciones {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 4px 8px;
}

@media (max-width: 700px) {
    .listado-cliente .filtros-registros {
        grid-template-columns: minmax(0, 1fr);
    }

    .fila-registro {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .fila-identidad {
        grid-column: 1 / -1;
    }
}

@media (max-width: 360px) {
    .fila-acciones {
        grid-column: 1 / -1;
        justify-content: flex-start;
    }
}
</style>
