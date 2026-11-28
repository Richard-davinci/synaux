<script setup>
import {money} from '../../support/formatters';

defineProps({
    preview: {type: Object, default: null},
    loading: {type: Boolean, default: false},
    error: {type: String, default: ''},
    requested: {type: Boolean, default: false},
    emptyMessage: {type: String, default: 'Completá las preguntas para actualizar el importe.'},
});

const estados = {
    listo: 'Estimación completa',
    respuestas_pendientes: 'Faltan respuestas obligatorias',
    conceptos_pendientes_de_cotizacion: 'Hay conceptos pendientes de cotización',
    revision_profesional: 'Requiere revisión profesional',
    segmento_sin_precio: 'El precio base requiere cotización',
    error_configuracion: 'El servicio requiere revisión de configuración',
};

function importe(min, max, currency) {
    if (min == null || max == null) return 'A cotizar';
    const desde = money(Number(min), currency);
    return Number(min) === Number(max) ? desde : `${desde} – ${money(Number(max), currency)}`;
}
</script>

<template>
    <section class="estimate-live" aria-labelledby="estimate-live-title" :aria-busy="loading">
        <header class="estimate-live-heading">
            <div>
                <p class="estimate-live-eyebrow">Cálculo del servidor</p>
                <h3 id="estimate-live-title">Tu estimación</h3>
            </div>
            <span v-if="loading" class="estimate-live-loading" role="status">Actualizando…</span>
        </header>

        <p v-if="!requested && !loading" class="estimate-live-empty">{{ emptyMessage }}</p>
        <p v-else-if="loading && !preview" class="estimate-live-empty" role="status">Calculando con las respuestas actuales…</p>
        <p v-else-if="error" class="estimate-live-error" role="alert">{{ error }}</p>
        <template v-else-if="preview">
            <div class="estimate-live-state" :class="preview.completa ? 'is-complete' : 'is-pending'" aria-live="polite">
                {{ preview.completa ? estados.listo : (estados[preview.estado?.find(item => item !== 'listo')] || 'Importe orientativo') }}
            </div>

            <div class="estimate-live-base">
                <span>Precio base · {{ preview.principal?.segmento || 'tipo de cliente pendiente' }}</span>
                <strong>{{ importe(preview.base?.minimo, preview.base?.maximo, preview.moneda) }}</strong>
            </div>

            <div v-if="preview.adicionales?.length || preview.servicios_adicionales?.length" class="estimate-live-lines">
                <h4>Adicionales</h4>
                <div v-for="item in preview.adicionales" :key="'component-' + item.component_id" class="estimate-live-line">
                    <span>{{ item.name }}<small v-if="item.quantity !== 1">{{ item.quantity }} × {{ importe(item.min, item.max, preview.moneda) }}</small></span>
                    <strong>{{ importe(item.subtotal_minimo, item.subtotal_maximo, preview.moneda) }}</strong>
                </div>
                <div v-for="item in preview.servicios_adicionales" :key="'service-' + item.service_id" class="estimate-live-line">
                    <span>{{ item.name }}<small>{{ item.quantity }} × {{ importe(item.unit_minimo, item.unit_maximo, item.moneda) }}</small></span>
                    <strong>{{ importe(item.subtotal_minimo, item.subtotal_maximo, item.moneda) }}</strong>
                </div>
                <div class="estimate-live-extra-total">
                    <span>Total de adicionales conocidos</span>
                    <strong>{{ importe(preview.total_adicionales?.minimo, preview.total_adicionales?.maximo, preview.moneda) }}</strong>
                </div>
            </div>
            <div v-else class="estimate-live-no-extras">Todavía no seleccionaste adicionales.</div>

            <div class="estimate-live-total">
                <span>{{ preview.completa ? 'Total estimado' : 'Subtotal conocido' }}</span>
                <strong>{{ preview.completa
                    ? importe(preview.total_estimado?.minimo, preview.total_estimado?.maximo, preview.moneda)
                    : importe(preview.subtotal_conocido?.minimo, preview.subtotal_conocido?.maximo, preview.moneda) }}</strong>
            </div>
            <p v-if="!preview.completa" class="estimate-live-note">
                El importe mostrado es parcial y puede requerir una cotización profesional.
                <span v-if="preview.preguntas_pendientes?.length">Completá: {{ preview.preguntas_pendientes.join(', ') }}.</span>
            </p>
            <p v-if="preview.equivalente_ars?.total_minimo != null || preview.equivalente_ars?.subtotal_minimo != null" class="estimate-live-ars">
                Equivalente orientativo en ARS:
                {{ importe(preview.completa ? preview.equivalente_ars.total_minimo : preview.equivalente_ars.subtotal_minimo,
                    preview.completa ? preview.equivalente_ars.total_maximo : preview.equivalente_ars.subtotal_maximo, 'ARS') }}
                <span v-if="preview.ccl">· CCL {{ money(preview.ccl.valor, 'ARS') }} por USD</span>
            </p>
            <p v-if="preview.version_tarifario" class="estimate-live-tariff">
                {{ preview.version_tarifario.nombre }} · {{ preview.moneda }}
            </p>
        </template>
    </section>
