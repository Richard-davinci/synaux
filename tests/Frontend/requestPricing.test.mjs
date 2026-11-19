import test from 'node:test';
import assert from 'node:assert/strict';
import { referenceLine, requestBudgetLines, summarizePrices } from '../../resources/js/support/requestPricing.js';

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


// El desglose de una solicitud conserva cantidades, tramos y el total del servicio.
test('separa hosting del precio base y multiplica cada concepto por la cantidad solicitada', () => {
    const lines = requestBudgetLines({
        name: 'Sitio web', quantity: 2, reference: { min: 740000, max: 760000, currency: 'ARS' },
        breakdown: { base_min: 600000, base_max: 600000, items: [
            { slug: 'segmento-tarifario-2026-11', name: 'Adecuación', quantity: 1, min: 80000, max: 80000 },
            { slug: 'hosting', name: 'Hosting anual', quantity: 1, min: 60000, max: 80000 },
        ] },
    });
    assert.equal(lines.length, 2);
    assert.deepEqual(lines.map(line => [line.description, line.quantity, line.reference.min, line.reference.max]), [
        ['Sitio web', 2, 680000, 680000],
        ['Hosting anual\nConcepto de Sitio web', 2, 60000, 80000],
    ]);
});

test('mantiene agrupada una referencia histórica que perdería centavos al desglosarse', () => {
    const reference = { min: 100.01, max: 100.01, currency: 'ARS' };
    const lines = requestBudgetLines({
        name: 'Sitio web', quantity: 2, reference,
        breakdown: { base_min: 100, base_max: 100, items: [
            { name: 'Extra', quantity: 0.5, min: 0.01, max: 0.01 },
        ] },
    });
    assert.deepEqual(lines, [{ description: 'Sitio web', quantity: 2, reference }]);
});
