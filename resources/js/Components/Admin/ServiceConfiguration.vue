<script setup>
import SidePanel from '../UI/SidePanel.vue';
import {computed, ref, useId} from 'vue';
import {VListItem, VSelect, VTooltip} from 'vuetify/components';
import {useForm} from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import S from '../UI/StatusBadge.vue';
import AppIcon from '../UI/AppIcon.vue';
import ImportesServicio from './ImportesServicio.vue';
import SimuladorEstimacion from './SimuladorEstimacion.vue';
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
const preguntaSistema = computed(() => questions.value.find(q => q.clave_sistema === 'tipo_cliente'));
const gruposPreguntas = computed(() => [
    {
        id: 'propias',
        titulo: 'Preguntas de este servicio',
        descripcion: 'Definen el alcance de este servicio.',
        registros: questions.value.filter(q => q.service_id != null && q.clave_sistema !== 'tipo_cliente')
    },
    {
        id: 'generales',
        titulo: 'Preguntas generales',
        descripcion: 'Se aplican a todos los servicios. Los cambios afectan las solicitudes nuevas.',
        registros: questions.value.filter(q => q.service_id == null && q.clave_sistema !== 'tipo_cliente')
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
const nombreConceptoCobrado = rule => {
    const componente = components.value.find(item => Number(item.id) === Number(rule.service_component_id));
    const modo = modosCobro.find(item => item.value === componente?.modo_cobro)?.label ?? modosCobro[0].label;
    return `${componentName(rule.service_component_id)} · ${modo}`;
};
// Solo se muestran condiciones aplicables, incluidas las cantidades ingresadas por el cliente.
const activeRulesFor = id => rules.value.filter(rule => rule.service_component_id == id && ruleIsCurrent(rule));
const quantityQuestionsFor = id => questions.value.filter(question =>
    question.active && question.input_type === 'number' && question.quantity_component_id == id);
// Resume preguntas, opciones y reglas asociadas a cada concepto sin crear una relación paralela.
const reglasDelConcepto = id => rules.value.filter(rule => Number(rule.service_component_id) === Number(id));
const reglaOpcion = id => rules.value.filter(rule => Number(rule.estimator_option_id) === Number(id));
const conceptosDeOpcion = id => reglaOpcion(id).filter(rule => rule.active).map(nombreConceptoCobrado);
const usoDelConcepto = id => {
    const reglas = reglasDelConcepto(id);
    const preguntas = new Set(reglas.map(rule => questionFor(rule.estimator_option_id)?.id).filter(Boolean));
    quantityQuestionsFor(id).forEach(question => preguntas.add(question.id));
    return {preguntas: preguntas.size, opciones: new Set(reglas.map(rule => rule.estimator_option_id)).size, reglas: reglas.length};
};
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
    const copia = {...original, id: null, service_id: props.service.id, quantity_component_id: null, is_default: false};
    if (tipo === 'preguntas') copia.options = (original.options ?? []).filter(option => option.active).map(option => ({
        id: null, label: option.label, component_ids: [], active: true,
    }));
    edit(tipo, copia);
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
    preguntas: ['id', 'service_id', 'question', 'input_type', 'sort_order', 'options_text', 'options', 'quantity_component_id', 'required', 'active'],
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
        options: [],
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
        options_text: row.options?.filter(o => o.active).map(o => o.label).join('\n') ?? '',
        options: row.options?.filter(o => o.active).map(o => ({
            id: o.id ?? null, label: o.label,
            component_ids: o.component_ids ?? rules.value.filter(rule => Number(rule.estimator_option_id) === Number(o.id)
                && components.value.some(component => Number(component.id) === Number(rule.service_component_id)))
                .map(rule => Number(rule.service_component_id)),
        })) ?? [],
    };
    if (kind === 'preguntas' && !segmentosEditables.value.length
        && ['single_choice', 'multiple_choice'].includes(values.input_type) && !values.options.length) {
        values.options = [{id: null, label: '', component_ids: []}];
    }
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
            delete datos.options;
            datos.etiquetas_segmentos = values.etiquetas_segmentos;
        } else if (resource.value === 'preguntas') {
            delete datos.options_text;
            if (!['single_choice', 'multiple_choice'].includes(values.input_type)) delete datos.options;
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

// Cada opción conserva su ID al cambiar el texto y edita solo reglas del servicio abierto.
function agregarOpcion() {
    form.options.push({id: null, label: '', component_ids: []});
}

function quitarOpcion(indice) {
    form.options.splice(indice, 1);
}

// Cada selector muestra los conceptos activos del servicio y conserva visibles los inactivos ya vinculados.
function opcionesConceptosPara(opcion) {
    const vinculados = new Set((opcion.component_ids ?? []).map(Number));
    return components.value
        .filter(concepto => concepto.active || vinculados.has(Number(concepto.id)))
        .map(concepto => {
            const precio = precioConcepto(concepto.id);
            const modo = modosCobro.find(item => item.value === concepto.modo_cobro)?.label ?? modosCobro[0].label;
            const tienePrecioVigente = precio?.price_min != null && precio?.price_max != null;
            const importeReferencia = tienePrecioVigente
                ? importe(precio) + ' ' + moneda.value
                : 'Sin precio cargado · requiere cotización';
            return {
                value: Number(concepto.id),
                label: concepto.name + (concepto.active ? '' : ' (inactivo)'),
                detalle: `${modo} · ${importeReferencia}`,
            };
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
const conceptosSinVinculo = computed(() => conceptosActivos.value.filter(concepto => !concepto.is_default
    && !activeRulesFor(concepto.id).length && !quantityQuestionsFor(concepto.id).length));
const conceptosSinPrecioVinculados = computed(() => conceptosActivos.value.filter(concepto =>
    !concepto.is_default && (activeRulesFor(concepto.id).length || quantityQuestionsFor(concepto.id).length)
    && !precioConcepto(concepto.id)));
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
            <v-card v-if="preguntaSistema" tag="section" class="card bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>Pregunta del sistema</h2>
                        <p>Identifica el tipo de cliente y determina qué precio base corresponde.</p></div>
                    <B compact variant="secondary" :aria-label="'Editar pregunta del sistema ' + preguntaSistema.question"
                       @click="edit('preguntas', preguntaSistema)">Editar</B>
                </div>
                <div class="configuration-row">
                    <div><div class="estado-configuracion"><h3>{{ preguntaSistema.question }}</h3><S value="enabled"/></div>
                        <div class="opciones-pregunta">
                            <span v-for="opcion in preguntaSistema.options.filter(o => o.active)" :key="opcion.id">
                                {{ opcion.label }}
                            </span>
                        </div>
                    </div>
                </div>
            </v-card>
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
                        <div v-if="question.options.some(o => o.active)" class="opciones-pregunta">
                            <div v-for="opcion in question.options.filter(o => o.active)" :key="opcion.id">
                                <strong>{{ opcion.label }}</strong>
                                <span>{{ conceptosDeOpcion(opcion.id).join(' · ') || 'Sin adicional asociado' }}</span>
                            </div>
                        </div>
                        <p v-if="question.quantity_component_id">La cantidad se vincula con «{{ componentName(question.quantity_component_id) }}».</p>
                    </div>
                    <B compact variant="secondary" :aria-label="'Editar pregunta ' + question.question"
                       @click="edit('preguntas', question)">Editar</B>
                </article>
            </v-card>
        </template>

        <!-- La pestaña explica el recorrido económico y mantiene accesibles las reglas avanzadas existentes. -->
        <template v-else-if="section === 'estimacion'">
            <v-card tag="section" class="card bloque-configuracion">
                <h2>Composición de la estimación</h2>
                <div class="paso-calculo">
                    <strong>1. Precio base</strong>
                    <span v-if="automatica">Se elige en «Precios y adicionales» según Particular, PyME o Empresa.</span>
                    <span v-else>Este servicio no muestra precio público; Gestión prepara un presupuesto a medida.</span>
                </div>
                <div class="paso-calculo">
                    <strong>2. Adicionales</strong>
                    <span>Las respuestas vinculadas suman conceptos. Las preguntas numéricas pueden aportar su cantidad.</span>
                </div>
                <p v-if="automatica && !coberturaCompleta" class="notice" role="status">Faltan precios base o importes de prestaciones incluidas para calcular una referencia completa.</p>
                <p v-if="!conceptosActivos.length" class="notice">Este servicio usa solo su precio base y no necesita adicionales.</p>
                <p v-if="conceptosSinVinculo.length" class="notice">{{ conceptosSinVinculo.length }} concepto(s) activo(s) todavía no se vinculan a respuestas ni a cantidades.</p>
                <p v-if="conceptosSinPrecioVinculados.length" class="notice">Algunos conceptos vinculados no tienen precio vigente. Si se seleccionan, el cálculo se deriva a revisión profesional.</p>
            </v-card>
            <v-card tag="section" class="card bloque-configuracion">
                <div class="cabecera-configuracion">
                    <div><h2>Reglas de cálculo</h2>
                        <p>Cada regla avanzada suma unidades cuando se elige una opción.</p></div>
                    <B compact :disabled="!opcionesActivas.length || !conceptosActivos.length" @click="edit('reglas')">Agregar
                        regla</B>
                </div>
                <p v-if="!opcionesActivas.length || !conceptosActivos.length" class="notice">Para crear una regla
                    necesitás una pregunta activa con opciones y un concepto activo.</p>
                <p v-else-if="!rules.length" class="notice">Todavía no hay reglas de cálculo.</p>
                <article v-for="regla in rules" :key="regla.id" class="configuration-row">
                    <div>
                        <div class="estado-configuracion"><h3>{{ questionFor(regla.estimator_option_id)?.question }} · {{ optionLabel(regla.estimator_option_id) }} →
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
            <SimuladorEstimacion :service="service" :questions="questions"/>
        </template>

        <!-- La ficha consulta y cambia los importes de este servicio sin salir del editor. -->
        <v-card tag="section" v-else-if="section === 'precios'" class="card bloque-configuracion">
            <div class="cabecera-configuracion">
                <div><h2>Precios vigentes</h2>
                    <p v-if="automatica && tienePreciosVigentes">Importes orientativos en {{ moneda }}.</p>
                    <p v-else-if="automatica">Cargá el importe orientativo para cada tipo de cliente.</p>
                    <p v-else>Este servicio se cotiza a medida y no publica un precio base.</p>
                </div>
                <div class="acciones-configuracion">
                    <B v-if="automatica" compact @click="editarPrecios">
                        {{ tienePreciosVigentes ? 'Editar precios' : 'Configurar precios' }}
                    </B>
                </div>
            </div>
            <template v-if="automatica">
                <p v-if="!coberturaCompleta" class="notice" role="status">Faltan precios base o importes de prestaciones incluidas para completar la estimación automática.</p>
                <dl class="lista-precios">
                    <div v-for="segment in preciosPorSegmento" :key="segment.id">
                        <dt>{{ segment.label }} <small>· precio del servicio</small></dt>
                        <dd>{{ importe(segment.price) }}</dd>
                    </div>
                </dl>
                <p v-if="cotizacion" class="precio-ccl">CCL de referencia · USD 1 = {{ money(cotizacion, 'ARS') }}</p>
            </template>
            <p v-else class="notice"><strong>A cotizar.</strong> El precio se define en un presupuesto profesional.</p>
        </v-card>

        <v-card v-if="section === 'precios'" tag="section" class="card bloque-configuracion">
            <div class="cabecera-configuracion">
                <div><h2>Adicionales del servicio</h2>
                    <p>Administrá cada concepto y su importe aquí. Las respuestas se vinculan en la pestaña Preguntas.</p></div>
                <div class="acciones-configuracion">
                    <B compact variant="secondary" @click="abrirReutilizacion('componentes')">Reutilizar concepto</B>
                    <B compact @click="edit('componentes')">Agregar concepto</B>
                </div>
            </div>
            <p v-if="!components.length" class="notice">Todavía no hay adicionales para este servicio.</p>
            <section v-for="grupo in gruposConceptos" :key="grupo.id" class="grupo-conceptos">
                <h3>{{ grupo.titulo }}</h3>
                <article v-for="concepto in grupo.registros" :key="concepto.id" class="configuration-row concept-row">
                    <div>
                        <div class="estado-configuracion"><h3>{{ concepto.name }}</h3><S :value="concepto.active ? 'enabled' : 'disabled'"/></div>
                        <p v-if="concepto.description">{{ concepto.description }}</p>
                        <p class="concepto-detalle">{{ importe(precioConcepto(concepto.id)) }} · {{ concepto.unit_code }} ·
                            {{ modosCobro.find(m => m.value === concepto.modo_cobro)?.label ?? modosCobro[0].label }}</p>
                        <p v-if="concepto.is_default">Incluido en el precio base · cantidad inicial {{ concepto.default_quantity }}</p>
                        <p v-for="pregunta in quantityQuestionsFor(concepto.id)" :key="pregunta.id">Cantidad definida por «{{ pregunta.question }}».</p>
                        <p class="concepto-uso">Usado en {{ usoDelConcepto(concepto.id).preguntas }} preguntas,
                            {{ usoDelConcepto(concepto.id).opciones }} opciones y {{ usoDelConcepto(concepto.id).reglas }} reglas.</p>
                    </div>
                    <B compact variant="secondary" :aria-label="'Editar adicional ' + concepto.name"
                       @click="edit('componentes', concepto)">Editar</B>
                </article>
            </section>
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
                    <section v-else-if="['single_choice','multiple_choice'].includes(form.input_type)" class="opciones-editor">
                        <div class="opciones-editor-heading"><div><h3>Respuestas y adicionales</h3>
                            <p>Podés dejar una respuesta sin adicional o asociarle varios conceptos de este servicio.</p></div>
                        </div>
                        <p v-if="!conceptosActivos.length" class="notice conceptos-sin-disponibles" role="status">
                            No hay adicionales configurados. Podés vincularlos posteriormente desde Precios y adicionales.
                        </p>
                        <article v-for="(opcion, indice) in form.options" :key="opcion.id ?? 'nueva-' + indice" class="opcion-editor-row">
                            <div class="opcion-editor-row-heading">
                                <h4>Respuesta {{ indice + 1 }}</h4>
                                <VTooltip text="Quitar respuesta" location="top" open-on-focus>
                                    <template #activator="{props: tooltipProps}">
                                        <B v-bind="tooltipProps" class="respuesta-quitar" compact variant="ghost" type="button"
                                           :disabled="form.options.length <= 1"
                                           :aria-label="'Quitar respuesta ' + (indice + 1)" @click="quitarOpcion(indice)">
                                            <AppIcon name="trash"/>
                                        </B>
                                    </template>
                                </VTooltip>
                            </div>
                            <F v-model="opcion.label" label="Texto de la respuesta" required maxlength="255"
                               :error="form.errors[`options.${indice}.label`]"/>
                            <div v-if="conceptosActivos.length" class="opcion-vinculo">
                                <label :for="idEditor + '-opcion-' + indice">Conceptos que suma (opcional)</label>
                                <VSelect :id="idEditor + '-opcion-' + indice"
                                         v-model="opcion.component_ids" :items="opcionesConceptosPara(opcion)"
                                         item-title="label" item-value="value" multiple chips closable-chips
                                         density="compact" variant="outlined" color="primary" hide-details
                                         placeholder="Seleccionar adicionales"
                                         :menu-props="{maxHeight: 300}" class="selector-conceptos-opcion">
                                    <template #item="{props: itemProps, item}">
                                        <VListItem v-bind="itemProps" :subtitle="item.raw.detalle"/>
                                    </template>
                                    <template #selection="{item, props: selectionProps}">
                                        <v-chip v-bind="selectionProps" size="small" color="primary">
                                            {{ item.raw.label }}
                                        </v-chip>
                                    </template>
                                </VSelect>
                                <small>Sin selección, esta respuesta no agrega un importe.</small>
                                <small v-if="form.errors[`options.${indice}.component_ids.0`]" class="error" role="alert">
                                    {{ form.errors[`options.${indice}.component_ids.0`] }}</small>
                            </div>
                        </article>
                        <B class="agregar-respuesta" compact variant="secondary" type="button" @click="agregarOpcion">
                            Agregar respuesta
                        </B>
                    </section>
                    <F v-if="form.input_type === 'number' && form.service_id !== null"
                       v-model="form.quantity_component_id" label="Concepto cuya cantidad define (opcional)"
                       type="select" :options="[{value:'',label:'Sin concepto adicional'}, ...componentOptions]"
                       hint="La cantidad ingresada se aplica al modo de cobro configurado en el concepto."/>
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
                    <legend>Configuración</legend>
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

.opciones-pregunta {
    display: grid;
    gap: 7px;
    margin-top: 10px;
}

.opciones-pregunta > div, .opciones-pregunta > span {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 6px 12px;
    color: #446075;
    font-size: .875rem;
}

.opciones-pregunta strong { color: #153b64; }

.paso-calculo {
    display: grid;
    gap: 3px;
    margin-top: 14px;
    padding: 12px 14px;
    border-left: 3px solid #1f6f7f;
    border-radius: 6px;
    background: #f5f9fa;
}

.paso-calculo span, .concepto-uso { color: #446075; font-size: .875rem; }

.opciones-editor {
    display: grid;
    gap: 10px;
    min-width: 0;
}

.opciones-editor-heading {
    display: grid;
    gap: 4px;
}

.opciones-editor-heading h3 { color: #153b64; font-size: 1rem; }
.opciones-editor-heading p, .opcion-vinculo small { color: #446075; font-size: .8125rem; }

.opcion-editor-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
    gap: 8px;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #1f6f7f26;
    border-radius: 10px;
    background: #fff;
}

.opcion-editor-row-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; min-width:0; }
.opcion-editor-row-heading h4 { margin:0; color:#153b64; font-size:.95rem; }
.opcion-editor-row-heading :deep(.respuesta-quitar.v-btn) { width:36px; min-width:36px; height:36px; padding:0; box-shadow:none; }
.opcion-editor-row-heading :deep(.respuesta-quitar .icon) { width:18px; height:18px; }
.opcion-vinculo { display: grid; gap: 4px; min-width: 0; }
.opcion-vinculo > label { color: #153b64; font-size: .875rem; }
.opcion-vinculo :deep(.v-field) { min-width:0; max-width:100%; background: #fff; border-radius: 8px; }
.opcion-vinculo :deep(.v-chip) { max-width: 100%; }
.opcion-vinculo :deep(.v-field__input) { min-width:0; flex-wrap:wrap; }
.opcion-vinculo :deep(.selector-conceptos-opcion .v-field__input input) {
    width: auto;
    min-width: 0;
    max-width: 100%;
    min-height: 0;
    height: auto;
    flex: 1 1 24px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: inherit;
    box-shadow: none;
    outline: none;
    font: inherit;
}
.opcion-vinculo :deep(.selector-conceptos-opcion .v-field__input input:focus) {
    border: 0;
    outline: none;
    box-shadow: none;
}
.opcion-vinculo :deep(.selector-conceptos-opcion .v-field__input input::placeholder) {
    color: #446075;
    opacity: 1;
}
.opcion-vinculo :deep(.v-list-item-subtitle) { color:#446075; opacity:1; }
.opcion-vinculo .error { color: #a63a45; }
.conceptos-sin-disponibles { margin:0; }
.agregar-respuesta { justify-self:start; }

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

    .opcion-editor-row { grid-template-columns: minmax(0, 1fr); align-items: stretch; }
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
