<script setup>
import {computed, reactive, ref, watch} from 'vue';
import axios from 'axios';
import {money} from '../../support/formatters';
import F from '../UI/FormField.vue';
import SelectorSiNo from '../UI/SelectorSiNo.vue';
import {preguntasVisibles as resolverVisibilidad} from '../../support/preguntasVisibles';

const props = defineProps({service: {type: Object, required: true}, questions: {type: Array, required: true}});
const respuestas = reactive({});
const resultado = ref(null);
const cargando = ref(false);
const error = ref('');
const desactualizado = ref(false);
const tipos = {
    single_choice: 'Una opción', multiple_choice: 'Varias opciones', number: 'Cantidad', text: 'Texto libre',
};
const preguntasConfiguradas = computed(() => props.questions
    .filter(pregunta => pregunta.active && (pregunta.service_id == null || pregunta.service_id == props.service.id))
    .map(pregunta => ({...pregunta, options: pregunta.options.filter(opcion => opcion.active)})));
const preguntasActivas = computed(() => resolverVisibilidad(preguntasConfiguradas.value, respuestas));
// Las respuestas de preguntas que se ocultan se eliminan del envío de simulación.
watch(preguntasActivas, visibles => {
    const ids = new Set(visibles.map(pregunta => String(pregunta.id)));
    for (const id of Object.keys(respuestas)) if (!ids.has(String(id))) delete respuestas[id];
});
const opcionesTipoCliente = computed(() => preguntasActivas.value.find(p => p.clave_sistema === 'tipo_cliente')?.options ?? []);
const preguntasAdicionales = computed(() => preguntasActivas.value.filter(pregunta => pregunta.clave_sistema !== 'tipo_cliente'));

// Si cambia una respuesta, conservamos el resultado anterior como referencia pero lo marcamos desactualizado.
watch(respuestas, () => { desactualizado.value = Boolean(resultado.value); }, {deep: true});

// El servidor valida la configuración guardada y ejecuta el mismo motor que usa el estimador comercial.
async function calcular() {
    cargando.value = true;
    error.value = '';
    try {
        const {data} = await axios.post(`/admin/servicios/${props.service.id}/simular`, {answers: {...respuestas}});
        resultado.value = data;
        desactualizado.value = false;
    } catch (exception) {
        resultado.value = null;
        error.value = exception.response?.data?.message
            ?? Object.values(exception.response?.data?.errors ?? {}).flat()[0]
            ?? 'No se pudo calcular la simulación. Revisá las respuestas e intentá de nuevo.';
    } finally {
        cargando.value = false;
    }
}

// Reinicia solamente los campos locales de esta previsualización; no modifica datos guardados.
function reiniciar() {
    Object.keys(respuestas).forEach(id => delete respuestas[id]);
    resultado.value = null;
    error.value = '';
    desactualizado.value = false;
}

function opciones(pregunta) {
    return pregunta.options.map(opcion => ({label: opcion.label, value: opcion.id}));
}
function moneda(valor, codigo) {
    return valor === null || valor === undefined ? 'Pendiente de cotización' : money(valor, codigo);
}
// El equivalente orientativo se calcula con la cotización que devolvió el mismo cálculo del servidor.
function equivalenteEnPesos(valor) {
    const ccl = Number(resultado.value?.ccl?.valor);
    return resultado.value?.moneda === 'USD' && Number.isFinite(ccl) && valor !== null && valor !== undefined
        ? money(Number(valor) * ccl, 'ARS') : null;
}
function modoCobro(valor) {
    return ({unico: 'Cargo único', acumulativo: 'Cargo acumulativo', cantidad: 'Por cantidad', heredado: 'Cálculo heredado'})[valor]
        ?? 'Cálculo heredado';
}
const estados = {
    listo: ['Cálculo completo', 'estado-listo'],
    respuestas_pendientes: ['Faltan respuestas obligatorias', 'estado-pendiente'],
    segmento_pendiente: ['Elegí un tipo de cliente', 'estado-pendiente'],
    segmento_sin_precio: ['El segmento no tiene precio', 'estado-revision'],
    precio_base_sin_configurar: ['Falta configurar el precio base', 'estado-error'],
    conceptos_sin_precio: ['Hay adicionales sin precio', 'estado-revision'],
    revision_profesional: ['Requiere revisión profesional', 'estado-revision'],
    error_configuracion: ['Error de configuración tarifaria', 'estado-error'],
};
</script>

