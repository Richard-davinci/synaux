<script setup>
import {computed, ref} from 'vue';
import {useForm} from '@inertiajs/vue3';
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
const panelOpen = ref(false);
const submit = useSubmit();
const tarifario = useForm({name: '', currency_code: 'USD', ccl_rate: '', active: false, services: [], components: [], segments: []});
const cotizacionCcl = computed(() => Number(tarifario.ccl_rate) || 0);
const serviciosActivos = computed(() => administration.value.services.filter(service =>
    service.active && service.estimation_mode === 'automatic'));
const componentesActivos = computed(() => administration.value.components.filter(component => component.active
    && component.slug !== 'segmento-tarifario-2026-11'));
const filasEsperadas = computed(() => tarifario.segments.length + tarifario.components.length);
const filasCompletas = computed(() => [...tarifario.segments, ...tarifario.components]
    .filter(row => row.min !== '' && row.min != null && row.max !== '' && row.max != null).length);
const erroresFormulario = computed(() => Object.entries(tarifario.errors).map(([field, message]) => ({field, message})));

// Una versión anterior en USD sirve de plantilla; las versiones históricas en ARS no se reinterpretan como dólares.
function prepararVersion(origen = {}) {
    const reutilizar = origen.currency_code === 'USD';
    tarifario.name = '';
    tarifario.currency_code = 'USD';
    tarifario.ccl_rate = reutilizar ? (origen.ccl_rate ?? '') : '';
    tarifario.active = false;
    tarifario.services = serviciosActivos.value.map(servicio => {
        const precio = origen.services?.find(item => item.service_id === servicio.id);
        return {id: servicio.id, name: servicio.name, min: reutilizar ? (precio?.price_min ?? 0) : 0, max: reutilizar ? (precio?.price_max ?? 0) : 0};
    });
    tarifario.components = componentesActivos.value.map(componente => {
        const precio = origen.components?.find(item => item.service_component_id === componente.id);
        return {id: componente.id, name: componente.name, min: reutilizar ? (precio?.price_min ?? '') : '', max: reutilizar ? (precio?.price_max ?? '') : ''};
    });
    tarifario.segments = serviciosActivos.value.flatMap(servicio => administration.value.clientSegments.map(segmento => {
        const precio = origen.segments?.find(item => item.service_id === servicio.id && item.estimator_option_id === segmento.id);
        return {service_id: servicio.id, service_name: servicio.name, option_id: segmento.id, label: segmento.label,
            min: reutilizar ? (precio?.price_min ?? '') : '', max: reutilizar ? (precio?.price_max ?? '') : '',
            fixed: !reutilizar || Number(precio?.price_min) === Number(precio?.price_max)};
    }));
    panelOpen.value = true;
}

function equivalente(amount) {
    if (amount === '' || amount == null) return 'Ingresá el importe';
    return cotizacionCcl.value && Number.isFinite(Number(amount)) ? money(Number(amount) * cotizacionCcl.value, 'ARS') : 'Ingresá la cotización CCL';
}

