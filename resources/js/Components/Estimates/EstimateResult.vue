<script setup>
import { date, money } from '../../support/formatters';

defineProps({
    result: { type: Object, required: true },
    showDeliverables: Boolean,
    preview: Boolean,
});

function priceRange(min, max, currency) {
    return Number(min) === Number(max) ? money(min, currency) : money(min, currency) + ' – ' + money(max, currency);
}
</script>

<template>
    <!-- La vista toma importes, moneda y respuestas del resultado que recibe. -->
    <div class="result-grid">
        <v-card class="card result-card">
            <p class="eyebrow">{{ result.mode === 'manual' ? 'Revisión de alcance' : 'Tu estimación orientativa' }}</p>
            <h2>{{ result.mode === 'manual' ? 'Preparemos una propuesta para tu proyecto' : priceRange(result.min, result.max, result.currency) }}</h2>
            <p>{{ result.mode === 'manual' ? 'synaux necesita revisar el alcance para definir el importe y los plazos. Podés continuar con la solicitud.' : 'Calculada el ' + date(result.date) + '. El precio y las condiciones finales se confirman en el presupuesto formal.' }}</p>
            <div v-if="result.mode !== 'manual'" class="estimate-lines">
                <div>
                    <span>Base del servicio</span>
                    <strong>{{ priceRange(result.base_min, result.base_max, result.currency) }}</strong>
                </div>
                <div v-for="item in result.items" :key="item.name">
                    <span>{{ item.name }}<template v-if="item.unit !== 'segmento'"> · {{ item.quantity }}</template></span>
                    <strong>{{ priceRange(Number(item.min) * Number(item.quantity), Number(item.max) * Number(item.quantity), result.currency) }}</strong>
                </div>
            </div>
            <div class="button-stack">
                <slot />
            </div>
        </v-card>
        <aside class="result-aside">
            <v-card class="card"><h3>Alcance considerado</h3><p>{{ result.service?.name ?? result.summary }}</p>
                <ul v-if="showDeliverables"><li v-for="deliverable in result.service?.includes ?? []" :key="deliverable">{{ deliverable }}</li></ul>
                <dl class="data-list"><div v-for="answer in result.answers" :key="answer.id"><dt>{{ answer.question }}</dt><dd>{{ answer.label }}</dd></div></dl>
            </v-card>
            <v-card class="card soft-panel"><h3>El próximo paso</h3><p>Completá el pedido y tus datos de contacto. synaux revisará el alcance y te enviará un presupuesto formal.</p><p v-if="!$page.props.auth && !preview">Tus respuestas se conservan al ingresar o crear tu cuenta.</p></v-card>
        </aside>
    </div>
</template>

<style scoped>
.result-grid h2, .result-grid h3 { font-weight: 700; }
.result-aside ul { padding-left: 1.25rem; margin-bottom: 1rem; }
.result-aside p:last-child { margin-bottom: 0; }
.result-grid .result-card h2 { overflow-wrap: anywhere; }
.result-grid :deep(button:focus-visible) { outline: 3px solid #1f6f7f; outline-offset: 3px; }
</style>
