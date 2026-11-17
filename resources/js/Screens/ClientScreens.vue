<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import T from '../Components/SynauxTitle.vue';
import S from '../Components/SynauxStatus.vue';
import E from '../Components/SynauxEmpty.vue';
import RequestForm from '../Components/SynauxRequestForm.vue';
import SynauxBudgetDetail from '../Components/SynauxBudgetDetail.vue';
import SynauxProjectDetail from '../Components/SynauxProjectDetail.vue';
import { money, date, useSubmit } from '../support/synaux';
// Las colecciones llegan filtradas por el servidor; el navegador solo organiza búsquedas y presentación.
const props = defineProps({ data: Object });
const submit = useSubmit();
const search = ref('');
const status = ref('');
const section = computed(() => props.data.screen === 'cliente' ? props.data.section || 'resumen' : props.data.screen);
const record = computed(() => props.data.selected ?? {});
const requests = computed(() => props.data.requests ?? []);
const quotes = computed(() => props.data.quotes ?? []);
const projects = computed(() => props.data.projects ?? []);
const estimates = computed(() => props.data.estimates ?? []);
const collections = computed(() => ({solicitudes:requests.value,presupuestos:quotes.value,proyectos:projects.value,estimaciones:estimates.value}));
const rows = computed(() => (collections.value[section.value] ?? []).filter(r => (!search.value || `${r.title ?? r.summary} ${r.id}`.toLowerCase().includes(search.value.toLowerCase())) && (!status.value || (r.display_status ?? r.status) === status.value)));
// Cada sección ofrece únicamente los estados que puede contener.
const statusOptions = computed(() => [{ value: '', label: 'Todos' }, ...({
    estimaciones: [['calculated', 'Calculada'], ['manual', 'Revisión profesional']],
    solicitudes: [['draft', 'Borrador'], ['open', 'Enviada'], ['closed', 'Cerrada'], ['cancelled', 'Cancelada']],
    presupuestos: [['draft', 'Borrador'], ['sent', 'Enviado'], ['accepted', 'Aceptado'], ['rejected', 'Rechazado'], ['expired', 'Vencido']],
    proyectos: [['pending', 'Pendiente de inicio'], ['in_progress', 'En desarrollo'], ['completed', 'Completado'], ['cancelled', 'Cancelado']],
}[section.value] ?? []).map(([value, label]) => ({ value, label }))]);
const titles = {resumen:'Tu espacio en Synaux',estimaciones:'Mis estimaciones',solicitudes:'Mis solicitudes',presupuestos:'Mis presupuestos',proyectos:'Mis proyectos',solicitud:'Detalle de solicitud',proyecto:'Tu proyecto',presupuesto:'Presupuesto formal'};
const stateForm = useForm({id:record.value.id,status:'closed'});
function closeRequest() { submit(stateForm,'estado-solicitud'); }
function href(row) { return `/${{solicitudes:'solicitudes',presupuestos:'presupuestos',proyectos:'proyectos',estimaciones:'estimaciones'}[section.value]}/${row.id}`; }
</script>
<template>
    <section :class="['page shell', {narrow:section === 'solicitud'}]">
        <!-- El resumen conserva los accesos y el orden de actividad del prototipo, con conteos persistentes. -->
        <template v-if="section === 'resumen'"><T eyebrow="Área cliente" :title="`Hola, ${$page.props.auth?.name?.split(' ')[0] ?? 'Martina'}`" description="Revisá tus solicitudes, presupuestos y proyectos en un solo lugar."><B href="/solicitudes/nueva" arrow>Nueva solicitud</B></T><div class="stats-grid"><v-card v-for="[label,value,link] in [['Estimaciones',estimates.length,'estimaciones'],['Solicitudes',requests.length,'solicitudes'],['Presupuestos',quotes.length,'presupuestos'],['Proyectos',projects.length,'proyectos']]" :key="label" class="card stat"><span>{{ label }}</span><strong>{{ value }}</strong><B variant="ghost" :href="`/cliente/${link}`">Ver {{ label.toLowerCase() }} →</B></v-card></div><div class="detail-layout"><v-card class="card"><div class="section-head"><h2>Actividad reciente</h2><B variant="ghost" href="/cliente/solicitudes">Ver todas</B></div><div v-for="r in requests.slice(0,3)" :key="r.id" class="list-row"><div><small>SOL-{{ r.id }} · {{ date(r.created_at) }}</small><h3>{{ r.title }}</h3><S :status="r.status" /></div><B :href="`/solicitudes/${r.id}`" variant="secondary">Ver detalle</B></div><E v-if="!requests.length" title="Todavía no tenés solicitudes" text="Empezá contando qué necesitás resolver." /></v-card><aside><v-card class="card soft-panel"><p class="eyebrow">Tu próximo paso</p><h3>De la idea al proyecto</h3><p>Estimá el alcance, creá una solicitud y revisá la propuesta formal antes de confirmar.</p><B href="/estimador" arrow>Estimar un proyecto</B></v-card></aside></div></template>
        <!-- Los listados comparten filtros; cada tarjeta conduce a su propio registro autorizado. -->
        <template v-else-if="['estimaciones','solicitudes','presupuestos','proyectos'].includes(section)"><T eyebrow="Área cliente" :title="titles[section]" description="Consultá el estado y los próximos pasos de cada proyecto."><B v-if="section === 'solicitudes'" href="/solicitudes/nueva" arrow>Nueva solicitud</B><B v-if="section === 'estimaciones'" href="/estimador" arrow>Nueva estimación</B></T><div class="filters"><F v-model="search" label="Buscar" placeholder="Título o identificador" /><F v-model="status" label="Estado" type="select" :options="statusOptions" /></div><div class="stack"><v-card v-for="r in rows" :key="r.id" class="card list-row"><div><small>{{ {estimaciones:'EST',solicitudes:'SOL',presupuestos:'PRE',proyectos:'PRY'}[section] }}-{{ r.id }} · {{ date(r.created_at) }}</small><h3>{{ r.title ?? r.summary }}</h3><p>{{ r.service_name ?? (section === 'presupuestos' ? `Versión ${r.version_number} · ${money(r.total,r.currency_code)}` : section === 'estimaciones' ? `${money(r.price_min,r.currency_snapshot)}${r.price_max != null ? ` – ${money(r.price_max,r.currency_snapshot)}` : ''}` : '') }}</p><S :status="r.display_status ?? r.status" /><template v-if="section === 'proyectos'"><p>Avance informado: {{ r.progress }}%</p><progress max="100" :value="r.progress" :aria-label="'Avance de ' + r.title" /><p v-if="r.next_steps">{{ r.next_steps }}</p></template></div><B :href="href(r)" variant="secondary">{{ r.status === 'draft' && section === 'solicitudes' ? 'Continuar borrador' : 'Ver detalle' }}</B></v-card></div><template v-if="!rows.length && (search || status)"><E title="No encontramos resultados" text="Probá con otro texto o estado, o quitá los filtros para ver todos los registros." /><B variant="secondary" @click="search = ''; status = ''">Limpiar filtros</B></template><E v-else-if="!rows.length" :title="`Todavía no tenés ${section}`" text="Cuando haya actividad, aparecerá acá con su estado actualizado." :href="section === 'estimaciones' ? '/estimador' : '/solicitudes/nueva'" :action="section === 'estimaciones' ? 'Crear una estimación' : 'Crear una nueva solicitud'" /></template>
        <template v-else-if="section === 'solicitud' && record.status === 'draft'"><T eyebrow="Solicitud en borrador" :title="record.title" description="Podés completar y revisar la información antes de enviarla." /><RequestForm :data="data" /></template>
        <template v-else-if="section === 'solicitud'"><B variant="ghost" href="/cliente/solicitudes">← Volver a solicitudes</B><T :eyebrow="`SOL-${record.id} · Solicitud`" :title="record.title"><S :status="record.status" /></T><div class="detail-layout"><v-card class="card"><h2>Sobre tu solicitud</h2><p class="preserve-lines">{{ record.description }}</p><dl class="data-list"><div><dt>Servicio</dt><dd>{{ record.service_name }}</dd></div><div><dt>Visibilidad</dt><dd>{{ record.visibility === 'public' ? 'Pública' : 'Privada' }}</dd></div><div><dt>Fecha de envío</dt><dd>{{ date(record.created_at) }}</dd></div><div><dt>Revisión</dt><dd>{{ record.reviewed_at ? date(record.reviewed_at) : 'Pendiente de revisión' }}</dd></div></dl><section v-if="record.contact"><h3>Contacto del pedido</h3><p>{{ record.contact.name }} · {{ record.contact.email }}</p><p>{{ record.contact.company }} {{ record.contact.phone }} {{ record.contact.address }}</p></section><section v-if="record.additional_services?.length"><h3>Servicios adicionales</h3><div v-for="item in record.additional_services" :key="item.service_id"><strong>{{ item.name }} × {{ item.quantity }}</strong><p>{{ item.notes }}</p></div></section><h3 v-if="record.answers?.length">Respuestas de la estimación</h3><dl class="data-list"><div v-for="a in record.answers" :key="a.id"><dt>{{ a.question }}</dt><dd>{{ a.label }}</dd></div></dl></v-card><aside><v-card class="card soft-panel"><h3>¿Qué sigue?</h3><template v-if="data.area === 'gestion'"><p>Revisá el alcance y las respuestas del cliente para preparar la propuesta formal.</p><B v-if="record.status === 'open'" :href="'/editor/presupuesto?solicitud=' + record.id" arrow>Preparar presupuesto</B><B href="/gestion/solicitudes" variant="secondary">Volver a Gestión</B></template><template v-else-if="record.visibility === 'private' || !$page.props.stageTwo"><p>Synaux revisará tu pedido y preparará un presupuesto con el alcance, el importe y los plazos.</p><B href="/cliente/presupuestos" variant="secondary">Consultar presupuestos</B></template><template v-else><p>Podés consultar las propuestas recibidas e invitar profesionales para tu proyecto.</p><B href="/cliente/propuestas" variant="secondary">Ver propuestas</B><B href="/profesionales" variant="ghost">Buscar profesionales</B></template></v-card><v-card v-if="record.status === 'open'" class="card"><h3>Gestionar solicitud</h3><p>Podés cerrarla si ya no necesitás continuar con el pedido.</p><B variant="secondary" :disabled="stateForm.processing" @click="closeRequest">Cerrar solicitud</B></v-card></aside></div></template>
        <!-- El documento y la decisión distinguen la versión vigente y las del historial. -->
        <SynauxBudgetDetail v-else-if="section === 'presupuesto'" :data="data" />
        <SynauxProjectDetail v-else-if="section === 'proyecto'" :data="data" />

    </section>
</template>

