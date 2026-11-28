// Calcula importes orientativos en centavos; una tarifa ausente o una cantidad inválida nunca equivale a cero.
export function referenceLine(reference, quantity = 1, segment = null, currency = null) {
    const count = Number(quantity);
    const selected = reference?.segments?.length && segment
        ? reference.segments.find(item => item.key === segment) : reference;
    const min = selected?.min;
    const max = selected?.max ?? min;
    const code = reference?.currency || 'ARS';
    if (reference?.partial === true && min != null && max != null && Number.isFinite(Number(min)) && Number.isFinite(Number(max))) {
        if (!Number.isInteger(count) || count < 1 || count > 1000 || (currency && reference.currency !== currency)) {
            return {priced: false, quantity: count};
        }
        return {priced: false, hasKnownAmount: true, partial: true, quantity: count, currency: reference.currency,
            min: Math.round(Number(min) * 100) * count / 100, max: Math.round(Number(max) * 100) * count / 100};
    }
    if (min == null || max == null || !Number.isFinite(Number(min)) || !Number.isFinite(Number(max))
        || Number(min) < 0 || Number(max) < Number(min) || !Number.isInteger(count) || count < 1 || count > 1000
        || (currency && code !== currency)) return {priced: false, quantity: count};
    return {
        priced: true, quantity: count, currency: code,
        unitMin: Number(min), unitMax: Number(max), segment: selected?.label ?? reference?.segment,
        min: Math.round(Number(min) * 100) * count / 100, max: Math.round(Number(max) * 100) * count / 100
    };
}

// Suma solo los renglones cotizados en una misma moneda y distingue un subtotal de un total completo.
export function summarizePrices(lines, currency = 'ARS') {
    const priced = lines.filter(line => (line.priced || line.hasKnownAmount) && line.currency === currency);
    const complete = lines.filter(line => line.priced && line.currency === currency);
    return {
        currency, hasPrice: priced.length > 0, pending: lines.length - complete.length,
        min: priced.reduce((sum, line) => sum + Math.round(line.min * 100), 0) / 100,
        max: priced.reduce((sum, line) => sum + Math.round(line.max * 100), 0) / 100
    };
}


// Desglosa únicamente instantáneas coherentes y mantiene la referencia agrupada si no se puede conservar el importe.
export function requestBudgetLines({name, description, quantity, reference, breakdown}) {
    const title = name || 'Servicio';
    const text = (title + (description ? '\n' + description : '')).slice(0, 2000);
    const fallback = [{description: text, quantity, reference}];
    if (!reference || !breakdown?.items?.length || breakdown.base_min == null || breakdown.base_max == null) return fallback;

    const currency = reference.currency;
    const currencyReference = (min, max) => ({min, max, currency});
    const base = currencyReference(Number(breakdown.base_min), Number(breakdown.base_max));
    const lines = [{description: text, quantity, reference: base}];
    const cents = (price, count) => Math.round(Number(price) * count * 100);
    for (const item of breakdown.items) {
        const count = Number(item.quantity);
        if (item.min == null || item.max == null || !Number.isFinite(count) || count <= 0) return fallback;
        // El ajuste por tipo de cliente forma parte del precio base comercial.
        if (item.slug === 'segmento-tarifario-2026-11' || (!item.slug && item.unit === 'segmento' && item.name === 'Adecuación por tipo de cliente')) {
            base.min = (cents(base.min, 1) + cents(item.min, count)) / 100;
            base.max = (cents(base.max, 1) + cents(item.max, count)) / 100;
            continue;
        }
        const totalQuantity = Math.round(count * quantity * 1000) / 1000;
        lines.push({
            description: ((item.name || 'Adicional') + '\n' + 'Concepto de ' + title).slice(0, 2000),
            quantity: totalQuantity,
            reference: currencyReference(Number(item.min), Number(item.max)),
        });
    }
    if (lines.some(line => line.quantity < 0.001 || line.quantity > 10000
        || !['min', 'max'].every(key => Number.isFinite(line.reference[key]) && line.reference[key] >= 0 && line.reference[key] <= 100000000))) return fallback;
    return ['min', 'max'].every(key => lines.reduce((sum, line) => sum + cents(line.reference[key], line.quantity), 0) === cents(reference[key], quantity))
        ? lines : fallback;
}
