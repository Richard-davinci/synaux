<script setup>
import {computed, ref} from 'vue';
import AppButton from '../../Components/UI/AppButton.vue';
import PageTitle from '../../Components/UI/PageTitle.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';
import EmptyState from '../../Components/UI/EmptyState.vue';
import HistorialPresupuesto from '../../Components/Presupuestos/HistorialPresupuesto.vue';
import FiltrosRegistros from '../../Components/Registros/FiltrosRegistros.vue';
import DeleteEstimateButton from '../../Components/Estimates/DeleteEstimateButton.vue';
import NewBadge from '../../Components/Notifications/NewBadge.vue';
import {date, money} from '../../support/formatters';
import {presupuestosSinProyecto} from '../../support/agrupacionPresupuestos';

const props = defineProps({data: {type: Object, required: true}});
const seccion = computed(() => props.data.section);

// Cada listado conserva sus datos y acciones, con un encabezado y una estructura de filas comunes.
const listados = {
    estimaciones: {
        coleccion: 'estimates', titulo: 'Mis estimaciones', prefijo: 'EST',
        descripcion: 'Consultá tus cálculos y retomá las preparaciones guardadas. Cuando envíes una, pasará a Solicitudes para que synaux la revise.',
        vacio: 'Tus estimaciones y preparaciones guardadas aparecerán acá.',
        estados: [['calculated', 'Calculada'], ['manual', 'Revisión profesional'], ['preparation_saved', 'Preparación guardada']],
        crear: {href: '/solicitudes/nueva', etiqueta: 'Nueva estimación'},
    },
    solicitudes: {
        coleccion: 'requests', titulo: 'Mis solicitudes', prefijo: 'SOL', aviso: 'solicitud',
        descripcion: 'Revisá el estado de las solicitudes que ya enviaste a synaux.',
        vacio: 'Cuando envíes una solicitud, vas a poder seguir su revisión desde acá.',
        estados: [['open', 'Enviada'], ['closed', 'Cerrada'], ['cancelled', 'Cancelada']],
        crear: {href: '/solicitudes/nueva', etiqueta: 'Nueva estimación'},
    },
    presupuestos: {
        coleccion: 'quotes', titulo: 'Mis presupuestos', prefijo: 'PRE', aviso: 'presupuesto',
        descripcion: 'Revisá las propuestas y sus versiones. Los presupuestos aceptados se consultan desde Mis proyectos.',
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
const listado = computed(() => {
    const configuracion = listados[seccion.value];
    if (props.data.area === 'profesional' && props.data.auth?.rol_organizacion === 'profesional' && seccion.value === 'proyectos') {
        return {
            ...configuracion, descripcion: 'Consultá los proyectos que te asignó Gestión.',
            vacio: 'Los proyectos que te asigne Gestión aparecerán acá.'
        };
    }
    return configuracion;
});
const busqueda = ref('');
const estado = ref('');
const filtrosActivos = computed(() => Boolean(busqueda.value.trim() || estado.value));
const agrupaPresupuestos = computed(() => props.data.area === 'cliente' && seccion.value === 'presupuestos');
const muestraPreparaciones = computed(() => props.data.area === 'cliente' && seccion.value === 'estimaciones');
const ocultaBorradores = computed(() => props.data.area === 'cliente' && seccion.value === 'solicitudes');
const registros = computed(() => {
    const disponibles = props.data[listado.value.coleccion] ?? [];
    if (agrupaPresupuestos.value) return presupuestosSinProyecto(disponibles);
    if (muestraPreparaciones.value) {
        // Una preparación se presenta junto a las estimaciones y reemplaza su fila técnica vinculada.
        const borradores = (props.data.requests ?? []).filter(request => request.status === 'draft');
        const estimacionesEnPreparacion = new Set(borradores.map(request => request.estimate_id).filter(Boolean));
        const estimacionesSinPreparacion = disponibles.filter(estimate => !estimacionesEnPreparacion.has(estimate.id));
        const preparaciones = borradores.map(request => {
            const estimate = disponibles.find(item => item.id === request.estimate_id);
            return {
                ...(estimate ?? {}),
                id: estimate?.id ?? request.id,
                request_id: request.id,
                title: request.title,
                summary: request.title,
                service_name: request.service_name,
                service_id: request.service_id,
                status: 'preparation_saved',
                created_at: request.created_at,
                price_min: estimate?.price_min ?? request.pricing_reference?.min ?? null,
                price_max: estimate?.price_max ?? request.pricing_reference?.max ?? null,
                currency_snapshot: estimate?.currency_snapshot ?? request.pricing_reference?.currency ?? null,
            };
        });
        return [...preparaciones, ...estimacionesSinPreparacion].sort((a, b) =>
            new Date(b.created_at ?? 0) - new Date(a.created_at ?? 0));
    }
    return ocultaBorradores.value ? disponibles.filter(request => request.status !== 'draft') : disponibles;
});
const tienePresupuestosEnProyectos = computed(() => agrupaPresupuestos.value
    && (props.data.quotes ?? []).some(version => version.status === 'accepted'));
const opcionesEstado = computed(() => [
    {value: '', label: 'Todos'},
    ...listado.value.estados.filter(([value]) => !agrupaPresupuestos.value || value !== 'accepted')
        .map(([value, label]) => ({value, label})),
]);

function identificador(registro) {
    return listado.value.prefijo + '-' + (registro.budget_id ?? registro.id);
}

// La búsqueda acepta el título o el identificador tal como aparece en la fila.
const filas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();
    return registros.value.filter(registro => {
        // Un título o código anterior también permite encontrar la entrada que contiene el historial.
        const versiones = [registro, ...(registro.historial ?? [])];
        const contenido = versiones.flatMap(version => [version.title ?? version.summary, identificador(version), version.id,
            version.request_id && 'SOL-' + version.request_id]).filter(Boolean).join(' ').toLowerCase();
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

function destino(registro) {
    if (muestraPreparaciones.value && registro.request_id) return '/solicitudes/' + registro.request_id;
    return '/' + seccion.value + '/' + registro.id;
}

</script>

<template>
    <section class="page shell workspace-view listado-cliente">
        <PageTitle :eyebrow="data.area === 'profesional' ? 'Área profesional' : 'Área cliente'"
                   :title="listado.titulo" :description="listado.descripcion">
            <AppButton v-if="listado.crear" :href="listado.crear.href" arrow>{{ listado.crear.etiqueta }}</AppButton>
        </PageTitle>
        <FiltrosRegistros v-if="registros.length || filtrosActivos" v-model:buscar="busqueda" v-model:estado="estado"
                          :opciones-estado="opcionesEstado"
                          :etiqueta-estado="agrupaPresupuestos ? 'Estado actual' : 'Estado'" tarjeta/>

        <ul v-if="filas.length" class="card filas-registros" :aria-label="listado.titulo">
            <li v-for="registro in filas" :key="registro.claveGrupo ?? registro.id" class="fila-registro">
                <div class="fila-identidad">
                    <small>{{ identificador(registro) }} · {{ date(registro.sent_at ?? registro.created_at) }}</small>
                    <div class="record-heading">
                        <h2>{{ registro.title ?? registro.summary }}</h2>
                        <NewBadge v-if="listado.aviso" :type="listado.aviso" :id="registro.id"/>
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
                    <AppButton :href="destino(registro)" variant="ghost" compact>
                        {{
                            registro.request_id ? 'Continuar preparación' : agrupaPresupuestos ? 'Ver presupuesto' : 'Ver detalle'
                        }}
                    </AppButton>
                    <DeleteEstimateButton v-if="seccion === 'estimaciones' && !registro.request_id" :estimate="registro"
                                          compact/>
                </div>
                <HistorialPresupuesto v-if="registro.historial?.length" :versiones="registro.historial"
                                      class="historial-en-lista"/>
            </li>
        </ul>

        <EmptyState v-else-if="filtrosActivos" title="Sin resultados"
                    text="Probá con otro título, identificador o estado."/>
        <EmptyState v-else-if="tienePresupuestosEnProyectos" title="No hay presupuestos para revisar"
                    text="Consultá los presupuestos aceptados y sus versiones en Mis proyectos."
                    href="/cliente/proyectos" action="Ir a Mis proyectos"/>
        <EmptyState v-else :title="'Todavía no tenés ' + seccion" :text="listado.vacio"/>
    </section>
</template>

<style scoped>
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

/* El historial ocupa el ancho completo debajo del resumen de la fila. */
.historial-en-lista {
    grid-column: 1 / -1;
}

@media (max-width: 700px) {

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
