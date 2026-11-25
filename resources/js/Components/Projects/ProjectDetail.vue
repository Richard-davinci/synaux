<script setup>
import {computed, ref} from 'vue';
import B from '../UI/AppButton.vue';
import T from '../UI/PageTitle.vue';
import S from '../UI/StatusBadge.vue';
import I from '../UI/AppIcon.vue';
import ProjectUpdate from './ProjectUpdate.vue';
import PagoProyecto from './PagoProyecto.vue';
import AsignacionProyecto from './AsignacionProyecto.vue';
import HistorialPresupuesto from '../Presupuestos/HistorialPresupuesto.vue';
import {date, money} from '../../support/formatters';

// El presupuesto y el historial llegan filtrados por la cuenta; el cliente consulta el seguimiento.
const props = defineProps({data: Object});
const project = computed(() => props.data.selected ?? {});
const budget = computed(() => props.data.quotes?.find(item => item.id === project.value.accepted_budget_version_id));
// La cuota usa el título y el momento acordados en el presupuesto aceptado.
const cuotasPorConcepto = computed(() => Object.fromEntries((budget.value?.plan_pago?.cuotas ?? []).map(cuota => [cuota.concepto, cuota])));
const listadoProyectos = computed(() => props.data.area === 'profesional' ? '/profesional/proyectos' : '/gestion/proyectos');
// Gestión reutiliza el formulario de seguimiento desde el detalle del proyecto.
const updating = ref(false);
const guidance = computed(() => ({
    pending: ['Coordinación del inicio', 'El presupuesto fue aceptado. synaux coordinará el inicio y registrará los próximos pasos.'],
    in_progress: ['Proyecto en desarrollo', 'Consultá los avances registrados y las próximas tareas acordadas.'],
    completed: ['Proyecto completado', 'El trabajo figura como completado. Conservás el presupuesto y el historial para consultarlos.'],
    cancelled: ['Proyecto cancelado', 'El proyecto figura como cancelado. Podés consultar el seguimiento y las condiciones del presupuesto aceptado.'],
}[project.value.status] ?? ['Seguimiento del proyecto', 'Consultá el estado y las actualizaciones del proyecto.']));
</script>

<template>
    <section class="page shell" :class="{ 'workspace-view': data.area === 'cliente' }">
        <div class="project-detail">
            <B v-if="data.area !== 'cliente'" :href="listadoProyectos"
               variant="ghost">← Volver a proyectos</B>
            <T :eyebrow="data.area === 'cliente' ? undefined : 'PRY-' + project.id + ' · Proyecto'" :title="project.title">
                <div class="button-row">
                    <S :status="project.status"/>
                    <B v-if="data.area === 'gestion' && (data.auth?.puede_gestionar ?? data.auth?.is_admin) && !data.preview"
                       @click="updating = true">Actualizar avance</B>
                </div>
            </T>
            <div class="project-guidance"><I :name="project.status === 'completed' ? 'check' : 'folder'"/>
                <div><strong>{{ guidance[0] }}</strong>
                    <p>{{ guidance[1] }}</p></div>
            </div>
            <div class="project-grid">
                <div class="project-main">
                    <AsignacionProyecto v-if="data.asignacion" :proyecto-id="project.id" :asignacion="data.asignacion"
                                        :usuario-id="data.auth?.id"/>
                    <v-card class="card">
                        <h2>Avance del proyecto</h2>
                        <div class="section-head"><span>Progreso informado</span><strong
                            class="project-percent">{{ project.progress }}%</strong></div>
                        <progress max="100" :value="project.progress" aria-label="Avance del proyecto"/>
                        <dl class="data-list">
                            <div>
                                <dt>Proyecto creado</dt>
                                <dd>{{ date(project.created_at) }}</dd>
                            </div>
                            <div>
                                <dt>Inicio</dt>
                                <dd>{{ project.start_date ? date(project.start_date) : 'A coordinar' }}</dd>
                            </div>
                            <div>
                                <dt>Fecha de cierre</dt>
                                <dd>{{ project.end_date ? date(project.end_date) : 'Sin registrar' }}</dd>
                            </div>
                        </dl>
                        <h3>Última actualización</h3>
                        <p class="preserve-lines">
                            {{ project.notes || 'Todavía no hay una actualización registrada por synaux.' }}</p>
                        <p v-if="project.updates?.length" class="project-help">Registrada el
                            {{ date(project.updates[0].created_at) }}</p>
                    </v-card>
                    <v-card v-if="data.pagos?.length" class="card pagos-proyecto">
                        <h2>Pagos del proyecto</h2>
                        <div v-if="budget?.plan_pago?.instrucciones">
                            <h3>Datos para la transferencia</h3>
                            <p class="preserve-lines">{{ budget.plan_pago.instrucciones }}</p>
                        </div>
                        <PagoProyecto v-for="pago in data.pagos" :key="pago.id" :pago="pago" :cuota="cuotasPorConcepto[pago.concepto]"
                                      :moneda="budget.currency_code" :proyecto-id="project.id"
                                      :puede-presentar="data.puedePresentarComprobante" :puede-revisar="data.puedeRevisarComprobante"/>
                    </v-card>
                    <v-card class="card next-steps"><p class="eyebrow">Qué sigue</p>
                        <h2>Próximos pasos</h2>
                        <p class="preserve-lines">{{
                                project.next_steps || (project.status === 'completed' ? 'El proyecto está completado. Consultá la última actualización para conocer el cierre.' : project.status === 'cancelled' ? 'No hay próximas tareas registradas para este proyecto.' : 'synaux registrará acá las próximas tareas y lo que necesite de tu parte.')
                            }}</p></v-card>
                    <v-card v-if="!data.alcanceAsignado" class="card">
                        <h2>Historial de avances</h2>
                        <p class="project-help">Cada actualización conserva el estado y el avance informado en ese
                            momento.</p>
                        <ol v-if="project.updates?.length" class="project-history">
                            <li v-for="update in project.updates" :key="update.id">
                                <div class="history-heading"><strong>{{ date(update.created_at) }} · {{
                                        update.progress
                                    }}%</strong><S :status="update.status"/></div>
                                <p v-if="update.notes" class="preserve-lines">{{ update.notes }}</p>
                                <div v-if="update.next_steps"><strong>Próximos pasos</strong>
                                    <p class="preserve-lines">{{ update.next_steps }}</p></div>
                                <p v-if="!update.notes && !update.next_steps">Se actualizaron los datos de seguimiento
                                    del proyecto.</p><small v-if="update.start_date || update.end_date">Inicio:
                                {{ date(update.start_date) }} · Cierre:
                                {{ update.end_date ? date(update.end_date) : 'Sin registrar' }}</small></li>
                        </ol>
                        <p v-else>Todavía no hay avances guardados en el historial. Las próximas actualizaciones
                            aparecerán acá.</p>
                    </v-card>
                </div>
                <aside>
                    <v-card v-if="data.alcanceAsignado" class="card project-budget">
                        <h2>Alcance acordado</h2>
                        <p>Entrega estimada: {{ data.alcanceAsignado.entrega }} días desde el inicio acordado.</p>
                        <h3>Entregables</h3>
                        <ul>
                            <li v-for="(item, indice) in data.alcanceAsignado.entregables" :key="indice">
                                {{ item.description }} <strong>× {{ item.quantity }}</strong>
                            </li>
                        </ul>
                    </v-card>
                    <v-card v-else class="card project-budget"><p class="eyebrow">Condiciones acordadas</p>
                        <h2>Presupuesto aceptado</h2>
                        <template v-if="budget"><p>PRE-{{ budget.budget_id }} · Versión {{ budget.version_number }}</p>
                            <strong class="project-total">{{ money(budget.total, budget.currency_code) }}</strong>
                            <p>Entrega estimada: {{ budget.estimated_delivery_days }} días desde el inicio acordado.</p>
                            <h3>Servicios incluidos</h3>
                            <ul>
                                <li v-for="(item, index) in budget.items" :key="index">{{ item.description }} <strong>×
                                    {{ item.quantity }}</strong></li>
                            </ul>
                        </template>
                        <p>El proyecto conserva la versión y las condiciones que aceptaste.</p>
                        <div class="button-stack"><B
                            :href="data.preview ? '/prototipo/presupuesto-aceptado' : '/presupuestos/' + project.accepted_budget_version_id"
                            variant="secondary">Consultar presupuesto</B><B v-if="!data.preview"
                                                                            :href="'/presupuestos/' + project.accepted_budget_version_id + '/pdf'"
                                                                            variant="secondary">Descargar presupuesto
                            PDF</B></div>
                        <HistorialPresupuesto v-if="data.historialPresupuestos?.length" class="historial-del-proyecto"
                                              :versiones="data.historialPresupuestos" titulo="Historial del presupuesto"/>
                    </v-card>
                </aside>
            </div>
        </div>
        <ProjectUpdate v-if="updating" :project="project" @close="updating = false"/>
    </section>