</template>

<style scoped>
.estimate-live {
    min-width: 0;
    padding: 18px;
    border: 1px solid #1f6f7f33;
    border-radius: 12px;
    background: #fff;
    color: #153b64;
}
.estimate-live-heading, .estimate-live-base, .estimate-live-line, .estimate-live-extra-total, .estimate-live-total {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}
.estimate-live-heading { align-items: center; margin-bottom: 14px; }
.estimate-live-eyebrow { margin: 0 0 3px; color: #1f6f7f; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.estimate-live h3 { margin: 0; font-size: 1.2rem; }
.estimate-live h4 { margin: 14px 0 8px; font-size: .95rem; }
.estimate-live-loading { color: #446075; font-size: .82rem; }
.estimate-live-state { display: inline-flex; margin-bottom: 12px; padding: 5px 9px; border-radius: 999px; font-size: .78rem; font-weight: 700; }
.is-complete { color: #17694f; background: #eaf6f0; border: 1px solid #b7dfcc; }
.is-pending { color: #84520b; background: #fff5df; border: 1px solid #ead4a8; }
.estimate-live-base { padding: 10px 0; border-block: 1px solid #1f6f7f24; font-size: .9rem; }
.estimate-live-base strong, .estimate-live-line strong { flex: 0 0 auto; text-align: right; }
.estimate-live-lines { padding-bottom: 8px; border-bottom: 1px solid #1f6f7f24; }
.estimate-live-line { padding: 7px 0; font-size: .86rem; }
.estimate-live-line span { min-width: 0; overflow-wrap: anywhere; }
.estimate-live-line small { display: block; margin-top: 3px; color: #58748a; font-size: .78rem; }
.estimate-live-extra-total { padding-top: 9px; margin-top: 4px; border-top: 1px solid #1f6f7f20; color: #446075; font-size: .84rem; }
.estimate-live-no-extras, .estimate-live-empty { margin: 0; color: #58748a; font-size: .9rem; line-height: 1.55; }
.estimate-live-total { align-items: center; padding: 13px 0 3px; font-size: .92rem; }
.estimate-live-total strong { font-size: 1.25rem; text-align: right; }
.estimate-live-note, .estimate-live-ars, .estimate-live-tariff { margin: 8px 0 0; color: #58748a; font-size: .79rem; line-height: 1.5; }
.estimate-live-ars { font-weight: 600; }
.estimate-live-ars span { display: block; font-weight: 400; }
.estimate-live-tariff { border-top: 1px solid #1f6f7f20; padding-top: 9px; }
.estimate-live-error { margin: 0; padding: 10px 12px; color: #8e3039; background: #fff0f0; border-radius: 8px; font-size: .88rem; }
@media (max-width: 600px) {
    .estimate-live { padding: 15px; }
    .estimate-live-total strong { font-size: 1.1rem; }
}
</style>
