<script setup>
import SidePanel from '../UI/SidePanel.vue';
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import { date, money, useSubmit } from '../../support/synaux';

// Cada panel filtra por el servicio actual; las preguntas generales se identifican explícitamente.
const props = defineProps({ service: Object, administration: Object, section: String });
const submit = useSubmit();
const questions = computed(() => props.administration.questions.filter(q => q.service_id == props.service.id || q.service_id == null));
const components = computed(() => props.administration.components.filter(c => c.service_id == props.service.id));
const rules = computed(() => props.administration.rules.filter(r => components.value.some(c => c.id == r.service_component_id)));
const options = computed(() => questions.value.flatMap(q => q.options.map(o => ({ value: o.id, label: `${q.question}: ${o.label}${q.active && o.active ? '' : ' (inactiva)'}` }))));
const componentOptions = computed(() => components.value.map(c => ({ value: c.id, label: c.name + (c.active ? '' : ' (inactivo)') })));
const types = [{value:'single_choice',label:'Una opción'}, {value:'multiple_choice',label:'Varias opciones'}, {value:'number',label:'Cantidad'}, {value:'text',label:'Texto libre'}];
const labelFor = type => types.find(t => t.value === type)?.label ?? type;
const questionFor = optionId => questions.value.find(q => q.options.some(o => o.id == optionId));
const optionLabel = id => questionFor(id)?.options.find(o => o.id == id)?.label ?? 'Opción inactiva';
const componentName = id => components.value.find(c => c.id == id)?.name ?? 'Componente';
const ruleIsCurrent = rule => rule.active && questionFor(rule.estimator_option_id)?.active && questionFor(rule.estimator_option_id)?.options.some(o => o.id == rule.estimator_option_id && o.active);
// Solo se muestran condiciones aplicables, incluidas las cantidades ingresadas por el cliente.
const activeRulesFor = id => rules.value.filter(rule => rule.service_component_id == id && ruleIsCurrent(rule));
const quantityQuestionsFor = id => questions.value.filter(question =>
    question.active && question.input_type === 'number' && question.quantity_component_id == id);
const dialog = ref(false);
const resource = ref('preguntas');
const form = useForm({});
const fields = {
    preguntas: ['id','service_id','question','input_type','sort_order','options_text','quantity_component_id','required','active'],
    componentes: ['id','service_id','name','slug','description','unit_code','is_default','default_quantity','active'],
    reglas: ['id','estimator_option_id','service_component_id','quantity','active'],
};
function edit(kind, row = {}, general = false) {
    resource.value = kind;
    const defaults = { id:null, service_id:general ? null : props.service.id, question:'', input_type:'single_choice', sort_order:0, options_text:'', quantity_component_id:null, required:true, active:true, name:'', slug:'', description:'', unit_code:'unidad', is_default:false, default_quantity:1, estimator_option_id:'', service_component_id:'', quantity:1 };
    const values = {...defaults, ...row, active:row.active == null ? true : Boolean(row.active), required:row.required == null ? true : Boolean(row.required), is_default:Boolean(row.is_default), options_text:row.options?.filter(o => o.active).map(o => o.label).join('\n') ?? ''};
    form.defaults(Object.fromEntries(fields[kind].map(key => [key,values[key]])));
    form.reset(); form.clearErrors(); dialog.value = true;
}
function save() {
    form.transform(values => ({...values, catalog_service_id:props.service.id, quantity_component_id:values.input_type === 'number' ? values.quantity_component_id || null : null}));
    submit(form, `/admin/guardar/${resource.value}`, {onSuccess:() => { dialog.value = false; }});
}
// La vista previa lee la versión vigente; publicar copia el resto de importes en el servidor.
const currentTariff = computed(() => props.administration.tariffs.filter(t => t.active && new Date(t.effective_from) <= new Date()).sort((a,b) => new Date(b.effective_from) - new Date(a.effective_from) || b.id-a.id)[0]);
// El resumen del concepto muestra su tarifa vigente junto a las condiciones de aplicación.
function conceptPrice(componentId) {
    const tariff = currentTariff.value;
    const price = tariff?.components.find(item => item.service_component_id == componentId);
    if (!price) return 'Sin precio vigente';
    const min = money(price.price_min, tariff.currency_code);
    return Number(price.price_min) === Number(price.price_max)
        ? min + ' por unidad'
        : min + ' a ' + money(price.price_max, tariff.currency_code) + ' por unidad';
}
const prices = useForm({ service_id:props.service.id, name:'', min:'', max:'', components:[] });
watch([currentTariff, components], () => {
    const tariff = currentTariff.value;
    const base = tariff?.services.find(p => p.service_id == props.service.id);
    prices.defaults({service_id:props.service.id, name:`Actualización de ${props.service.name}`, min:base?.price_min ?? '', max:base?.price_max ?? '', components:components.value.filter(c => c.active).map(c => {
        const price = tariff?.components.find(p => p.service_component_id == c.id);
        return {id:c.id, name:c.name, min:price?.price_min ?? '', max:price?.price_max ?? ''};
    })});
    prices.reset();
}, {immediate:true});
function publishPrices() { submit(prices, '/admin/guardar/precios-servicio'); }
</script>

