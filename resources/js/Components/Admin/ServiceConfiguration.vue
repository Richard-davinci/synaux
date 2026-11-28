<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {computed, ref, useId} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import S from '../UI/StatusBadge.vue';
import ImportesServicio from './ImportesServicio.vue';
import {datosDeImportes, prepararImporte} from './preciosServicio';
import {money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// La ficha separa lo propio del servicio de las preguntas compartidas por todo el catálogo.
const props = defineProps({service: Object, administration: Object, section: String});
const submit = useSubmit();
// Los botones del pie flotante envían su formulario con la validación nativa.
const idEditor = useId();
const formularios = {
    plantilla: idEditor + '-plantilla',
    configuracion: idEditor + '-configuracion',
    precios: idEditor + '-precios',
};
const questions = computed(() => props.administration.questions.filter(q => q.service_id == props.service.id || q.service_id == null));
const components = computed(() => props.administration.components.filter(c => c.service_id == props.service.id
    && c.slug !== 'segmento-tarifario-2026-11'));
const gruposPreguntas = computed(() => [
    {
        id: 'propias',
        titulo: 'Preguntas de este servicio',
        descripcion: 'Definen el alcance de este servicio.',
        registros: questions.value.filter(q => q.service_id != null)
    },
    {
        id: 'generales',
        titulo: 'Preguntas generales',
        descripcion: 'Se aplican a todos los servicios. Los cambios afectan las solicitudes nuevas.',
        registros: questions.value.filter(q => q.service_id == null)
    },
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
const modosCobro = [
    {value: 'heredado', label: 'Conservar cálculo actual'},
    {value: 'unico', label: 'Cargo único'},
    {value: 'acumulativo', label: 'Cargo acumulativo'},
    {value: 'cantidad', label: 'Por cantidad'},
];
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
const categoriaOrigen = ref('');
const servicioOrigen = ref('');
const seleccionPlantilla = ref('');
const menuPlantillas = {contentClass: 'menu-plantillas-servicio', maxWidth: 'min(512px, calc(100vw - 32px))'};
const plantillas = computed(() => {
    const esPregunta = reutilizar.value === 'preguntas';
    const registros = esPregunta ? props.administration.questions : props.administration.components;
    return registros.filter(r => r.active && r.service_id != null && r.service_id != props.service.id &&
        (esPregunta
            ? !questions.value.some(q => q.active && q.question === r.question)
            : !components.value.some(c => c.slug === r.slug)));
});
// Solo se ofrecen categorías y servicios con plantillas reutilizables. Cada cambio limpia la selección dependiente.
const opcionesPorNombre = registros => registros.map(registro => ({value: registro.id, label: registro.name}))
    .sort((a, b) => a.label.localeCompare(b.label, 'es'));
const serviciosConPlantillas = computed(() => {
    const ids = new Set(plantillas.value.map(r => Number(r.service_id)));
    return props.administration.services.filter(s => ids.has(Number(s.id)));
});
const categoriasOrigen = computed(() => {
    const ids = new Set(serviciosConPlantillas.value.map(s => Number(s.service_category_id)));
    return opcionesPorNombre(props.administration.categories.filter(c => ids.has(Number(c.id))));
});
const serviciosOrigen = computed(() => opcionesPorNombre(serviciosConPlantillas.value.filter(s =>
    s.service_category_id == categoriaOrigen.value)));
const plantillasDelServicio = computed(() => plantillas.value.filter(r => r.service_id == servicioOrigen.value));
const opcionesPlantilla = computed(() => opcionesPorNombre(plantillasDelServicio.value.map(r => ({
    id: r.id,
    name: r.question ?? r.name
}))));
const plantillaElegida = computed(() => plantillasDelServicio.value.find(r => r.id == seleccionPlantilla.value));

function elegirServicioOrigen(valor) {
    servicioOrigen.value = valor;
    seleccionPlantilla.value = '';
}

function elegirCategoriaOrigen(valor) {
    categoriaOrigen.value = valor;
    elegirServicioOrigen('');
}

function abrirReutilizacion(tipo) {
    elegirCategoriaOrigen('');
    reutilizar.value = tipo;
}

function copiarPlantilla() {
    const original = plantillaElegida.value;
    if (!original) return;
    const tipo = reutilizar.value;
    reutilizar.value = null;
    edit(tipo, {...original, id: null, service_id: props.service.id, quantity_component_id: null, is_default: false});
}

const dialog = ref(false);
const resource = ref('preguntas');
const form = useForm({});
// Los campos de la pregunta del sistema mantienen el ID de cada variante aunque cambie su texto.
const segmentosEditables = ref([]);
const nombresSegmentos = {particular: 'Particular', pyme: 'PyME', empresa: 'Empresa'};
const puedeGeneralizar = ref(false);
const alcance = computed({
    get: () => form.service_id == null ? 'general' : 'servicio',
    set: valor => {
        form.service_id = valor === 'general' ? null : props.service.id;
    },
});
const fields = {
    preguntas: ['id', 'service_id', 'question', 'input_type', 'sort_order', 'options_text', 'quantity_component_id', 'required', 'active'],
    componentes: ['id', 'service_id', 'name', 'slug', 'description', 'unit_code', 'is_default', 'default_quantity', 'modo_cobro', 'active'],
    reglas: ['id', 'estimator_option_id', 'service_component_id', 'quantity', 'active'],
};

function edit(kind, row = {}, general = false) {
    resource.value = kind;
    segmentosEditables.value = kind === 'preguntas' && row.clave_sistema === 'tipo_cliente'
        ? row.options.filter(option => Object.hasOwn(nombresSegmentos, option.clave_segmento)) : [];
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
        modo_cobro: 'heredado',
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
    const datos = Object.fromEntries(fields[kind].map(key => [key, values[key]]));
    if (segmentosEditables.value.length) {
        datos.etiquetas_segmentos = Object.fromEntries(segmentosEditables.value.map(option => [option.id, option.label]));
    }
    form.defaults(datos);
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function save() {
    form.transform(values => {
        const datos = Object.fromEntries(fields[resource.value].map(key => [key, values[key]]));
        if (segmentosEditables.value.length) {
            delete datos.options_text;
            datos.etiquetas_segmentos = values.etiquetas_segmentos;
        }
        return {...datos, catalog_service_id: props.service.id,
            quantity_component_id: values.input_type === 'number' ? values.quantity_component_id || null : null};
    });
    submit(form, `/admin/guardar/${resource.value}`, {
        onSuccess: () => {
            dialog.value = false;
        }
    });
}

// El servidor identifica la versión vigente con el mismo criterio que usa el catálogo.
const currentTariff = computed(() => props.administration.currentTariff);
const moneda = computed(() => currentTariff.value?.currency_code ?? 'ARS');
const cotizacion = computed(() => props.administration.currentCcl?.valor);
const precioConcepto = id => currentTariff.value?.components.find(p => p.service_component_id == id);
const preciosPorSegmento = computed(() => props.administration.clientSegments.map(segment => ({
    ...segment,
    price: currentTariff.value?.segments?.find(price => price.service_id == props.service.id && price.estimator_option_id == segment.id),
})));
const tienePreciosVigentes = computed(() => {
    const base = currentTariff.value?.services?.find(price => price.service_id == props.service.id);
    return Boolean((base && (Number(base.price_min) > 0 || Number(base.price_max) > 0))
        || preciosPorSegmento.value.some(segment => Boolean(segment.price)));
});
const coberturaCompleta = computed(() => preciosPorSegmento.value.length > 0
    && preciosPorSegmento.value.every(segment => Boolean(segment.price))
    && conceptosActivos.value.filter(c => c.is_default).every(c => Boolean(precioConcepto(c.id))));
const automatica = computed(() => props.service.estimation_mode === 'automatic');
const panelPrecios = ref(false);
const formularioPrecios = useForm({precios: {segmentos: [], conceptos: []}});

// La edición muestra solo las variantes y los adicionales de la ficha abierta.
function editarPrecios() {
    const reutilizar = moneda.value === 'USD';
    formularioPrecios.defaults({
        precios: {
            segmentos: preciosPorSegmento.value.map(s => prepararImporte(s.id, s.label, reutilizar ? s.price : null)),
            conceptos: conceptosActivos.value.map(c => prepararImporte(c.id, c.name, reutilizar ? precioConcepto(c.id) : null)),
        },
    });
    formularioPrecios.resetAndClearErrors();
    panelPrecios.value = true;
}

function guardarPrecios() {
    formularioPrecios.transform(({precios}) => ({precios: datosDeImportes(precios)}));
    submit(formularioPrecios, '/admin/servicios/' + props.service.id + '/precios', {
        onSuccess: () => { panelPrecios.value = false; },
    });
}
const gruposConceptos = computed(() => [
    {id: 'incluidos', titulo: 'Incluidos por defecto', registros: conceptosActivos.value.filter(c => c.is_default)},
    {
        id: 'condicionales',
        titulo: 'Según las respuestas',
        registros: conceptosActivos.value.filter(c => !c.is_default && (activeRulesFor(c.id).length || quantityQuestionsFor(c.id).length))
    },
    {
        id: 'pendientes',
        titulo: 'Sin condición configurada',
        registros: conceptosActivos.value.filter(c => !c.is_default && !activeRulesFor(c.id).length && !quantityQuestionsFor(c.id).length)
    },
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
</script>

<template>
    <div class="service-configuration">
        <!-- Cada pregunta muestra su alcance, su estado y si requiere respuesta. -->
        <template v-if="section === 'preguntas'">
            <v-card tag="section" v-for="grupo in gruposPreguntas" :key="grupo.id" class="card bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>{{ grupo.titulo }}</h2>
                        <p>{{ grupo.descripcion }}</p></div>
                    <div class="acciones-configuracion">
                        <B v-if="grupo.id === 'propias'" compact variant="secondary"
                           @click="abrirReutilizacion('preguntas')">Reutilizar pregunta</B>
                        <B compact :variant="grupo.id === 'propias' ? 'primary' : 'secondary'"
                           @click="edit('preguntas', {}, grupo.id === 'generales')">{{
                                grupo.id === 'generales' ? 'Agregar pregunta general' : 'Agregar pregunta'
                            }}</B>
                    </div>
                </div>
                <p v-if="!grupo.registros.length" class="notice">{{
                        grupo.id === 'generales' ? 'Todavía no hay preguntas generales.' : 'Todavía no hay preguntas propias de este servicio.'
                    }}</p>
                <article v-for="question in grupo.registros" :key="question.id" class="configuration-row">
                    <div>
                        <div class="estado-configuracion"><h3>{{ question.question }}</h3><S
                            :value="question.active ? 'enabled' : 'disabled'"/></div>
                        <p>{{ labelFor(question.input_type) }} · {{ question.required ? 'Obligatoria' : 'Opcional' }} ·
                            Orden {{ question.sort_order }}</p>
                        <p v-if="question.options.some(o => o.active)">
                            {{ question.options.filter(o => o.active).map(o => o.label).join(' · ') }}</p>
                    </div>
                    <B compact variant="secondary" :aria-label="'Editar pregunta ' + question.question"
                       @click="edit('preguntas', question)">Editar</B>
                </article>
            </v-card>
        </template>

        <!-- La base, los conceptos y las condiciones se revisan por separado. -->
        <template v-else-if="section === 'estimacion'">
            <v-card tag="section" class="card bloque-configuracion">
                <h2>Cómo se calcula la estimación</h2>
                <p v-if="automatica">La estimación toma el precio del tipo de cliente y suma los conceptos que correspondan.</p>
                <p v-else>Este servicio se cotiza manualmente. Podés preparar sus conceptos y reglas para cuando actives
                    la estimación automática.</p>
                <template v-if="automatica">
                    <div v-for="segment in preciosPorSegmento" :key="segment.id" class="resumen-importe">
                        <strong>{{ segment.label }}</strong><span>{{ importe(segment.price) }}</span>
                    </div>
                </template>
            </v-card>
            <v-card tag="section" class="card bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>Conceptos de precio</h2>
                        <p>Definí qué se suma al precio base y en qué cantidad.</p></div>
                    <div class="acciones-configuracion">
                        <B compact variant="secondary" @click="abrirReutilizacion('componentes')">Reutilizar
                            concepto</B>
                        <B compact @click="edit('componentes')">Agregar concepto</B>
                    </div>
                </div>
                <p v-if="!components.length" class="notice">Todavía no hay conceptos de precio.</p>
                <section v-for="grupo in gruposConceptos" :key="grupo.id" class="grupo-conceptos">
                    <h3>{{ grupo.titulo }}</h3>
                    <article v-for="concepto in grupo.registros" :key="concepto.id"
                             class="configuration-row concept-row">
                        <div>
                            <h4>{{ concepto.name }}</h4>
                            <p>{{ concepto.description }}</p>
                            <p>Unidad {{ concepto.unit_code }} · {{ importe(precioConcepto(concepto.id)) }} · {{ modosCobro.find(m => m.value === concepto.modo_cobro)?.label ?? modosCobro[0].label }}</p>
                            <p v-if="concepto.is_default">Cantidad por defecto {{ concepto.default_quantity }}</p>
                            <p v-for="pregunta in quantityQuestionsFor(concepto.id)" :key="pregunta.id">Cantidad
                                ingresada en «{{ pregunta.question }}».</p>
                        </div>
                        <B compact variant="secondary" :aria-label="'Editar concepto ' + concepto.name"
                           @click="edit('componentes', concepto)">Editar</B>
                    </article>
                </section>
            </v-card>
            <v-card tag="section" class="card bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>Reglas de cálculo</h2>
                        <p>Cada regla indica cuántas unidades suma una respuesta a un concepto.</p></div>
                    <B compact :disabled="!opcionesActivas.length || !conceptosActivos.length" @click="edit('reglas')">Agregar
                        regla</B>
                </div>
                <p v-if="!opcionesActivas.length || !conceptosActivos.length" class="notice">Para crear una regla
                    necesitás una pregunta activa con opciones y un concepto activo.</p>
                <p v-else-if="!rules.length" class="notice">Todavía no hay reglas de cálculo.</p>
                <article v-for="regla in rules" :key="regla.id" class="configuration-row">
                    <div>
                        <div class="estado-configuracion"><h3>{{ optionLabel(regla.estimator_option_id) }} →
                            {{ componentName(regla.service_component_id) }}</h3>
                            <S :value="ruleIsCurrent(regla) && conceptosActivos.some(c => c.id == regla.service_component_id) ? 'enabled' : 'disabled'"/>
                        </div>
                        <p>{{ questionFor(regla.estimator_option_id)?.question }}</p>
                        <p>Unidades que suma {{ regla.quantity }}</p>
                    </div>
                    <B compact variant="secondary"
                       :aria-label="'Editar regla ' + optionLabel(regla.estimator_option_id) + ' a ' + componentName(regla.service_component_id)"
                       @click="edit('reglas', regla)">Editar</B>
                </article>
            </v-card>
        </template>

        <!-- La ficha consulta y cambia los importes de este servicio sin salir del editor. -->
        <v-card tag="section" v-else-if="section === 'precios'" class="card bloque-configuracion">
            <div class="cabecera-configuracion">
                <div><h2>Precios vigentes</h2>
                    <p v-if="automatica && tienePreciosVigentes">Importes orientativos en {{ moneda }}.</p>
                    <p v-else-if="automatica">Cargá el importe orientativo para cada tipo de cliente.</p>
                </div>
                <div class="acciones-configuracion">
                    <B v-if="automatica" compact @click="editarPrecios">
                        {{ tienePreciosVigentes ? 'Editar precios' : 'Configurar precios' }}
                    </B>
                </div>
            </div>
            <template v-if="automatica">
                <p v-if="!coberturaCompleta" class="notice">Faltan precios para completar la estimación automática.</p>
                <dl class="lista-precios">
                    <div v-for="segment in preciosPorSegmento" :key="segment.id">
                        <dt>{{ segment.label }} <small>· precio del servicio</small></dt>
                        <dd>{{ importe(segment.price) }}</dd>
                    </div>
                    <div v-for="concepto in conceptosActivos" :key="concepto.id">
                        <dt>{{ concepto.name }} <small>· Por unidad</small></dt>
                        <dd>{{ importe(precioConcepto(concepto.id)) }}</dd>
                    </div>
                </dl>
                <p v-if="cotizacion" class="precio-ccl">CCL de referencia · USD 1 = {{ money(cotizacion, 'ARS') }}</p>
            </template>
            <p v-else class="notice"><strong>A cotizar.</strong> El precio se define en un presupuesto profesional.</p>
        </v-card>

        <SidePanel v-if="section === 'precios'" title="Precios del servicio" v-model="panelPrecios"
                   :persistent="formularioPrecios.processing">
            <form :id="formularios.precios" class="form-configuracion" @submit.prevent="guardarPrecios">
                <div class="contexto-configuracion">
                    <span>Servicio</span>
                    <strong>{{ service.name }}</strong>
                </div>
                <p class="ayuda-configuracion">Los cambios se aplican a las próximas estimaciones de este servicio.</p>
                <ImportesServicio :formulario="formularioPrecios" :cotizacion="cotizacion" obligatorio/>
            </form>
            <template #footer>
                <B variant="secondary" :disabled="formularioPrecios.processing" @click="panelPrecios = false">Cancelar</B>
                <B type="submit" :form="formularios.precios"
                   :disabled="formularioPrecios.processing || !formularioPrecios.precios.segmentos.length || !formularioPrecios.isDirty">
                    {{ formularioPrecios.processing ? 'Guardando…' : 'Guardar precios' }}
                </B>
            </template>
        </SidePanel>

        <SidePanel :title="reutilizar ? 'Reutilizar ' + titulos[reutilizar] : 'Reutilizar'"
                   :model-value="Boolean(reutilizar)"
                   @update:model-value="reutilizar = null">
            <form v-if="reutilizar" :id="formularios.plantilla" class="form-configuracion"
                  @submit.prevent="copiarPlantilla">
                <div class="contexto-configuracion">
                    <span>Servicio de destino</span>
                    <strong>{{ service.name }}</strong>
                </div>
                <template v-if="categoriasOrigen.length">
                    <F :model-value="categoriaOrigen" label="Categoría de origen" type="select"
                       :options="categoriasOrigen"
                       :menu-props="menuPlantillas" required @update:model-value="elegirCategoriaOrigen"/>
                    <F :model-value="servicioOrigen" label="Servicio de origen" type="select" :options="serviciosOrigen"
                       :menu-props="menuPlantillas" :disabled="!categoriaOrigen" required
                       @update:model-value="elegirServicioOrigen"/>
                    <F v-model="seleccionPlantilla" :label="reutilizar === 'preguntas' ? 'Pregunta' : 'Concepto'"
                       type="select" :options="opcionesPlantilla" :menu-props="menuPlantillas"
                       :disabled="!servicioOrigen" required/>
                    <p class="ayuda-configuracion">Vas a poder editar la copia antes de guardarla. Las reglas y los
                        precios se configuran por separado.</p>
                </template>
                <p v-else class="notice">No hay registros activos de otros servicios disponibles para copiar.</p>
            </form>
            <template #footer>
                <B variant="secondary" @click="reutilizar = null">Cancelar</B>
                <B type="submit" :form="formularios.plantilla" :disabled="!plantillaElegida">Usar como plantilla</B>
            </template>
        </SidePanel>

        <SidePanel :title="(form.id ? 'Editar ' : 'Agregar ') + titulos[resource]" v-model="dialog"
                   :persistent="form.processing">
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
                    <p v-if="segmentosEditables.length" class="notice">Pregunta del sistema. La respuesta define el precio base.</p>
                    <p v-else-if="alcance === 'general'" class="notice">Pregunta general. Se aplica a todos los
                        servicios.</p>
                    <F v-model="form.question" label="Pregunta" required maxlength="1000"
                       :error="form.errors.question"/>
                    <div class="field-row">
                        <F v-model="form.input_type" label="Tipo de respuesta" type="select" :options="types" required
                           :disabled="Boolean(segmentosEditables.length)"/>
                        <F v-model="form.sort_order" label="Orden de aparición" type="number" min="0" max="1000"
                           required/>
                    </div>
                    <template v-if="segmentosEditables.length">
                        <F v-for="opcion in segmentosEditables" :key="opcion.id" v-model="form.etiquetas_segmentos[opcion.id]"
                           :label="'Texto visible de ' + nombresSegmentos[opcion.clave_segmento]" required maxlength="255"/>
                    </template>
                    <F v-else-if="['single_choice','multiple_choice'].includes(form.input_type)"
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
                    <F v-model="form.modo_cobro" label="Cómo se cobra" type="select" :options="modosCobro" required
                       hint="Las configuraciones existentes mantienen su cálculo hasta que elijas otro modo."/>
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
                       type="select"
                       :options="form.id ? componentOptions : componentOptions.filter(c => conceptosActivos.some(activo => activo.id == c.value))"
                       required/>
                    <F v-model="form.quantity" label="Cantidad que incorpora" type="number" min="0" max="10000"
                       step="0.001" required/>
                </template>
                <fieldset class="controles-configuracion">
                    <legend>Opciones</legend>
                    <label v-if="resource === 'preguntas'" class="select-row">
                        <input v-model="form.required" type="checkbox" :disabled="Boolean(segmentosEditables.length)">Respuesta obligatoria
                    </label>
                    <label v-if="resource === 'componentes'" class="select-row">
                        <input v-model="form.is_default" type="checkbox">Incluir por defecto en la estimación
                    </label>
                    <label class="select-row">
                        <input v-model="form.active"
                               type="checkbox" :disabled="Boolean(segmentosEditables.length)">{{ resource === 'preguntas' ? 'Pregunta activa' : 'Activo' }}
                    </label>
                    <p v-if="form.id" class="ayuda-configuracion">Desactivar conserva las respuestas y estimaciones
                        anteriores.</p>
                </fieldset>
            </form>
            <template #footer>
                <B variant="secondary" :disabled="form.processing" @click="dialog = false">Cancelar</B>
                <B type="submit" :form="formularios.configuracion"
                   :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar' }}</B>
            </template>
        </SidePanel>
    </div>
</template>

<style scoped>
/* Las tarjetas separan cada grupo y mantienen los márgenes de la ficha. */
.service-configuration {
    display: grid;
    gap: 16px;
}

.card.bloque-configuracion {
    min-width: 0;
    padding: clamp(18px, 3vw, 28px);
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

/* Las opciones largas se leen completas dentro del ancho del panel. */
:global(.menu-plantillas-servicio .v-list-item-title) {
    white-space: normal;
    overflow-wrap: anywhere;
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