<template>
    <section class="card simulador-estimacion" aria-labelledby="titulo-simulador">
        <header class="simulador-cabecera">
            <div>
                <p class="simulador-eyebrow">Prueba interna · usa configuración guardada</p>
                <h2 id="titulo-simulador">Simulador de estimación</h2>
                <p>Probá las respuestas y comprobá el precio antes de publicar el servicio.</p>
            </div>
            <span class="simulador-servicio">{{ service.active ? 'Servicio publicado' : 'Servicio en borrador' }}</span>
        </header>

        <div class="simulador-columnas">
            <form class="simulador-formulario" @submit.prevent="calcular">
                <h3>Preguntas y respuestas</h3>
                <p v-if="!preguntasActivas.length" class="simulador-vacio">No hay preguntas activas para probar. El motor podrá calcular solo el precio base.</p>
                <p v-else-if="!preguntasAdicionales.length" class="simulador-vacio" role="status">
                    Este servicio no tiene preguntas adicionales configuradas. Podés probar el precio base según el tipo de cliente.
                </p>
                <div v-for="pregunta in preguntasActivas" :key="pregunta.id" class="simulador-pregunta">
                    <SelectorSiNo v-if="pregunta.is_yes_no" v-model="respuestas[pregunta.id]" :question="pregunta"
                                  :required="Boolean(pregunta.required)"/>
                    <template v-else>
                        <label class="simulador-etiqueta" :for="`simulacion-${pregunta.id}`">
                            {{ pregunta.question }}<span v-if="pregunta.required"> *</span>
                        </label>
                        <F v-if="pregunta.input_type === 'single_choice'"
                           v-model="respuestas[pregunta.id]" type="select" :options="opciones(pregunta)"
                           placeholder="Seleccioná una opción"/>
                        <fieldset v-else-if="pregunta.input_type === 'multiple_choice'" class="simulador-opciones">
                            <legend class="visually-hidden">{{ pregunta.question }}</legend>
                            <label v-for="opcion in pregunta.options" :key="opcion.id" class="simulador-opcion">
                                <input v-model="respuestas[pregunta.id]" type="checkbox" :value="opcion.id">
                                <span>{{ opcion.label }}</span>
                            </label>
                        </fieldset>
                        <F v-else-if="pregunta.input_type === 'number'" v-model="respuestas[pregunta.id]"
                           type="number" min="1" max="10000" step="1" placeholder="Ingresá una cantidad"/>
                        <F v-else v-model="respuestas[pregunta.id]" type="textarea"
                           placeholder="Escribí una respuesta de prueba"/>
                    </template>
                </div>
                <div class="simulador-acciones">
                    <button type="button" class="simulador-boton simulador-secundario" @click="reiniciar">Reiniciar</button>
                    <button type="submit" class="simulador-boton simulador-principal" :disabled="cargando">
                        {{ cargando ? 'Calculando…' : 'Calcular simulación' }}
                    </button>
                </div>
            </form>

            <section class="simulador-resultado" aria-labelledby="titulo-resultado" aria-live="polite">
                <h3 id="titulo-resultado">Resultado y desglose</h3>
                <p v-if="error" class="simulador-alerta estado-error" role="alert">{{ error }}</p>
                <template v-if="resultado">
                    <p v-if="desactualizado" class="simulador-alerta estado-pendiente" role="status">
                        Cambiaste respuestas. Volvé a calcular para actualizar este resultado.
                    </p>
                    <div class="simulador-estados">
                        <span v-for="estado in resultado.estado" :key="estado" class="simulador-estado"
                              :class="estados[estado]?.[1] ?? 'estado-error'">
                            {{ estados[estado]?.[0] ?? estado }}
                        </span>
                    </div>
                    <p v-if="resultado.preguntas_faltantes.length" class="simulador-ayuda">
                        Pendientes: {{ resultado.preguntas_faltantes.join(' · ') }}
                    </p>
                    <dl class="simulador-base">
                        <div><dt>Precio base <span v-if="resultado.base.segmento">· {{ resultado.base.segmento }}</span></dt>
                            <dd>{{ moneda(resultado.base.minimo, resultado.moneda) }}<template v-if="resultado.base.minimo !== resultado.base.maximo && resultado.base.maximo !== null"> a {{ moneda(resultado.base.maximo, resultado.moneda) }}</template></dd></div>
                    </dl>
                    <div v-if="resultado.adicionales.length" class="simulador-lista-adicionales">
                        <h4>Adicionales aplicados</h4>
                        <article v-for="(item, index) in resultado.adicionales" :key="`${item.component_id}-${index}`" class="simulador-linea">
                            <div class="simulador-linea-cabecera"><strong>{{ item.name }}</strong><strong>{{ moneda(item.subtotal_min, resultado.moneda) }}<template v-if="item.subtotal_min !== item.subtotal_max && item.subtotal_max !== null"> a {{ moneda(item.subtotal_max, resultado.moneda) }}</template></strong></div>
                            <p>{{ modoCobro(item.modo_cobro) }} · {{ item.quantity }} {{ item.unit }} × {{ moneda(item.min, resultado.moneda) }}<template v-if="item.min !== item.max && item.max !== null"> a {{ moneda(item.max, resultado.moneda) }}</template></p>
                            <p v-if="item.activado_por.length">Activado por: {{ item.activado_por.join(' · ') }}</p>
                        </article>
                    </div>
                    <p v-else class="simulador-vacio">No se aplicaron adicionales.</p>
                    <dl class="simulador-totales">
                        <div><dt>{{ resultado.total_adicionales.parcial ? 'Adicionales conocidos · parcial' : 'Total de adicionales' }}</dt><dd>{{ moneda(resultado.total_adicionales.minimo, resultado.moneda) }}<template v-if="resultado.total_adicionales.minimo !== resultado.total_adicionales.maximo"> a {{ moneda(resultado.total_adicionales.maximo, resultado.moneda) }}</template></dd></div>
                        <div v-if="resultado.subtotal_conocido && (resultado.subtotal_conocido.parcial || !resultado.total_estimado)"><dt>{{ resultado.subtotal_conocido.parcial ? 'Subtotal conocido · parcial' : 'Total estimado' }}</dt><dd>{{ moneda(resultado.subtotal_conocido.minimo, resultado.moneda) }}<template v-if="resultado.subtotal_conocido.minimo !== resultado.subtotal_conocido.maximo"> a {{ moneda(resultado.subtotal_conocido.maximo, resultado.moneda) }}</template></dd></div>
                        <div v-if="resultado.total_estimado" class="simulador-total-final">
                            <dt>Total estimado</dt>
                            <dd>
                                <strong>{{ moneda(resultado.total_estimado.minimo, resultado.moneda) }}<template v-if="resultado.total_estimado.minimo !== resultado.total_estimado.maximo"> a {{ moneda(resultado.total_estimado.maximo, resultado.moneda) }}</template></strong>
                                <span v-if="equivalenteEnPesos(resultado.total_estimado.minimo)" class="simulador-equivalente">
                                    Equivalente orientativo: {{ equivalenteEnPesos(resultado.total_estimado.minimo) }}<template v-if="resultado.total_estimado.minimo !== resultado.total_estimado.maximo"> a {{ equivalenteEnPesos(resultado.total_estimado.maximo) }}</template>
                                </span>
                            </dd>
                        </div>
                    </dl>
                    <footer class="simulador-fuente" aria-label="Referencia de precios utilizada">
                        <div><span>Tarifario</span><strong>{{ resultado.version_tarifario?.nombre ?? 'Sin versión vigente' }}</strong></div>
                        <div><span>Moneda</span><strong>{{ resultado.moneda }}</strong></div>
                        <div v-if="resultado.ccl"><span>CCL aplicado</span><strong>{{ moneda(resultado.ccl.valor, 'ARS') }} por USD</strong></div>
                    </footer>
                </template>
                <p v-else-if="!error" class="simulador-vacio">Elegí las respuestas y calculá para ver el precio base, los adicionales y cualquier revisión pendiente.</p>
            </section>
        </div>
    </section>
