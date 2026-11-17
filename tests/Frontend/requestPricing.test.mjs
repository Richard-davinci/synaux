import test from 'node:test';
import assert from 'node:assert/strict';
import { referenceLine, summarizePrices } from '../../resources/js/support/requestPricing.js';

// Las referencias reales de ambos paquetes deben compartir segmento y multiplicarse por cantidad.
test('suma principal y adicionales del mismo segmento y se actualiza al quitar o cambiar cantidades', () => {
    const landing = {currency:'ARS', min:254580, max:254580, segments:[
        {label:'Particular',min:254580,max:254580}, {label:'PyME',min:509160,max:509160}, {label:'Empresa',min:763740,max:763740},
    ]};
    const main = referenceLine({currency:'ARS',min:1352720,max:1352720});
    const total = quantity => summarizePrices([main, referenceLine(landing, quantity, 'PyME', 'ARS')]);
    assert.equal(total(1).min, 1861880);
    assert.equal(total(2).min, 2371040);
    assert.equal(total(2).pending, 0);
    assert.equal(summarizePrices([main]).min, 1352720);
    assert.equal(referenceLine(landing, 1, 'Empresa').min, 763740);
});

// No presenta importes desconocidos, monedas incompatibles o cantidades vacías como servicios gratuitos.
test('separa lo pendiente de cotizar y rechaza cantidades o monedas incompatibles', () => {
    const priced = referenceLine({currency:'ARS', min:100, max:200}, 2);
    for (const quantity of ['', 0, -1, 1.5, 1001, 'abc']) {
        assert.equal(referenceLine({min:1,max:1}, quantity).priced, false);
    }
    const unknown = referenceLine(null);
    const foreign = referenceLine({currency:'USD',min:300,max:300}, 1, null, 'ARS');
    const total = summarizePrices([priced, unknown, foreign]);
    assert.deepEqual(total, {currency:'ARS',hasPrice:true,pending:2,min:200,max:400});
    assert.equal(summarizePrices([unknown]).hasPrice, false);
    assert.equal(referenceLine({min:0,max:0}).priced, true);
    assert.equal(referenceLine({currency:'ARS',segments:[{label:'Particular',min:1,max:1}]}, 1, 'Empresa').priced, false);
});

// Conserva los centavos y los rangos sin acumular errores de punto flotante.
test('suma rangos y centavos con precisión monetaria', () => {
    const total = summarizePrices([referenceLine({min:0.1,max:0.2},3),referenceLine({min:0.2,max:0.3})]);
    assert.equal(total.min, 0.5);
    assert.equal(total.max, 0.9);
});
