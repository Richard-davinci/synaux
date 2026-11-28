<script setup>
import F from '../UI/FormField.vue';
import {money} from '../../support/formatters';

// La misma carga se usa al dar de alta el servicio y al editar sus precios desde la ficha.
defineProps({
    formulario: {type: Object, required: true},
    cotizacion: [String, Number],
    obligatorio: Boolean,
});

function elegirModalidad(fila, fija) {
    fila.fijo = fija;
    if (fija) fila.max = fila.min;
}

function equivalente(fila, cotizacion) {
    if (!Number(cotizacion) || fila.min === '' || fila.max === '') return '';
    const minimo = money(Number(fila.min) * Number(cotizacion), 'ARS');
    return Number(fila.min) === Number(fila.max) ? minimo : minimo + ' a ' + money(Number(fila.max) * Number(cotizacion), 'ARS');
}
</script>

<template>
    <div class="importes-servicio">
        <p v-if="formulario.errors.precios" class="notice" role="alert">{{ formulario.errors.precios }}</p>
        <p v-if="!formulario.precios.segmentos.length" class="notice">
            Activá la pregunta general «¿Para qué tipo de cliente?» y sus opciones para cargar los importes.
        </p>
        <template v-for="(filas, grupo) in formulario.precios" :key="grupo">
            <h3 v-if="grupo === 'conceptos' && filas.length">Adicionales por unidad</h3>
            <fieldset v-for="(fila, i) in filas" :key="fila.id" class="fila-importe">
                <legend>{{ fila.nombre }}</legend>
                <div class="controles-importe">
                    <div class="modalidad-importe" role="group" :aria-label="'Modalidad para ' + fila.nombre">
                        <button type="button" :aria-pressed="fila.fijo" @click="elegirModalidad(fila, true)">Fijo</button>
                        <button type="button" :aria-pressed="!fila.fijo" @click="elegirModalidad(fila, false)">Rango</button>
                    </div>
                    <F v-if="fila.fijo" :model-value="fila.min" label="Precio en USD" type="number" min="0" max="100000000"
                       step="0.01" :required="obligatorio" :error="formulario.errors[`precios.${grupo}.${i}.min`]"
                       @update:model-value="fila.min = fila.max = $event"/>
                    <div v-else class="rango-importe">
                        <F v-model="fila.min" label="Desde · USD" type="number" min="0" max="100000000" step="0.01"
                           :required="obligatorio" :error="formulario.errors[`precios.${grupo}.${i}.min`]"/>
                        <F v-model="fila.max" label="Hasta · USD" type="number" min="0" max="100000000" step="0.01"
                           :required="obligatorio" :error="formulario.errors[`precios.${grupo}.${i}.max`]"/>
                    </div>
                </div>
                <small v-if="equivalente(fila, cotizacion)">Equivalente en pesos · {{ equivalente(fila, cotizacion) }}</small>
            </fieldset>
        </template>
    </div>
</template>

<style scoped>
/* Los campos mantienen el mismo ancho y alineación tanto en la ficha como en el panel. */
.importes-servicio, .fila-importe { display: grid; gap: 8px; }
.importes-servicio h3 { margin: 12px 0 0; font-size: 1rem; }
.fila-importe { min-width: 0; margin: 0; padding: 8px 12px 12px; border: 1px solid #1f6f7f35; border-radius: 8px; }
.fila-importe legend { padding-inline: 5px; color: #153b64; font-weight: 700; }
.fila-importe small { color: #446075; }
.controles-importe { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: start; gap: 10px; }
.controles-importe :deep(.field) { margin: 0; min-width: 0; }
.rango-importe { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.modalidad-importe { display: flex; gap: 2px; padding: 2px; margin-top: 24px; border: 1px solid #1f6f7f35; border-radius: 7px; background: #f5f9fa; }
.modalidad-importe button { min-height: 32px; padding-inline: 8px; border: 0; border-radius: 5px; font: inherit; font-size: .8rem; color: #153b64; cursor: pointer; }
.modalidad-importe button[aria-pressed='true'] { background: #153b64; color: #fff; }
.modalidad-importe button:focus-visible { outline: 2px solid #1f6f7f; outline-offset: 2px; }
@media (max-width: 430px) {
    .controles-importe { grid-template-columns: minmax(0, 1fr); }
    .modalidad-importe { justify-self: start; margin-top: 0; }
}
</style>