<template>
    <div class="service-configuration">
        <!-- El formulario de preguntas conserva la identidad histórica de las opciones que no cambian. -->
        <template v-if="section === 'preguntas'">
            <div class="section-head"><div><h2>Preguntas del servicio</h2><p>Se muestran al elegir este servicio en la solicitud.</p></div><B compact @click="edit('preguntas')">Agregar pregunta</B></div>
            <div v-if="!questions.length" class="notice">Este servicio todavía no tiene preguntas. Podés agregar las que necesites para definir su alcance.</div>
            <article v-for="question in questions" :key="question.id" class="configuration-row">
                <div><h3>{{ question.question }}</h3><p>{{ labelFor(question.input_type) }} · {{ question.required ? 'Obligatoria' : 'Opcional' }} · Orden {{ question.sort_order }} · {{ question.active ? 'Activa' : 'Inactiva' }}</p><p v-if="question.service_id == null" class="shared-question">General: se aplica a todos los servicios.</p><p v-if="question.options.some(o => o.active)">{{ question.options.filter(o => o.active).map(o => o.label).join(' · ') }}</p></div>
                <B compact variant="secondary" @click="edit('preguntas', question)">Editar pregunta</B>
            </article>
            <details class="general-settings"><summary>Preguntas generales</summary><p>Usalas solo para datos que necesitás en todos los servicios. Sus cambios se aplican a todas las solicitudes nuevas.</p><B compact variant="secondary" @click="edit('preguntas', {}, true)">Agregar pregunta general</B></details>
        </template>
        <!-- Los componentes y reglas se editan juntos, sin mezclar datos de otros servicios. -->
        <template v-else-if="section === 'estimacion'">
            <h2>Configuración de estimación</h2><p>{{ service.estimation_mode === 'automatic' ? 'La estimación parte del precio base y suma los conceptos que correspondan según sus cantidades.' : 'Este servicio requiere cotización profesional. Sus preguntas orientan la consulta; no se publica un precio automático.' }}</p>
            <div class="section-head">
                <div><h3>Conceptos de precio</h3><p>Configurá lo que se suma al precio base, como hosting o integraciones.</p></div>
                <B compact @click="edit('componentes')">Agregar concepto</B>
            </div>
            <p v-if="!components.length">Todavía no hay conceptos de precio.</p>
            <article v-for="component in components" :key="component.id" class="configuration-row concept-row">
                <div>
                    <h3>{{ component.name }}</h3>
                    <p>{{ component.description }}</p>
                    <p><strong>{{ component.active ? (component.is_default ? 'Incluido por defecto' : (activeRulesFor(component.id).length || quantityQuestionsFor(component.id).length ? 'Según las respuestas' : 'Sin condición configurada')) : 'Inactivo' }}</strong> · Unidad {{ component.unit_code }} · {{ conceptPrice(component.id) }}</p>
                    <p v-if="component.is_default">Cantidad por defecto {{ component.default_quantity }}</p>
                    <p v-for="rule in activeRulesFor(component.id)" :key="rule.id">
                        {{ questionFor(rule.estimator_option_id)?.question }} · {{ optionLabel(rule.estimator_option_id) }} → +{{ rule.quantity }}
                    </p>
                    <p v-for="question in quantityQuestionsFor(component.id)" :key="'quantity-' + question.id">
                        La cantidad se toma de «{{ question.question }}».
                    </p>
                </div>
                <B compact variant="secondary" @click="edit('componentes', component)">Editar concepto</B>
            </article>
            <div class="section-head"><h3>Reglas de cálculo</h3><B compact :disabled="!options.length || !components.length" @click="edit('reglas')">Agregar regla</B></div>
            <p v-if="!options.length || !components.length">Para crear reglas necesitás preguntas con opciones y conceptos de precio del servicio.</p>
            <p v-else-if="!rules.length">No hay reglas configuradas.</p>
            <article v-for="rule in rules" :key="rule.id" class="configuration-row"><div><h3>{{ optionLabel(rule.estimator_option_id) }} → {{ componentName(rule.service_component_id) }}</h3><p>Incorpora {{ rule.quantity }} unidades · {{ ruleIsCurrent(rule) ? 'Activa' : 'Inactiva o con respuesta desactivada' }}</p></div><B compact variant="secondary" @click="edit('reglas',rule)">Editar regla</B></article>
        </template>
        <!-- La publicación conserva la cobertura y los valores históricos de los demás servicios. -->
        <template v-else-if="section === 'precios'">
            <h2>Precios vigentes</h2><p v-if="currentTariff">{{ currentTariff.name }} · {{ currentTariff.currency_code }} · Desde {{ date(currentTariff.effective_from) }}</p><p v-else>No hay un tarifario vigente.</p>
            <p>Guardá una versión nueva para actualizar este servicio. Los precios anteriores y los demás servicios se conservan.</p>
            <p v-if="service.estimation_mode !== 'automatic'" class="notice">El catálogo mostrará cotización profesional mientras la modalidad del servicio sea manual.</p>
            <form @submit.prevent="publishPrices">
                <div v-if="Object.keys(prices.errors).length" class="notice" role="alert"><p v-for="(error,key) in prices.errors" :key="key">{{ error }}</p></div>
                <F v-model="prices.name" label="Nombre de la nueva versión" required maxlength="255" />
                <h3>Base del servicio · {{ currentTariff?.currency_code ?? 'ARS' }}</h3><div class="field-row"><F v-model="prices.min" label="Precio mínimo" type="number" min="0" max="100000000" step="0.01" required :error="prices.errors.min" /><F v-model="prices.max" label="Precio máximo" type="number" min="0" max="100000000" step="0.01" required :error="prices.errors.max" /></div>
                <div v-for="(component,index) in prices.components" :key="component.id" class="component-price"><h3>{{ component.name }}</h3><div class="field-row"><F v-model="component.min" label="Precio mínimo por unidad" type="number" min="0" step="0.01" required :error="prices.errors[`components.${index}.min`]" /><F v-model="component.max" label="Precio máximo por unidad" type="number" min="0" step="0.01" required :error="prices.errors[`components.${index}.max`]" /></div></div>
                <p>Las reglas de respuestas y las cantidades se suman a esta base. Para un importe fijo, usá el mismo mínimo y máximo.</p>
                <div class="form-actions"><B variant="secondary" href="/admin/tarifario">Ver tarifarios</B><B type="submit" :disabled="prices.processing">{{ prices.processing ? 'Publicando…' : 'Publicar nueva versión' }}</B></div>
            </form>
        </template>
        <SidePanel title="Configuración del servicio" v-model="dialog" max-width="560" :persistent="form.processing"><div class="synaux-app"><v-card class="card modal-card"><h2>{{ form.id ? 'Editar' : 'Agregar' }} {{ {preguntas:'pregunta',componentes:'concepto de precio',reglas:'regla'}[resource] }}</h2><p>{{ form.service_id === null ? 'Pregunta general para todos los servicios' : service.name }}</p><form @submit.prevent="save">
            <div v-if="Object.keys(form.errors).length" class="notice" role="alert"><p v-for="(error,key) in form.errors" :key="key">{{ error }}</p></div>
            <template v-if="resource === 'preguntas'">
                <F v-model="form.question" label="Pregunta" required maxlength="1000" :error="form.errors.question" /><F v-model="form.input_type" label="Tipo de respuesta" type="select" :options="types" required />
                <F v-if="['single_choice','multiple_choice'].includes(form.input_type)" v-model="form.options_text" label="Opciones (una por línea)" type="textarea" required maxlength="5000" hint="Las opciones sin cambios conservan sus reglas. Si cambiás un texto, revisá las reglas vinculadas." />
                <F v-if="form.input_type === 'number' && form.service_id !== null" v-model="form.quantity_component_id" label="Concepto cuya cantidad define (opcional)" type="select" :options="[{value:'',label:'Sin componente'}, ...componentOptions]" />
                <F v-model="form.sort_order" label="Orden de aparición" type="number" min="0" max="1000" required /><label class="select-row"><input v-model="form.required" type="checkbox">Respuesta obligatoria</label>
            </template>
            <template v-else-if="resource === 'componentes'">
                <F v-model="form.name" label="Nombre" required maxlength="255" /><F v-model="form.slug" label="Identificador" required maxlength="150" hint="Letras, números y guiones; único dentro del servicio." /><F v-model="form.description" label="Descripción" type="textarea" required maxlength="10000" /><div class="field-row"><F v-model="form.unit_code" label="Unidad" required maxlength="32" /><F v-model="form.default_quantity" label="Cantidad inicial" type="number" min="0.001" max="10000" step="0.001" required /></div><label class="select-row"><input v-model="form.is_default" type="checkbox">Incluir por defecto en la estimación</label>
            </template>
            <template v-else><F v-model="form.estimator_option_id" label="Respuesta que activa la regla" type="select" :options="options" required /><F v-model="form.service_component_id" label="Concepto de precio de este servicio" type="select" :options="componentOptions" required /><F v-model="form.quantity" label="Cantidad que incorpora" type="number" min="0" max="10000" step="0.001" required /></template>
            <label class="select-row"><input v-model="form.active" type="checkbox">{{ resource === 'preguntas' ? 'Pregunta activa' : 'Activo' }}</label><p>Desactivar conserva las respuestas y estimaciones anteriores.</p>
            <div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="dialog=false">Cancelar</B><B type="submit" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar' }}</B></div>
        </form></v-card></div></SidePanel>
    </div>
