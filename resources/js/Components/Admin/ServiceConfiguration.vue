<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {computed, ref, useId} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import S from '../UI/StatusBadge.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// La ficha separa lo propio del servicio de las preguntas compartidas por todo el catálogo.
const props = defineProps({service: Object, administration: Object, section: String});
const submit = useSubmit();
// Los botones del pie flotante envían su formulario con la validación nativa.
const idEditor = useId();
const formularios = {
    plantilla: idEditor + '-plantilla',
    precios: idEditor + '-precios',
    configuracion: idEditor + '-configuracion',
};
const questions = computed(() => props.administration.questions.filter(q => q.service_id == props.service.id || q.service_id == null));
const components = computed(() => props.administration.components.filter(c => c.service_id == props.service.id));
const gruposPreguntas = computed(() => [
    {id: 'propias', titulo: 'Preguntas de este servicio', descripcion: 'Definen el alcance de este servicio.', registros: questions.value.filter(q => q.service_id != null)},
    {id: 'generales', titulo: 'Preguntas generales', descripcion: 'Se aplican a todos los servicios. Los cambios afectan las solicitudes nuevas.', registros: questions.value.filter(q => q.service_id == null)},
]);
const conceptosActivos = computed(() => components.value.filter(c => c.active));
const rules = computed(() => props.administration.rules.filter(r => components.value.some(c => c.id == r.service_component_id)));
const options = computed(() => questions.value.flatMap(q => q.options.map(o => ({
    value: o.id,
    label: `${q.question}: ${o.label}${q.active && o.active ? '' : ' (inactiva)'}`
}))));
const componentOptions = computed(() => components.value.map(c => ({
    value: c.id,
    label: c.name + (c.active ? '' : ' (inactivo)')
})));
const types = [{value: 'single_choice', label: 'Una opción'}, {
    value: 'multiple_choice',
    label: 'Varias opciones'
}, {value: 'number', label: 'Cantidad'}, {value: 'text', label: 'Texto libre'}];
const titulos = {preguntas: 'pregunta', componentes: 'concepto', reglas: 'regla'};
const labelFor = type => types.find(t => t.value === type)?.label ?? type;
const questionFor = optionId => questions.value.find(q => q.options.some(o => o.id == optionId));
const optionLabel = id => questionFor(id)?.options.find(o => o.id == id)?.label ?? 'Opción inactiva';
const componentName = id => components.value.find(c => c.id == id)?.name ?? 'Componente';
const ruleIsCurrent = rule => rule.active && questionFor(rule.estimator_option_id)?.active && questionFor(rule.estimator_option_id)?.options.some(o => o.id == rule.estimator_option_id && o.active);
// Solo se muestran condiciones aplicables, incluidas las cantidades ingresadas por el cliente.
const activeRulesFor = id => rules.value.filter(rule => rule.service_component_id == id && ruleIsCurrent(rule));
const quantityQuestionsFor = id => questions.value.filter(question =>
    question.active && question.input_type === 'number' && question.quantity_component_id == id);
// La reutilización copia la definición al servicio actual y deja los precios y las reglas en su origen.
const reutilizar = ref(null);
const seleccionPlantilla = ref('');
const nombreServicio = id => props.administration.services.find(s => s.id == id)?.name ?? 'Otro servicio';
const plantillas = computed(() => {
    const esPregunta = reutilizar.value === 'preguntas';
    const registros = esPregunta ? props.administration.questions : props.administration.components;
    return registros.filter(r => r.active && r.service_id != null && r.service_id != props.service.id &&
        (esPregunta
            ? !questions.value.some(q => q.active && q.question === r.question)
            : !components.value.some(c => c.slug === r.slug)));
});
const opcionesPlantilla = computed(() => plantillas.value.map(r => ({
    value: r.id,
    label: (r.question ?? r.name) + ' · ' + nombreServicio(r.service_id),
})));
function abrirReutilizacion(tipo) {
    seleccionPlantilla.value = '';
    reutilizar.value = tipo;
}
function copiarPlantilla() {
    const original = plantillas.value.find(r => r.id == seleccionPlantilla.value);
    if (!original) return;
    const tipo = reutilizar.value;
    reutilizar.value = null;
    edit(tipo, {...original, id: null, service_id: props.service.id, quantity_component_id: null, is_default: false});
}