// Un precio fijo se guarda como mínimo y máximo iguales para conservar la estructura histórica del cálculo.
function alternarPrecioFijo(price, event) {
    price.fixed = event.target.checked;
    if (price.fixed) price.max = price.min;
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

        <SidePanel title="Nueva versión del tarifario" v-model="panelOpen" max-width="920" :persistent="tarifario.processing">
            <div class="synaux-app">
                <v-card class="card modal-card tariff-form-card">
                    <form @submit.prevent="guardarTarifario">
                        <div v-if="erroresFormulario.length" class="notice tariff-errors" role="alert">
                            <p v-for="error in erroresFormulario" :key="error.field">{{ error.message }}</p>
                        </div>
                        <p class="tariff-required-note">Los campos marcados con * son obligatorios.</p>
                        <section class="tariff-section">
                            <h2>Datos de la versión</h2>
                            <div class="tariff-fields">
                                <F v-model="tarifario.name" label="Nombre de la versión" required maxlength="255"/>
                                <F v-model="tarifario.ccl_rate" label="CCL de venta · ARS por USD" type="number"
                                   min="0.0001" step="0.0001" required hint="Ingresá manualmente la cotización de referencia del día."/>
                            </div>
                            <div class="notice tariff-conversion"><span>Moneda de carga: <strong>USD</strong></span>
                                <span v-if="cotizacionCcl">Con esta cotización, USD 1 equivale a {{ money(cotizacionCcl, 'ARS') }}.</span>
                                <span v-else>La conversión a pesos aparece cuando ingreses el CCL.</span></div>
                        </section>

                        <section class="tariff-section">
                            <div class="tariff-heading"><div><h2>Precios por servicio y tipo de cliente</h2>
                                <p>Cada bloque azul identifica un servicio. Definí su precio para cada tipo de cliente; si tiene trabajos adicionales configurados, aparecerán aparte.</p></div></div>
                            <p v-if="!administration.clientSegments.length" class="notice">Primero configurá la pregunta general «¿Para qué tipo de cliente?» con sus variantes.</p>
                            <article v-for="servicio in tarifario.services" :key="servicio.id" class="tariff-service">
                                <h3>{{ servicio.name }}</h3>
                                <div v-for="precio in tarifario.segments.filter(row => row.service_id === servicio.id)" :key="precio.option_id"
                                     :class="['tariff-segment', {'tariff-segment-fixed': precio.fixed}]">
                                    <strong>{{ precio.label }}</strong>
                                    <label class="fixed-toggle"><input :checked="precio.fixed" type="checkbox" @change="alternarPrecioFijo(precio, $event)">Precio fijo</label>
                                    <F v-if="precio.fixed" v-model="precio.min" label="Precio · USD" type="number" min="0" step="0.01" :required="tarifario.active" @update:model-value="precio.max = $event"/>
                                    <template v-else>
                                        <F v-model="precio.min" label="Desde · USD" type="number" min="0" step="0.01" :required="tarifario.active"/>
                                        <F v-model="precio.max" label="Hasta · USD" type="number" min="0" step="0.01" :required="tarifario.active"/>
                                    </template>
                                    <span class="tariff-equivalent">{{ equivalente(precio.min) }}<template v-if="Number(precio.min) !== Number(precio.max)"> – {{ equivalente(precio.max) }}</template></span>
                                </div>
                            </article>
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

                        <section class="tariff-section">
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
                            <div class="notice tariff-conversion">
                                <strong>{{ filasCompletas }} de {{ filasEsperadas }} precios cargados</strong>
                                <span>Para activarla, completá los precios para cada tipo de cliente<span v-if="componentesActivos.length"> y los trabajos adicionales con costo</span>.</span>
                            </div>
                            <p v-if="tarifario.errors.active" class="field-error" role="alert">{{ tarifario.errors.active }}</p>
                        </section>
                        <div class="form-actions">
                            <B variant="secondary" :disabled="tarifario.processing" @click="panelOpen = false">Cancelar</B>
                            <B type="submit" :disabled="tarifario.processing">{{ tarifario.processing ? 'Guardando…' : (tarifario.active ? 'Activar y guardar' : 'Guardar como borrador') }}</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>

<style scoped>
/* El panel de carga separa la conversión, los precios base y los componentes opcionales. */
.tariff-form-card { width: 100%; max-width: 780px; margin-inline: auto; padding: 18px; }
.tariff-form-card form, .tariff-section { display: grid; gap: 14px; }
.tariff-errors { margin: 0; }
.tariff-required-note { font-size: .82rem; }
.tariff-section + .tariff-section { border-top: 1px solid #1f6f7f26; padding-top: 18px; }
.tariff-section h2 { margin: 0; font-size: 1.15rem; }
.tariff-section p { margin: 0; color: #446075; }
.tariff-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.tariff-fields :deep(.field > label) { min-height: 2.5em; display: flex; align-items: flex-end; }
.tariff-conversion { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 18px; margin: 0; }
.tariff-activation { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; padding: 14px 16px; border: 1px solid #1f6f7f30; border-left: 4px solid #446075; border-radius: 10px; background: #f5f9fa; }
.tariff-activation-on { border-left-color: #2db3a3; background: #eaf7f5; }
.tariff-activation-copy { display: grid; gap: 4px; max-width: 500px; color: #446075; font-size: .9rem; }
.tariff-activation-copy strong { color: #153b64; font-size: .98rem; }
.tariff-activation-toggle { display: inline-flex; align-items: center; gap: 9px; color: #153b64; font-size: .88rem; font-weight: 700; cursor: pointer; }
.tariff-activation-toggle input { width: 18px; height: 18px; margin: 0; accent-color: #1f6f7f; }
.tariff-heading p { margin-top: 4px; }
.tariff-service { display: grid; gap: 5px; padding: 11px; border: 1px solid #1f6f7f26; border-radius: 12px; }
.tariff-service h3 { margin: 0; padding: 8px 11px; border-radius: 8px; background: #153b64; color: #fff; font-size: .96rem; }
.tariff-segment { display: grid; grid-template-columns: minmax(95px, .7fr) auto repeat(2, minmax(110px, 1fr)) minmax(125px, .8fr); align-items: center; gap: 9px; padding: 8px 0; border-top: 1px solid #1f6f7f1e; }
.tariff-segment-fixed { grid-template-columns: minmax(95px, .7fr) auto minmax(150px, 1fr) minmax(125px, .8fr); }
.tariff-segment > strong { align-self: center; color: #153b64; }
.fixed-toggle { display: flex; align-items: center; gap: 6px; align-self: center; color: #446075; font-size: .8rem; white-space: nowrap; }
.tariff-equivalent { align-self: center; color: #446075; font-size: .85rem; text-align: right; }
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
    .tariff-fields { grid-template-columns: 1fr; }
    .tariff-segment, .tariff-segment-fixed { grid-template-columns: minmax(0, 1fr) auto; }
    .tariff-segment > strong, .tariff-equivalent { grid-column: 1 / -1; text-align: left; }
}
</style>
