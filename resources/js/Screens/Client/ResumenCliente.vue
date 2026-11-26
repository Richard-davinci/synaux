<script setup>
import {computed} from 'vue';
import {usePage} from '@inertiajs/vue3';
import AppButton from '../../Components/UI/AppButton.vue';
import PageTitle from '../../Components/UI/PageTitle.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';
import IndicadoresPanel from '../../Components/Navigation/IndicadoresPanel.vue';
import NewBadge from '../../Components/Notifications/NewBadge.vue';
import {date} from '../../support/formatters';
import {presupuestosSinProyecto} from '../../support/agrupacionPresupuestos';

// El resumen muestra las colecciones propias que Laravel reunió para esta cuenta.
const props = defineProps({data: {type: Object, required: true}});
const page = usePage();
const nombre = computed(() => page.props.auth?.name?.trim().split(/\s+/)[0] || (props.data.preview ? 'Martina' : ''));
const preparaciones = computed(() => (props.data.requests ?? []).filter(request => request.status === 'draft'));
const estimacionesEnPreparacion = computed(() => new Set(preparaciones.value.map(request => request.estimate_id).filter(Boolean)));
const indicadores = computed(() => [
    ['Estimaciones', 'estimaciones', 'edit', (props.data.estimates ?? []).filter(estimate => !estimacionesEnPreparacion.value.has(estimate.id)).length + preparaciones.value.length],
    ['Solicitudes', 'solicitudes', 'file', (props.data.requests ?? []).filter(request => request.status !== 'draft').length],
    ['Presupuestos', 'presupuestos', 'receipt', presupuestosSinProyecto(props.data.quotes ?? []).length],
    ['Proyectos', 'proyectos', 'folder', props.data.projects?.length ?? 0],
]);
const recientes = computed(() => (props.data.requests ?? []).filter(request => request.status !== 'draft').slice(0, 3));
</script>

<template>
    <section class="page shell workspace-view">
        <PageTitle eyebrow="Área cliente" :title="nombre ? 'Hola, ' + nombre : 'Resumen'"
                   description="Revisá tus solicitudes, presupuestos y proyectos.">
            <AppButton href="/solicitudes/nueva" arrow>Nueva estimación</AppButton>
        </PageTitle>

        <IndicadoresPanel :indicadores="indicadores" ruta-base="/cliente" :contexto="data"/>

        <!-- La actividad presenta solicitudes recientes y distingue la acción de los borradores. -->
        <section class="card solicitudes-recientes" aria-labelledby="titulo-solicitudes-recientes">
            <div class="section-head">
                <h2 id="titulo-solicitudes-recientes">Solicitudes recientes</h2>
                <AppButton v-if="recientes.length" compact variant="secondary" href="/cliente/solicitudes">
                    Ver todas
                </AppButton>
            </div>
            <ul v-if="recientes.length" class="lista-recientes">
                <li v-for="solicitud in recientes" :key="solicitud.id">
                    <div class="solicitud-reciente-identidad">
                        <small>SOL-{{ solicitud.id }} · {{ date(solicitud.created_at) }}</small>
                        <div class="record-heading">
                            <h3>{{ solicitud.title }}</h3>
                            <NewBadge type="solicitud" :id="solicitud.id"/>
                        </div>
                    </div>
                    <StatusBadge :status="solicitud.status" request/>
                    <AppButton :href="'/solicitudes/' + solicitud.id" variant="ghost" compact
                               :aria-label="(solicitud.status === 'draft' ? 'Continuar borrador de ' : 'Ver detalle de ') + solicitud.title">
                        {{ solicitud.status === 'draft' ? 'Continuar borrador' : 'Ver detalle' }}
                    </AppButton>
                </li>
            </ul>
            <p v-else class="sin-solicitudes">Todavía no enviaste solicitudes. Las preparaciones guardadas están en Estimaciones.</p>
        </section>
    </section>
</template>

<style scoped>
/* El resumen conserva una tarjeta de actividad compacta y apila cada fila en pantallas pequeñas. */
.solicitudes-recientes {
    padding: clamp(16px, 3vw, 26px);
}

.solicitudes-recientes .section-head {
    flex-flow: row wrap;
    align-items: center;
    gap: 8px 12px;
    margin-bottom: 12px;
}

.lista-recientes {
    list-style: none;
    padding: 0;
    margin: 0;
}

.lista-recientes > li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(104px, auto) minmax(146px, auto);
    align-items: center;
    gap: 10px 16px;
    padding-block: 14px;
    border-top: 1px solid #1f6f7f22;
}

.lista-recientes > li:last-child {
    padding-bottom: 0;
}

.solicitud-reciente-identidad {
    min-width: 0;
    overflow-wrap: anywhere;
}

.solicitud-reciente-identidad small {
    color: #446075;
}

.solicitudes-recientes .record-heading h3 {
    margin: 3px 0 0;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
}

.lista-recientes > li > .btn {
    justify-self: end;
}

.sin-solicitudes {
    margin: 0;
    color: #446075;
}

@media (max-width: 600px) {
    .lista-recientes > li {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .solicitud-reciente-identidad {
        grid-column: 1 / -1;
    }
}
</style>
