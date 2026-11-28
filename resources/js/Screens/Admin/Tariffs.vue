<script setup>
import {computed, onMounted, ref} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import AccionIcono from '../../Components/UI/AccionIcono.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// El tarifario nuevo se carga en USD y guarda la cotización CCL que permite explicar su equivalente en pesos.
const props = defineProps({data: Object});
const administration = computed(() => props.data.administration);
const tarifarios = computed(() => administration.value.tariffs);
const page = usePage();
const panelOpen = ref(false);
const servicioAbierto = ref(null);
const servicioFocalId = ref(null);
const tarifaOrigen = ref(null);
const submit = useSubmit();
const tarifario = useForm({name: '', currency_code: 'USD', ccl_rate: '', active: false, services: [], components: [], segments: []});
const cotizacionCcl = computed(() => Number(tarifario.ccl_rate) || 0);
const serviciosAutomaticos = computed(() => administration.value.services.filter(service =>
    service.estimation_mode === 'automatic'));
const componentesActivos = computed(() => administration.value.components.filter(component => component.active
    && component.slug !== 'segmento-tarifario-2026-11'));
const filasEsperadas = computed(() => tarifario.segments.length + tarifario.components.length);
const filasCompletas = computed(() => [...tarifario.segments, ...tarifario.components]
    .filter(row => row.min !== '' && row.min != null && row.max !== '' && row.max != null).length);
const erroresFormulario = computed(() => Object.entries(tarifario.errors).map(([field, message]) => ({field, message})));
const servicioFocal = computed(() => tarifario.services.find(servicio => Number(servicio.id) === Number(servicioFocalId.value)));

// Una versión anterior en USD sirve de plantilla; las versiones históricas en ARS no se reinterpretan como dólares.
function prepararVersion(origen = {}, servicioId = null) {
    const reutilizar = origen.currency_code === 'USD';
    tarifaOrigen.value = origen.id ? origen : null;
    tarifario.name = '';
    tarifario.currency_code = 'USD';
    tarifario.ccl_rate = reutilizar ? (origen.ccl_rate ?? '') : '';
    tarifario.active = false;
    tarifario.services = serviciosAutomaticos.value.map(servicio => {
        const precio = origen.services?.find(item => item.service_id === servicio.id);
        return {id: servicio.id, name: servicio.name, min: reutilizar ? (precio?.price_min ?? 0) : 0, max: reutilizar ? (precio?.price_max ?? 0) : 0};
    });
    tarifario.components = componentesActivos.value.map(componente => {
        const precio = origen.components?.find(item => item.service_component_id === componente.id);
        return {id: componente.id, name: componente.name, min: reutilizar ? (precio?.price_min ?? '') : '', max: reutilizar ? (precio?.price_max ?? '') : ''};
    });
    tarifario.segments = serviciosAutomaticos.value.flatMap(servicio => administration.value.clientSegments.map(segmento => {
        const precio = origen.segments?.find(item => item.service_id === servicio.id && item.estimator_option_id === segmento.id);
        return {service_id: servicio.id, service_name: servicio.name, option_id: segmento.id, label: segmento.label,
            min: reutilizar ? (precio?.price_min ?? '') : '', max: reutilizar ? (precio?.price_max ?? '') : '',
            fixed: !reutilizar || Number(precio?.price_min) === Number(precio?.price_max)};
    }));
    servicioFocalId.value = servicioId;
    servicioAbierto.value = servicioId ?? tarifario.services[0]?.id ?? null;
    panelOpen.value = true;
}

// El acceso desde un servicio abre una copia de la tarifa vigente enfocada en ese servicio.
onMounted(() => {
    const serviceId = Number(new URLSearchParams(page.url.split('?')[1] ?? '').get('servicio'));
    if (!Number.isInteger(serviceId) || serviceId <= 0) return;

    const servicio = serviciosAutomaticos.value.find(item => Number(item.id) === serviceId);
    if (!servicio) return;

    const vigente = tarifarios.value.find(item => item.id == administration.value.currentTariffId);
    prepararVersion(vigente ?? {}, serviceId);
    tarifario.name = vigente?.currency_code === 'USD' && vigente.name
        ? `${vigente.name} · ${servicio.name}`.slice(0, 255)
        : `Precios de ${servicio.name}`.slice(0, 255);
});