const dialog = ref(false);
const resource = ref('preguntas');
const form = useForm({});
const puedeGeneralizar = ref(false);
const alcance = computed({
    get: () => form.service_id == null ? 'general' : 'servicio',
    set: valor => { form.service_id = valor === 'general' ? null : props.service.id; },
});
const fields = {
    preguntas: ['id', 'service_id', 'question', 'input_type', 'sort_order', 'options_text', 'quantity_component_id', 'required', 'active'],
    componentes: ['id', 'service_id', 'name', 'slug', 'description', 'unit_code', 'is_default', 'default_quantity', 'active'],
    reglas: ['id', 'estimator_option_id', 'service_component_id', 'quantity', 'active'],
};

function edit(kind, row = {}, general = false) {
    resource.value = kind;
    const defaults = {
        id: null,
        service_id: general ? null : props.service.id,
        question: '',
        input_type: 'single_choice',
        sort_order: 0,
        options_text: '',
        quantity_component_id: null,
        required: true,
        active: true,
        name: '',
        slug: '',
        description: '',
        unit_code: 'unidad',
        is_default: false,
        default_quantity: 1,
        estimator_option_id: '',
        service_component_id: '',
        quantity: 1
    };
    const values = {
        ...defaults, ...row,
        active: row.active == null ? true : Boolean(row.active),
        required: row.required == null ? true : Boolean(row.required),
        is_default: Boolean(row.is_default),
        options_text: row.options?.filter(o => o.active).map(o => o.label).join('\n') ?? ''
    };
    puedeGeneralizar.value = kind === 'preguntas' && values.service_id != null && !row.quantity_component_id;
    form.defaults(Object.fromEntries(fields[kind].map(key => [key, values[key]])));
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function save() {
    form.transform(values => ({
        ...Object.fromEntries(fields[resource.value].map(key => [key, values[key]])),
        catalog_service_id: props.service.id,
        quantity_component_id: values.input_type === 'number' ? values.quantity_component_id || null : null
    }));
    submit(form, `/admin/guardar/${resource.value}`, {
        onSuccess: () => {
            dialog.value = false;
        }
    });
}

// El servidor identifica la versión vigente con el mismo criterio que usa el catálogo.
const currentTariff = computed(() => props.administration.tariffs.find(t => t.id == props.administration.currentTariffId));
const moneda = computed(() => currentTariff.value?.currency_code ?? 'ARS');
const baseVigente = computed(() => currentTariff.value?.services.find(p => p.service_id == props.service.id));
const precioConcepto = id => currentTariff.value?.components.find(p => p.service_component_id == id);
const conceptosConPrecio = computed(() => conceptosActivos.value.filter(c => precioConcepto(c.id)).length);
const otrosErroresDePrecio = computed(() => Object.entries(prices.errors)
    .filter(([campo]) => campo !== 'name' && campo !== 'min' && campo !== 'max' && !/^components\.\d+\.(min|max)$/.test(campo))
    .map(([, mensaje]) => mensaje));
const coberturaCompleta = computed(() => Boolean(baseVigente.value) && conceptosConPrecio.value === conceptosActivos.value.length);
const automatica = computed(() => props.service.estimation_mode === 'automatic');
const gruposConceptos = computed(() => [
    {id: 'incluidos', titulo: 'Incluidos por defecto', registros: conceptosActivos.value.filter(c => c.is_default)},
    {id: 'condicionales', titulo: 'Según las respuestas', registros: conceptosActivos.value.filter(c => !c.is_default && (activeRulesFor(c.id).length || quantityQuestionsFor(c.id).length))},
    {id: 'pendientes', titulo: 'Sin condición configurada', registros: conceptosActivos.value.filter(c => !c.is_default && !activeRulesFor(c.id).length && !quantityQuestionsFor(c.id).length)},
    {id: 'inactivos', titulo: 'Inactivos', registros: components.value.filter(c => !c.active)},
].filter(grupo => grupo.registros.length));
const opcionesActivas = computed(() => options.value.filter(opcion => {
    const pregunta = questionFor(opcion.value);
    return pregunta?.active && pregunta.options.some(o => o.id == opcion.value && o.active);
}));

function importe(precio) {
    if (!precio) return 'Sin precio vigente';
    const minimo = money(precio.price_min, moneda.value);
    return Number(precio.price_min) === Number(precio.price_max)
        ? minimo
        : minimo + ' a ' + money(precio.price_max, moneda.value);
}

// El formulario se prepara al abrirlo, con los importes ya publicados como punto de partida.
const publicar = ref(false);
const prices = useForm({service_id: props.service.id, name: '', min: '', max: '', components: []});
function abrirPrecios() {
    prices.defaults({
        service_id: props.service.id,
        name: ('Actualización de ' + props.service.name).slice(0, 255),
        min: baseVigente.value?.price_min ?? '',
        max: baseVigente.value?.price_max ?? '',
        components: conceptosActivos.value.map(c => {
            const precio = precioConcepto(c.id);
            return {id: c.id, name: c.name, min: precio?.price_min ?? '', max: precio?.price_max ?? ''};
        }),
    });
    prices.resetAndClearErrors();
    publicar.value = true;
}
function publishPrices() {
    submit(prices, '/admin/guardar/precios-servicio', {onSuccess: () => { publicar.value = false; }});
}
</script>

<template>
    <div class="service-configuration">
        <!-- Cada pregunta muestra su alcance, su estado y si requiere respuesta. -->
        <template v-if="section === 'preguntas'">
            <section v-for="grupo in gruposPreguntas" :key="grupo.id" class="bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>{{ grupo.titulo }}</h2><p>{{ grupo.descripcion }}</p></div>
                    <div class="acciones-configuracion">
                        <B v-if="grupo.id === 'propias'" compact variant="secondary" @click="abrirReutilizacion('preguntas')">Reutilizar pregunta</B>
                        <B compact :variant="grupo.id === 'propias' ? 'primary' : 'secondary'"
                           @click="edit('preguntas', {}, grupo.id === 'generales')">{{ grupo.id === 'generales' ? 'Agregar pregunta general' : 'Agregar pregunta' }}</B>
                    </div>
                </div>
                <p v-if="!grupo.registros.length" class="notice">{{ grupo.id === 'generales' ? 'Todavía no hay preguntas generales.' : 'Todavía no hay preguntas propias de este servicio.' }}</p>
                <article v-for="question in grupo.registros" :key="question.id" class="configuration-row">
                    <div>
                        <div class="estado-configuracion"><h3>{{ question.question }}</h3><S :value="question.active ? 'enabled' : 'disabled'"/></div>
                        <p>{{ labelFor(question.input_type) }} · {{ question.required ? 'Obligatoria' : 'Opcional' }} · Orden {{ question.sort_order }}</p>
                        <p v-if="question.options.some(o => o.active)">{{ question.options.filter(o => o.active).map(o => o.label).join(' · ') }}</p>
                    </div>
                    <B compact variant="secondary" :aria-label="'Editar pregunta ' + question.question" @click="edit('preguntas', question)">Editar</B>
                </article>
            </section>
        </template>

        <!-- La base, los conceptos y las condiciones se revisan por separado. -->
        <template v-else-if="section === 'estimacion'">
            <section class="bloque-configuracion">
                <h2>Cómo se calcula la estimación</h2>
                <p v-if="automatica">La estimación empieza con el precio base y suma los conceptos según sus cantidades.</p>
                <p v-else>Este servicio se cotiza manualmente. Podés preparar sus conceptos y reglas para cuando actives la estimación automática.</p>
                <div v-if="automatica" class="resumen-importe"><strong>Precio base vigente</strong><span>{{ importe(baseVigente) }}</span></div>
            </section>
            <section class="bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>Conceptos de precio</h2><p>Definí qué se suma al precio base y en qué cantidad.</p></div>
                    <div class="acciones-configuracion">
                        <B compact variant="secondary" @click="abrirReutilizacion('componentes')">Reutilizar concepto</B>
                        <B compact @click="edit('componentes')">Agregar concepto</B>
                    </div>
                </div>
                <p v-if="!components.length" class="notice">Todavía no hay conceptos de precio.</p>
                <section v-for="grupo in gruposConceptos" :key="grupo.id" class="grupo-conceptos">
                    <h3>{{ grupo.titulo }}</h3>
                    <article v-for="concepto in grupo.registros" :key="concepto.id" class="configuration-row concept-row">
                        <div>
                            <h4>{{ concepto.name }}</h4>
                            <p>{{ concepto.description }}</p>
                            <p>Unidad {{ concepto.unit_code }} · {{ importe(precioConcepto(concepto.id)) }}</p>
                            <p v-if="concepto.is_default">Cantidad por defecto {{ concepto.default_quantity }}</p>
                            <p v-for="pregunta in quantityQuestionsFor(concepto.id)" :key="pregunta.id">Cantidad ingresada en «{{ pregunta.question }}».</p>
                        </div>
                        <B compact variant="secondary" :aria-label="'Editar concepto ' + concepto.name" @click="edit('componentes', concepto)">Editar</B>
                    </article>
                </section>
            </section>
            <section class="bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>Reglas de cálculo</h2><p>Cada regla indica cuántas unidades suma una respuesta a un concepto.</p></div>
                    <B compact :disabled="!opcionesActivas.length || !conceptosActivos.length" @click="edit('reglas')">Agregar regla</B>
                </div>
                <p v-if="!opcionesActivas.length || !conceptosActivos.length" class="notice">Para crear una regla necesitás una pregunta activa con opciones y un concepto activo.</p>
                <p v-else-if="!rules.length" class="notice">Todavía no hay reglas de cálculo.</p>
                <article v-for="regla in rules" :key="regla.id" class="configuration-row">
                    <div>
                        <div class="estado-configuracion"><h3>{{ optionLabel(regla.estimator_option_id) }} → {{ componentName(regla.service_component_id) }}</h3>
                            <S :value="ruleIsCurrent(regla) && conceptosActivos.some(c => c.id == regla.service_component_id) ? 'enabled' : 'disabled'"/></div>
                        <p>{{ questionFor(regla.estimator_option_id)?.question }}</p>
                        <p>Unidades que suma {{ regla.quantity }}</p>
                    </div>
                    <B compact variant="secondary" :aria-label="'Editar regla ' + optionLabel(regla.estimator_option_id) + ' a ' + componentName(regla.service_component_id)" @click="edit('reglas', regla)">Editar</B>
                </article>
            </section>
        </template>

        <!-- Para actualizar el tarifario se abre un formulario aparte. -->
        <section v-else-if="section === 'precios'" class="bloque-configuracion">
            <div class="cabecera-configuracion">
                <div><h2>Precios vigentes</h2>
                    <p v-if="automatica && currentTariff">{{ currentTariff.name }} · {{ moneda }} · Desde {{ date(currentTariff.effective_from) }}</p>
                    <p v-else-if="automatica">Todavía no hay un tarifario vigente.</p>
                </div>
                <div class="acciones-configuracion">
                    <B compact variant="secondary" href="/admin/tarifario">Ver tarifarios</B>
                    <B v-if="automatica" compact @click="abrirPrecios">Actualizar precios</B>
                </div>
            </div>
            <template v-if="automatica">
                <p class="notice">
                    <strong>{{ coberturaCompleta ? 'Cobertura completa' : 'Cobertura incompleta' }}</strong>
                    · {{ baseVigente ? 'Base cargada' : 'Base pendiente' }}
                    · {{ conceptosConPrecio }} de {{ conceptosActivos.length }} conceptos activos con precio
                </p>
                <dl class="lista-precios">
                    <div><dt>Precio base del servicio</dt><dd>{{ importe(baseVigente) }}</dd></div>
                    <div v-for="concepto in conceptosActivos" :key="concepto.id">
                        <dt>{{ concepto.name }} <small>· Por unidad</small></dt><dd>{{ importe(precioConcepto(concepto.id)) }}</dd>
                    </div>
                </dl>
            </template>
            <p v-else class="notice"><strong>A cotizar.</strong> El precio se define en un presupuesto profesional.</p>
        </section>

        <SidePanel :title="reutilizar ? 'Reutilizar ' + titulos[reutilizar] : 'Reutilizar'" :model-value="Boolean(reutilizar)"
                   @update:model-value="reutilizar = null">
            <form v-if="reutilizar" :id="formularios.plantilla" class="form-configuracion" @submit.prevent="copiarPlantilla">
                <div class="contexto-configuracion">
                    <span>Servicio de destino</span>
                    <strong>{{ service.name }}</strong>
                </div>
                <F v-if="opcionesPlantilla.length" v-model="seleccionPlantilla" :label="reutilizar === 'preguntas' ? 'Elegí una pregunta' : 'Elegí un concepto'"
                   type="select" :options="opcionesPlantilla" required/>
                <p v-else class="notice">No hay registros activos de otros servicios disponibles para copiar.</p>
                <p v-if="opcionesPlantilla.length" class="ayuda-configuracion">Vas a poder editar la copia antes de guardarla. Las reglas y los precios se configuran por separado.</p>
            </form>
            <template #footer>
                <B variant="secondary" @click="reutilizar = null">Cancelar</B>
                <B type="submit" :form="formularios.plantilla" :disabled="!seleccionPlantilla">Usar como plantilla</B>
            </template>
        </SidePanel>

        <SidePanel title="Actualizar precios" v-model="publicar" :persistent="prices.processing">
            <form :id="formularios.precios" class="form-configuracion" @submit.prevent="publishPrices">
                <div class="contexto-configuracion">
                    <span>Servicio · {{ moneda }}</span>
                    <strong>{{ service.name }}</strong>
                    <p>Publicar crea una versión del tarifario y conserva los importes anteriores.</p>
                </div>
                <div v-if="otrosErroresDePrecio.length" class="notice" role="alert">
                    <p v-for="(error, indice) in otrosErroresDePrecio" :key="indice">{{ error }}</p>
                </div>
                <F v-model="prices.name" label="Nombre de la nueva versión" required maxlength="255" :error="prices.errors.name"/>
                <h3>Base del servicio · {{ moneda }}</h3>
                <div class="field-row">
                    <F v-model="prices.min" label="Precio mínimo" type="number" min="0" max="100000000" step="0.01"
                       required :error="prices.errors.min"/>
                    <F v-model="prices.max" label="Precio máximo" type="number" min="0" max="100000000" step="0.01"
                       required :error="prices.errors.max"/>
                </div>
                <div v-for="(component,index) in prices.components" :key="component.id" class="component-price"><h3>
                    {{ component.name }}</h3>
                    <div class="field-row">
                        <F v-model="component.min" label="Precio mínimo por unidad" type="number" min="0" max="100000000" step="0.01"
                           required :error="prices.errors[`components.${index}.min`]"/>
                        <F v-model="component.max" label="Precio máximo por unidad" type="number" min="0" max="100000000" step="0.01"
                           required :error="prices.errors[`components.${index}.max`]"/>
                    </div>
                </div>
                <p>Las reglas de respuestas y las cantidades se suman a esta base. Para un importe fijo, usá el mismo
                    mínimo y máximo.</p>
            </form>
            <template #footer>
                <B variant="secondary" :disabled="prices.processing" @click="publicar = false">Cancelar</B>
                <B type="submit" :form="formularios.precios" :disabled="prices.processing">{{ prices.processing ? 'Publicando…' : 'Publicar nueva versión' }}</B>
            </template>
        </SidePanel>

        <SidePanel :title="(form.id ? 'Editar ' : 'Agregar ') + titulos[resource]" v-model="dialog" :persistent="form.processing">
            <form :id="formularios.configuracion" class="form-configuracion" @submit.prevent="save">
                <div class="contexto-configuracion">
                    <span>Servicio</span>
                    <strong>{{ service.name }}</strong>
                </div>
                <div v-if="Object.keys(form.errors).length" class="notice" role="alert"><p
                    v-for="(error,key) in form.errors" :key="key">{{ error }}</p></div>
                <template v-if="resource === 'preguntas'">
                    <F v-if="puedeGeneralizar" v-model="alcance" label="Alcance de la pregunta" type="select" required
                       :options="[{value: 'servicio', label: 'Solo este servicio'}, {value: 'general', label: 'Todos los servicios'}]"/>
                    <p v-if="alcance === 'general'" class="notice">Pregunta general. Se aplica a todos los servicios.</p>
                    <F v-model="form.question" label="Pregunta" required maxlength="1000"
                       :error="form.errors.question"/>
                    <div class="field-row">
                        <F v-model="form.input_type" label="Tipo de respuesta" type="select" :options="types" required/>
                        <F v-model="form.sort_order" label="Orden de aparición" type="number" min="0" max="1000" required/>
                    </div>
                    <F v-if="['single_choice','multiple_choice'].includes(form.input_type)"
                       v-model="form.options_text" label="Opciones (una por línea)" type="textarea" required
                       maxlength="5000"
                       hint="Las opciones sin cambios conservan sus reglas. Si cambiás un texto, revisá las reglas vinculadas."/>
                    <F v-if="form.input_type === 'number' && form.service_id !== null"
                       v-model="form.quantity_component_id" label="Concepto cuya cantidad define (opcional)"
                       type="select" :options="[{value:'',label:'Sin componente'}, ...componentOptions]"/>
                </template>
                <template v-else-if="resource === 'componentes'">
                    <F v-model="form.name" label="Nombre" required maxlength="255"/>
                    <F v-model="form.slug" label="Identificador" required maxlength="150"
                       hint="Letras, números y guiones; único dentro del servicio."/>
                    <F v-model="form.description" label="Descripción" type="textarea" required
                       maxlength="10000"/>
                    <div class="field-row">
                        <F v-model="form.unit_code" label="Unidad" required maxlength="32"/>
                        <F v-model="form.default_quantity" label="Cantidad inicial" type="number" min="0.001"
                           max="10000" step="0.001" required/>
                    </div>
                </template>
                <template v-else>
                    <F v-model="form.estimator_option_id" label="Respuesta que activa la regla" type="select"
                       :options="form.id ? options : opcionesActivas" required/>
                    <F v-model="form.service_component_id" label="Concepto de precio de este servicio"
                       type="select" :options="form.id ? componentOptions : componentOptions.filter(c => conceptosActivos.some(activo => activo.id == c.value))" required/>
                    <F v-model="form.quantity" label="Cantidad que incorpora" type="number" min="0" max="10000"
                       step="0.001" required/>
                </template>
                <fieldset class="controles-configuracion">
                    <legend>Opciones</legend>
                    <label v-if="resource === 'preguntas'" class="select-row">
                        <input v-model="form.required" type="checkbox">Respuesta obligatoria
                    </label>
                    <label v-if="resource === 'componentes'" class="select-row">
                        <input v-model="form.is_default" type="checkbox">Incluir por defecto en la estimación
                    </label>
                    <label class="select-row">
                        <input v-model="form.active" type="checkbox">{{ resource === 'preguntas' ? 'Pregunta activa' : 'Activo' }}
                    </label>
                    <p v-if="form.id" class="ayuda-configuracion">Desactivar conserva las respuestas y estimaciones anteriores.</p>
                </fieldset>
            </form>
            <template #footer>
                <B variant="secondary" :disabled="form.processing" @click="dialog = false">Cancelar</B>
                <B type="submit" :form="formularios.configuracion" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar' }}</B>
            </template>
        </SidePanel>
    </div>
</template>

<style scoped>
/* Los grupos de configuración separan visualmente las decisiones y admiten textos largos. */
.bloque-configuracion + .bloque-configuracion {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid #1f6f7f26;
}

.bloque-configuracion h2 {
    font-size: 1.25rem;
    margin: 0;
}

.bloque-configuracion p {
    margin: 4px 0 0;
    color: #446075;
}

.cabecera-configuracion, .acciones-configuracion, .estado-configuracion,
.configuration-row, .resumen-importe, .lista-precios > div {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cabecera-configuracion, .configuration-row, .resumen-importe, .lista-precios > div {
    justify-content: space-between;
}

.acciones-configuracion, .estado-configuracion {
    flex-wrap: wrap;
}

.bloque-configuracion .notice, .lista-precios, .resumen-importe, .grupo-conceptos {
    margin-top: 16px;
}

.configuration-row {
    padding: 16px 0;
    border-bottom: 1px solid #1f6f7f22;
    overflow-wrap: anywhere;
}

.configuration-row > div {
    min-width: 0;
}

.configuration-row h3, .configuration-row h4 {
    font-size: 1rem;
    margin: 0;
}

.configuration-row p {
    font-size: .875rem;
}

.grupo-conceptos > h3 {
    font-size: .9375rem;
    margin: 0 0 8px;
}

.concept-row, .resumen-importe {
    padding: 14px 16px;
    margin-bottom: 8px;
    background: #f5f9fa;
    border: 1px solid #1f6f7f26;
    border-radius: 10px;
}

/* La lectura de importes y el formulario de publicación comparten los espacios del editor. */
.lista-precios {
    border: 1px solid #1f6f7f26;
    border-radius: 10px;
    overflow: hidden;
}

.lista-precios > div {
    padding: 12px 16px;
}

.lista-precios > div + div, .component-price {
    border-top: 1px solid #1f6f7f26;
}

.lista-precios dd {
    margin: 0;
    font-weight: 700;
    text-align: right;
}

.lista-precios small {
    color: #446075;
}

/* Los formularios tienen el mismo margen del encabezado y una separación única entre campos. */
.form-configuracion {
    display: grid;
    gap: 16px;
    padding: 20px clamp(16px, 4vw, 24px) 24px;
    min-width: 0;
}

.form-configuracion :deep(.field) {
    min-width: 0;
    margin: 0;
}

.form-configuracion p, .form-configuracion h3 {
    margin: 0;
}

.contexto-configuracion,
.controles-configuracion {
    margin: 0;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #1f6f7f26;
    border-radius: 10px;
    background: #f5f9fa;
}

.contexto-configuracion {
    display: grid;
    gap: 3px;
    overflow-wrap: anywhere;
}

.contexto-configuracion > span,
.contexto-configuracion p,
.ayuda-configuracion {
    color: #446075;
    font-size: .875rem;
}

.controles-configuracion legend {
    padding-inline: 5px;
    color: #153b64;
    font-weight: 700;
    font-size: .875rem;
}

.controles-configuracion .select-row {
    margin: 0;
    min-height: 36px;
    cursor: pointer;
}

.component-price {
    display: grid;
    gap: 12px;
    padding-top: 16px;
}

@media (max-width: 760px) {
    .cabecera-configuracion, .configuration-row {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 440px) {
    .lista-precios > div {
        display: block;
    }

    .lista-precios dd {
        text-align: left;
    }
}
</style>