</template>

<style scoped>
/* El seguimiento y las condiciones se leen en columnas amplias y se apilan en móvil. */
.historial-del-proyecto { margin-top: 16px; }
.pagos-proyecto { display: grid; gap: 12px; }
.pagos-proyecto h3 { font-size: .95rem; }
.project-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.project-grid > *, .project-main {
    min-width: 0;
}

.project-main {
    display: grid;
    gap: 24px;
}

.project-grid aside {
    position: sticky;
    top: calc(var(--navbar-height, 76px) + var(--workspace-bar-height, 0px) + 16px);
}

.project-detail h2 {
    font-size: clamp(1.4rem, 2.3vw, 1.9rem);
    font-weight: 700;
}

.project-detail h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-top: 22px;
}

.project-guidance {
    display: flex;
    gap: 14px;
    padding: 20px;
    border: 1px solid #1f6f7f33;
    background: #f5f9fa;
    border-radius: 14px;
    margin: 24px 0;
}

.project-guidance .icon {
    width: 26px;
    height: 26px;
    flex: 0 0 26px;
    color: #1f6f7f;
}

.project-guidance strong {
    font-size: 1.1rem;
}

.project-guidance p {
    margin: 6px 0 0;
}

.project-percent {
    font-size: 1.8rem;
    font-family: 'Space Grotesk', sans-serif;
}

.project-detail progress {
    width: 100%;
    height: 14px;
}

.project-help {
    font-size: .9rem;
    color: #446075;
}

.next-steps {
    background: #f5f9fa;
}

.project-history {
    padding: 0;
    list-style: none;
    margin: 20px 0 0;
}

.project-history li {
    border-left: 3px solid #2db3a3;
    padding: 0 0 22px 18px;
    margin-top: 20px;
    overflow-wrap: anywhere;
}

.history-heading {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.project-history small {
    color: #446075;
}

.project-total {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.8rem;
    overflow-wrap: anywhere;
}

.project-budget ul {
    padding-left: 18px;
    margin-bottom: 20px;
}

.project-budget li {
    margin: 12px 0;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

@media (max-width: 900px) {
    .historial-del-proyecto { margin-top: 16px; }
.pagos-proyecto { display: grid; gap: 12px; }
.pagos-proyecto h3 { font-size: .95rem; }
.project-grid {
        grid-template-columns: 1fr;
    }

    .project-grid aside {
        position: static;
    }
}
</style>