function equivalente(amount) {
    if (amount === '' || amount == null) return 'Ingresá el importe';
    return cotizacionCcl.value && Number.isFinite(Number(amount)) ? money(Number(amount) * cotizacionCcl.value, 'ARS') : 'Ingresá la cotización CCL';
}

// Conserva la estructura de rangos; un precio fijo guarda el mismo importe como mínimo y máximo.
function establecerPrecioFijo(precio, fijo) {
    precio.fixed = fijo;
    if (fijo) precio.max = precio.min;
}

function preciosCargadosDelServicio(servicioId) {
    const precios = tarifario.segments.filter(precio => precio.service_id === servicioId);
    const completos = precios.filter(precio => precio.min !== '' && precio.min != null && precio.max !== '' && precio.max != null).length;
    return `${completos} de ${precios.length} tipos con precio`;
}

function guardarTarifario() {
    submit(tarifario, '/admin/guardar/tarifario', {onSuccess: () => { panelOpen.value = false; }});
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Tarifarios"
           description="Cargá tus precios en dólares y registrá el CCL usado para ver su equivalente en pesos.">
            <B v-if="data.auth?.is_admin" compact href="/admin/etapa-2" variant="secondary">Revisar etapa 2</B>
        </T>
        <div class="workspace-tariffs">
            <div class="section-head">
                <div><h2>Versiones del tarifario</h2>
                    <p>Los cambios se aplican a las nuevas estimaciones. Los importes anteriores se conservan.</p></div>
                <B @click="prepararVersion()">＋ Nueva versión</B>
            </div>
            <div class="table-scroll workspace-table">
                <table>
                    <thead><tr><th>Nombre</th><th>Moneda y referencia</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                    <tr v-for="version in tarifarios" :key="version.id">
                        <td>{{ version.name }}</td>
                        <td>{{ version.currency_code }}<span v-if="version.ccl_rate"> · CCL {{ money(version.ccl_rate, 'ARS') }}</span></td>
                        <td><S :status="version.active ? 'enabled' : 'disabled'"/></td>
                        <td><AccionIcono icono="copy" texto="Crear desde esta versión"
                                         :etiqueta="'Crear versión a partir de ' + version.name"
                                         @click="prepararVersion(version)"/></td>
                    </tr>
                    <tr v-if="!tarifarios.length"><td colspan="4">Todavía no hay versiones de precios.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="notice"><div><h3>Precios y cálculos históricos</h3>
                <p>Una nueva versión no modifica estimaciones ni presupuestos ya preparados. El equivalente en ARS usa el CCL guardado en esa versión.</p></div></div>
        </div>

        <SidePanel title="Nueva versión de precios" v-model="panelOpen" max-width="700" :persistent="tarifario.processing">
            <div class="synaux-app">
                <v-card class="card modal-card tariff-form-card">
                    <form id="formulario-tarifario" @submit.prevent="guardarTarifario">
                        <div v-if="erroresFormulario.length" class="notice tariff-errors" role="alert">
                            <p v-for="error in erroresFormulario" :key="error.field">{{ error.message }}</p>
                        </div>
                        <div class="tariff-status-summary" :class="{'tariff-status-ready': filasEsperadas > 0 && filasCompletas === filasEsperadas}">
                            <strong>{{ filasCompletas }} de {{ filasEsperadas }} precios cargados</strong>
                            <span v-if="filasEsperadas > 0 && filasCompletas === filasEsperadas">Ya están todos los precios. Podés activar esta versión para las nuevas estimaciones.</span>
                            <span v-else>Completá los precios de cada tipo de cliente<span v-if="componentesActivos.length"> y los trabajos adicionales con costo</span> para completar esta versión.</span>
                        </div>
                        <div v-if="servicioFocal" class="notice tariff-target-context">
                            <strong>Estás editando los precios de {{ servicioFocal.name }}.</strong>
                            <span v-if="tarifaOrigen?.currency_code === 'USD'">Se crea una versión nueva con los demás precios vigentes precargados. Podés revisar o cambiar cualquier otro servicio antes de guardar.</span>
                            <span v-else-if="tarifaOrigen">La tarifa vigente está en {{ tarifaOrigen.currency_code }}. Sus importes no se convierten; completá los precios en USD para esta versión.</span>
                            <span v-else>No hay una tarifa vigente para precargar. Completá los precios de los servicios que quieras incluir en esta versión.</span>
                        </div>
                        <section class="tariff-section">
                            <div class="tariff-section-heading">
                                <h2>Datos de la versión</h2>
                                <span><strong>*</strong> Campos obligatorios</span>
                            </div>
                            <div class="tariff-fields">
                                <F v-model="tarifario.name" label="Nombre de la versión" required maxlength="255"/>
                                <F v-model="tarifario.ccl_rate" label="CCL · ARS/USD" type="number"
                                   min="0.0001" step="0.0001" required hint="Cotización CCL de venta."/>
                            </div>
                            <div class="notice tariff-conversion"><span>Moneda de carga: <strong>USD</strong></span>
                                <span v-if="cotizacionCcl">Con esta cotización, USD 1 equivale a {{ money(cotizacionCcl, 'ARS') }}.</span>
                                <span v-else>La conversión a pesos aparece cuando ingreses el CCL.</span></div>
                            <div class="tariff-activation" :class="{'tariff-activation-on': tarifario.active}">
                                <div class="tariff-activation-copy">
                                    <strong>¿Cuándo se usa esta versión?</strong>
                                    <span v-if="tarifario.active">Se aplicará a las próximas estimaciones de los servicios automáticos. Las estimaciones y presupuestos anteriores conservan sus importes.</span>
                                    <span v-else>Quedará como borrador y no se usará para calcular nuevas estimaciones.</span>
                                </div>
                                <label class="tariff-activation-toggle">
                                    <input v-model="tarifario.active" type="checkbox">
                                    <span>{{ tarifario.active ? 'Activar para nuevas estimaciones' : 'Dejar como borrador' }}</span>
                                </label>
                            </div>
                            <p v-if="tarifario.errors.active" class="field-error" role="alert">{{ tarifario.errors.active }}</p>
                        </section>

                        <section class="tariff-section">
                            <div class="tariff-heading"><div><h2>Precios por servicio y tipo de cliente</h2>
                                <p>Cada sección corresponde a un servicio. Definí su precio para cada tipo de cliente; si tiene trabajos adicionales configurados, aparecerán aparte.</p></div></div>
                            <p v-if="!administration.clientSegments.length" class="notice">Primero configurá la pregunta general «¿Para qué tipo de cliente?» con sus variantes.</p>
                            <div class="tariff-services">
                                <article v-for="servicio in tarifario.services" :key="servicio.id" class="tariff-service"
                                         :class="{'tariff-service-open': servicioAbierto === servicio.id}">
                                    <button type="button" class="tariff-service-toggle" :aria-expanded="servicioAbierto === servicio.id"
                                            @click="servicioAbierto = servicioAbierto === servicio.id ? null : servicio.id">
                                        <span class="tariff-service-name">{{ servicio.name }}</span>
                                        <span class="tariff-service-meta">
                                            <span>{{ preciosCargadosDelServicio(servicio.id) }}</span>
                                            <span class="tariff-service-chevron" aria-hidden="true">⌄</span>
                                        </span>
                                    </button>
                                    <div v-if="servicioAbierto === servicio.id" class="tariff-service-content">
                                        <div class="tariff-segment-header">
                                            <span>Tipo de cliente</span><span>Modalidad e importe en USD</span><span>Equivalente CCL</span>
                                        </div>
                                        <div v-for="precio in tarifario.segments.filter(row => row.service_id === servicio.id)" :key="precio.option_id"
                                             class="tariff-segment">
                                            <strong>{{ precio.label }}</strong>
                                            <div class="tariff-price-entry">
                                                <div class="tariff-price-mode" role="group" :aria-label="'Modalidad de precio para ' + precio.label">
                                                    <button type="button" :aria-pressed="precio.fixed" @click="establecerPrecioFijo(precio, true)">Fijo</button>
                                                    <button type="button" :aria-pressed="!precio.fixed" @click="establecerPrecioFijo(precio, false)">Rango</button>
                                                </div>
                                                <F v-if="precio.fixed" v-model="precio.min" label="Precio fijo en USD" type="number" min="0" step="0.01" :required="tarifario.active" @update:model-value="precio.max = $event"/>
                                                <div v-else class="tariff-range-inputs">
                                                    <F v-model="precio.min" label="Desde · USD" type="number" min="0" step="0.01" :required="tarifario.active"/>
                                                    <F v-model="precio.max" label="Hasta · USD" type="number" min="0" step="0.01" :required="tarifario.active"/>
                                                </div>
                                            </div>
                                            <span v-if="precio.min === '' || precio.min == null" class="tariff-equivalent tariff-equivalent-empty">Falta cargar el precio</span>
                                            <span v-else class="tariff-equivalent">{{ equivalente(precio.min) }}<template v-if="Number(precio.min) !== Number(precio.max)"> – {{ equivalente(precio.max) }}</template></span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>

                        <details v-if="tarifario.components.length" class="tariff-section tariff-components">
                            <summary>Trabajos adicionales con costo <span>Se suman solo cuando el alcance los incluye</span></summary>
                            <div v-for="componente in tarifario.components" :key="componente.id" class="tariff-segment tariff-component-row">
                                <strong>{{ componente.name }}</strong>
                                <F v-model="componente.min" label="Desde · USD" type="number" min="0" step="0.01" :required="tarifario.active"/>
                                <F v-model="componente.max" label="Hasta · USD" type="number" min="0" step="0.01" :required="tarifario.active"/>
                                <span class="tariff-equivalent">{{ equivalente(componente.min) }}<template v-if="Number(componente.min) !== Number(componente.max)"> – {{ equivalente(componente.max) }}</template></span>
                            </div>
                        </details>

                    </form>
                </v-card>
            </div>
            <template #footer>
                <B variant="secondary" :disabled="tarifario.processing" @click="panelOpen = false">Cancelar</B>
                <B type="submit" form="formulario-tarifario" :disabled="tarifario.processing">{{ tarifario.processing ? 'Guardando…' : (tarifario.active ? 'Activar y guardar' : 'Guardar como borrador') }}</B>
            </template>
        </SidePanel>
    </section>
</template>

<style scoped>
/* El panel de carga separa la conversión, los precios base y los componentes opcionales. */
.tariff-form-card { width: 100%; max-width: 640px; margin-inline: auto; padding: 18px; }
.tariff-form-card form, .tariff-section { display: grid; gap: 14px; }
.tariff-errors { margin: 0; }
.tariff-target-context { display: grid; gap: 3px; margin: 0; border-left: 3px solid #1f6f7f; background: #f3f8f9; }
.tariff-target-context strong { color: #153b64; }
.tariff-target-context span { color: #446075; }
.tariff-status-summary { position: sticky; top: 0; z-index: 2; display: grid; gap: 2px; margin: -18px -18px 2px; padding: 10px 18px; border-bottom: 1px solid #1f6f7f26; background: #fff; color: #446075; font-size: .8rem; }
.tariff-status-summary strong { color: #153b64; font-size: .86rem; }
.tariff-status-ready { border-bottom-color: #2db3a380; background: #f1faf8; }
.tariff-section + .tariff-section { border-top: 1px solid #1f6f7f26; padding-top: 18px; }
.tariff-section h2 { margin: 0; font-size: 1.1rem; line-height: 1.35; }
.tariff-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 12px; border: 1px solid #1f6f7f26; border-left: 3px solid #2db3a3; border-radius: 8px; background: #f5f9fa; }
.tariff-section-heading > span { color: #446075; font-size: .76rem; white-space: nowrap; }
.tariff-section-heading > span strong { color: #1f6f7f; }
.tariff-section p { margin: 0; color: #446075; }
.tariff-fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(150px, .48fr); gap: 12px; align-items: start; width: 100%; }
.tariff-fields :deep(.field > label) { min-height: 0; display: flex; align-items: flex-end; }
.tariff-conversion { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 18px; margin: 0; }
.tariff-activation { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; padding: 14px 16px; border: 1px solid #1f6f7f30; border-left: 4px solid #446075; border-radius: 10px; background: #f5f9fa; }
.tariff-activation-on { border-left-color: #2db3a3; background: #eaf7f5; }
.tariff-activation-copy { display: grid; gap: 4px; max-width: 500px; color: #446075; font-size: .9rem; }
.tariff-activation-copy strong { color: #153b64; font-size: .98rem; }
.tariff-activation-toggle { display: inline-flex; align-items: center; gap: 9px; color: #153b64; font-size: .88rem; font-weight: 700; cursor: pointer; }
.tariff-activation-toggle input { width: 18px; height: 18px; margin: 0; accent-color: #1f6f7f; }
.tariff-heading p { margin-top: 4px; }
.tariff-services { display: grid; gap: 10px; }
.tariff-service { overflow: hidden; border: 1px solid #1f6f7f38; border-radius: 10px; background: #fff; }
.tariff-service-toggle { display: flex; width: 100%; min-height: 48px; align-items: center; justify-content: space-between; gap: 14px; padding: 10px 13px; border: 0; border-left: 3px solid #1f6f7f; background: #f3f8f9; color: #153b64; text-align: left; cursor: pointer; }
.tariff-service-toggle:hover, .tariff-service-open .tariff-service-toggle { background: #eaf4f5; }
.tariff-service-name { font-size: .9rem; font-weight: 700; line-height: 1.35; }
.tariff-service-meta { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 10px; color: #446075; font-size: .76rem; }
.tariff-service-chevron { color: #1f6f7f; font-size: 1.2rem; line-height: 1; transition: transform .16s ease; }
.tariff-service-open .tariff-service-chevron { transform: rotate(180deg); }
.tariff-service-content { display: grid; gap: 0; padding: 4px 12px 8px; }
.tariff-segment-header, .tariff-segment { display: grid; grid-template-columns: minmax(95px, .72fr) minmax(260px, 1.8fr) minmax(120px, .86fr); align-items: center; gap: 10px; }
.tariff-segment-header { margin-top: 4px; padding: 8px 0; border-bottom: 1px solid #1f6f7f2e; color: #153b64; font-size: .8rem; font-weight: 700; }
.tariff-segment { padding: 8px 0; border-top: 1px solid #1f6f7f1e; }
.tariff-component-row { grid-template-columns: minmax(150px, .9fr) repeat(2, minmax(130px, 1fr)) minmax(125px, .8fr); }
.tariff-segment > strong { align-self: center; color: #153b64; }
.tariff-price-entry { display: grid; grid-template-columns: 112px minmax(0, 1fr); align-items: end; gap: 9px; min-width: 0; }
.tariff-price-mode { display: grid; grid-template-columns: 1fr 1fr; padding: 2px; border: 1px solid #1f6f7f30; border-radius: 7px; background: #f5f9fa; }
.tariff-price-mode button { min-height: 28px; border: 0; border-radius: 5px; background: transparent; color: #446075; font: inherit; font-size: .75rem; cursor: pointer; }
.tariff-price-mode button[aria-pressed='true'] { background: #153b64; color: #fff; }
.tariff-range-inputs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.tariff-equivalent { align-self: center; color: #446075; font-size: .82rem; text-align: right; }
.tariff-equivalent-empty { justify-self: end; padding: 5px 8px; border: 1px solid #d9e5e9; border-radius: 6px; background: #f3f7f8; color: #526b7d; font-size: .75rem; font-weight: 600; }
.tariff-segment :deep(.field) { gap: 3px; margin: 0; }
.tariff-segment :deep(.field > label) { font-size: .78rem; line-height: 1.15; }
.tariff-components { border: 1px solid #1f6f7f26 !important; border-radius: 12px; padding: 14px !important; }
.tariff-components summary { cursor: pointer; color: #153b64; font-weight: 700; }
.tariff-components summary span { color: #446075; font-weight: 400; margin-left: 8px; }
.tariff-component-row:first-of-type { margin-top: 10px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; }
.field-error { color: #a43c46 !important; }
@media (max-width: 700px) {
    .tariff-form-card { padding: 14px; }
    .tariff-status-summary { margin: -14px -14px 2px; padding: 9px 14px; }
    .tariff-segment-header { display: none; }
    .tariff-segment-header { grid-template-columns: minmax(0, 1fr); }
    .tariff-segment { grid-template-columns: minmax(0, 1fr); gap: 7px; }
    .tariff-segment > strong { grid-column: 1 / -1; }
    .tariff-price-entry { grid-template-columns: 88px minmax(0, 1fr); align-items: end; }
    .tariff-component-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tariff-equivalent { grid-column: 1 / -1; text-align: left; }
    .tariff-equivalent-empty { justify-self: start; }
}
@media (max-width: 480px) {
    .tariff-section-heading { align-items: flex-start; flex-direction: column; gap: 4px; }
    .tariff-fields { grid-template-columns: minmax(0, 1fr); }
    .tariff-service-toggle { align-items: flex-start; }
    .tariff-service-meta { gap: 5px; font-size: .7rem; }
    .tariff-price-entry { grid-template-columns: 78px minmax(0, 1fr); gap: 7px; }
}
</style>
