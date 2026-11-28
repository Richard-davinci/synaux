<script setup>
import {computed} from 'vue';
import {date, money} from '../../support/formatters';
import FuenteCotizacionCcl from '../Catalog/FuenteCotizacionCcl.vue';

const props = defineProps({
    result: {type: Object, required: true},
    showDeliverables: Boolean,
    preview: Boolean,
});

function priceRange(min, max, currency) {
    return Number(min) === Number(max) ? money(min, currency) : money(min, currency) + ' – ' + money(max, currency);
}

// El equivalente del resultado usa la instantánea del cálculo, incluso al abrirlo más adelante.
const equivalenteArs = computed(() => {
    const {mode, currency, ccl_rate, min, max} = props.result;
    if (mode === 'manual' || currency !== 'USD' || !Number(ccl_rate) || min == null || max == null) return '';
    return priceRange(Number(min) * Number(ccl_rate), Number(max) * Number(ccl_rate), 'ARS');
});
const parcial = computed(() => props.result.pricing_partial ?? null);
const importeParcial = computed(() => parcial.value
    ? priceRange(parcial.value.min, parcial.value.max, parcial.value.currency) : '');
</script>

<template>
    <!-- La vista toma importes, moneda y respuestas del resultado que recibe. -->
    <div class="result-grid">
        <v-card class="card result-card">
            <p class="eyebrow">{{ result.mode === 'manual' ? 'Revisión de alcance' : 'Tu estimación orientativa' }}</p>
            <h2>{{ result.mode === 'manual' ? (importeParcial ? 'Subtotal conocido: ' + importeParcial : 'Preparemos una propuesta para tu proyecto') : priceRange(result.min, result.max, result.currency) }}</h2>
            <p v-if="equivalenteArs" class="estimacion-equivalente">
                Equivalente en pesos: {{ equivalenteArs }}
                <FuenteCotizacionCcl :fuente="result.ccl_source"/>
            </p>
            <p>{{ result.mode === 'manual' ? (importeParcial ? 'Este subtotal incluye los importes disponibles. Algunos conceptos requieren revisión profesional; el subtotal no es un total definitivo.' : 'synaux necesita revisar el alcance para definir el importe y los plazos. Podés continuar con la solicitud.') : 'Calculada el ' + date(result.date) + '. El precio y las condiciones finales se confirman en el presupuesto formal.' }}</p>
            <div v-if="result.mode !== 'manual' || importeParcial" class="estimate-lines">
                <div>
                    <span>Base del servicio</span>
                    <strong>{{ result.base_min == null ? 'A cotizar' : priceRange(result.base_min, result.base_max, result.currency) }}</strong>
                </div>
                <div v-for="item in result.items" :key="item.name">
                    <span>{{ item.name }}<template v-if="item.unit !== 'segmento'"> · {{
                            item.quantity
                        }}</template></span>
                    <strong>{{ item.min == null || item.max == null ? 'A cotizar' : priceRange(Number(item.min) * Number(item.quantity), Number(item.max) * Number(item.quantity), result.currency) }}</strong>
                </div>
            </div>
            <div class="button-stack">
                <slot/>
            </div>
        </v-card>
        <aside class="result-aside">
            <v-card class="card"><h3>Alcance considerado</h3>
                <p>{{ result.service?.name ?? result.summary }}</p>
                <ul v-if="showDeliverables">
                    <li v-for="deliverable in result.service?.includes ?? []" :key="deliverable">{{ deliverable }}</li>
                </ul>
                <dl class="data-list">
                    <div v-for="answer in result.answers" :key="answer.id">
                        <dt>{{ answer.question }}</dt>
                        <dd>{{ answer.label }}</dd>
                    </div>
                </dl>
            </v-card>
            <v-card class="card soft-panel"><h3>El próximo paso</h3>
                <p>Completá el pedido y tus datos de contacto. synaux revisará el alcance y te enviará un presupuesto
                    formal.</p>
                <p v-if="!$page.props.auth && !preview">Tus respuestas se conservan al ingresar o crear tu cuenta.</p>
            </v-card>
        </aside>
    </div>
</template>

<style scoped>
.result-grid h2, .result-grid h3 {
    font-weight: 700;
}

.result-aside ul {
    padding-left: 1.25rem;
    margin-bottom: 1rem;
}

.result-aside p:last-child {
    margin-bottom: 0;
}

.result-grid .result-card h2 {
    overflow-wrap: anywhere;
}

.estimacion-equivalente { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: baseline; color: #446075; }

.result-grid :deep(button:focus-visible) {
    outline: 3px solid #1f6f7f;
    outline-offset: 3px;
}
</style>