</template>

<style scoped>
/* Las dos columnas pueden reducirse sin empujar el ancho de la ficha que las contiene. */
.simulador-estimacion { box-sizing:border-box; width:100%; max-width:100%; min-width:0; padding:clamp(18px, 2.5vw, 28px); }
.simulador-cabecera { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; min-width:0; margin-bottom:18px; }
.simulador-cabecera > div { min-width:0; }
.simulador-cabecera h2 { margin:0 0 6px; color:#153b64; font-size:1.3rem; }
.simulador-cabecera p { margin:0; color:#446075; }
.simulador-eyebrow { margin:0 0 6px !important; color:#1f6f7f !important; font-size:.74rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; }
.simulador-servicio { flex:0 1 auto; max-width:40%; padding:6px 10px; border-radius:999px; background:#f5f9fa; color:#446075; font-size:.8rem; overflow-wrap:anywhere; }
.simulador-columnas { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; align-items:start; min-width:0; }
.simulador-formulario,.simulador-resultado { box-sizing:border-box; width:100%; max-width:100%; min-width:0; align-self:start; padding:18px; border:1px solid #d3e3e8; border-radius:14px; background:#fff; }
.simulador-formulario > *,.simulador-resultado > * { min-width:0; }
.simulador-formulario h3,.simulador-resultado h3 { margin:0 0 14px; color:#153b64; font-size:1.06rem; }
.simulador-pregunta { display:grid; gap:7px; margin:0 0 16px; }
.simulador-pregunta :deep(.field),.simulador-pregunta :deep(.v-input),.simulador-pregunta :deep(.v-field) { min-width:0; max-width:100%; }
.simulador-etiqueta { color:#153b64; font-size:.9rem; font-weight:600; }
.simulador-etiqueta span { color:#1f6f7f; }
.simulador-opciones { margin:0; padding:10px 14px; border:1px solid #d3e3e8; border-radius:10px; }
.simulador-opcion { display:flex; align-items:center; gap:9px; min-height:32px; color:#153b64; }
.simulador-opcion input { width:16px; height:16px; accent-color:#1f6f7f; }
.simulador-acciones { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:10px; margin-top:20px; }
.simulador-boton { min-height:40px; padding:8px 16px; border:1px solid #153b64; border-radius:8px; font:inherit; font-size:.9rem; cursor:pointer; }
.simulador-principal { background:#153b64; color:#fff; font-weight:700; }
.simulador-principal:disabled { opacity:.6; cursor:wait; }
.simulador-secundario { background:#fff; color:#153b64; }
.simulador-vacio,.simulador-ayuda { margin:0; padding:12px 14px; border-left:3px solid #2db3a3; background:#f5f9fa; color:#446075; font-size:.88rem; }
.simulador-alerta { margin:0 0 12px; padding:10px 12px; border-radius:8px; font-size:.88rem; }
.simulador-estados { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:12px; }
.simulador-estado { display:inline-flex; align-items:center; min-height:26px; padding:3px 9px; border:1px solid transparent; border-radius:999px; font-size:.75rem; font-weight:700; }
.estado-listo { color:#176f53; background:#e8f5ef; border-color:#b8dfcf; }
.estado-pendiente { color:#8a5a00; background:#fff4df; border-color:#efd6a7; }
.estado-revision { color:#8a4f13; background:#fff1e6; border-color:#edc9aa; }
.estado-error { color:#9b3039; background:#fff0f0; border-color:#e7bfc2; }
.simulador-base,.simulador-totales { margin:12px 0; }
.simulador-base div,.simulador-totales div { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; min-width:0; padding:10px 0; border-bottom:1px solid #d3e3e8; }
.simulador-base dt,.simulador-totales dt { color:#446075; }
.simulador-base dt,.simulador-totales dt { min-width:0; }
.simulador-base dd,.simulador-totales dd { min-width:0; max-width:65%; margin:0; color:#153b64; font-weight:700; text-align:right; overflow-wrap:anywhere; }
.simulador-lista-adicionales h4 { margin:14px 0 8px; color:#153b64; font-size:.94rem; }
.simulador-linea { padding:10px 0; border-bottom:1px solid #d3e3e8; }
.simulador-linea-cabecera { display:flex; justify-content:space-between; gap:12px; color:#153b64; }
.simulador-linea p { margin:5px 0 0; color:#446075; font-size:.8rem; }
.simulador-total-final { margin-top:5px; padding-top:12px !important; border-top:2px solid #1f6f7f; }
.simulador-totales .simulador-total-final dd { display:grid; gap:4px; max-width:100%; color:#153b64; }
.simulador-total-final dd > strong { color:#153b64; font-family:'Space Grotesk',sans-serif; font-size:clamp(1.2rem,2vw,1.6rem); line-height:1.2; }
.simulador-equivalente { color:#446075; font-size:.82rem; font-weight:500; }
.simulador-fuente { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 16px; min-width:0; margin-top:14px; padding:12px; border:1px solid #d3e3e8; border-radius:10px; background:#f5f9fa; color:#446075; font-size:.78rem; }
.simulador-fuente > div { display:grid; gap:2px; min-width:0; }
.simulador-fuente > div:first-child { grid-column:1 / -1; }
.simulador-fuente span { color:#446075; }
.simulador-fuente strong { color:#153b64; font-weight:700; overflow-wrap:anywhere; }
.visually-hidden { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
@media (max-width:760px) { .simulador-columnas { grid-template-columns:minmax(0,1fr); } }
@media (max-width:560px) {
    .simulador-cabecera { flex-direction:column; gap:10px; }
    .simulador-servicio { max-width:100%; }
    .simulador-formulario,.simulador-resultado { padding:15px; }
    .simulador-linea-cabecera { flex-direction:column; }
    .simulador-base div,.simulador-totales div { flex-direction:column; gap:4px; }
    .simulador-base dd,.simulador-totales dd { max-width:100%; text-align:left; }
    .simulador-fuente { grid-template-columns:minmax(0,1fr); }
    .simulador-fuente > div:first-child { grid-column:auto; }
    .simulador-acciones { justify-content:stretch; }
    .simulador-acciones .simulador-boton { flex:1 1 auto; }
}
</style>