</template>

<style scoped>
/* Los registros mantienen una jerarquía compacta y sus acciones pasan debajo en móvil. */
.configuration-row { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:18px 0; border-bottom:1px solid #1f6f7f22; }
.configuration-row > div { min-width:0; }
.configuration-row h3 { font-size:1rem; margin:0 0 6px; overflow-wrap:anywhere; }
.configuration-row p { margin:4px 0; color:#446075; font-size:.875rem; text-wrap:pretty; }
/* El concepto agrupa descripción, activación e importe; sus acciones siguen en un panel lateral. */
.concept-row { margin-bottom:12px; padding:16px; background:#f5f9fa; border:1px solid #1f6f7f26; border-radius:10px; }
.service-configuration h2, .service-configuration .section-head h3 { font-weight:700; }
.service-configuration .section-head p { margin:4px 0 0; }
.shared-question { color:#1f6f7f; font-weight:700; }
.general-settings { margin-top:24px; padding:16px; background:#f5f9fa; border-radius:10px; }
.general-settings summary { cursor:pointer; font-weight:700; }
.component-price { padding-top:16px; border-top:1px solid #1f6f7f22; }
.service-configuration .section-head { margin-top:24px; }
@media (max-width:600px) { .configuration-row { align-items:flex-start; flex-direction:column; } }
</style>
