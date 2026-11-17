// Calcula importes orientativos en centavos; una tarifa ausente o una cantidad inválida nunca equivale a cero.
export function referenceLine(reference, quantity = 1, segment = null, currency = null) {
    const count = Number(quantity);
    const selected = reference?.segments?.length && segment
        ? reference.segments.find(item => item.label === segment) : reference;
    const min = selected?.min;
    const max = selected?.max ?? min;
    const code = reference?.currency || 'ARS';
    if (min == null || max == null || !Number.isFinite(Number(min)) || !Number.isFinite(Number(max))
        || Number(min) < 0 || Number(max) < Number(min) || !Number.isInteger(count) || count < 1 || count > 1000
        || (currency && code !== currency)) return { priced: false, quantity: count };
    return { priced: true, quantity: count, currency: code,
        unitMin: Number(min), unitMax: Number(max), segment: selected?.label ?? reference?.segment,
        min: Math.round(Number(min) * 100) * count / 100, max: Math.round(Number(max) * 100) * count / 100 };
}

// Suma solo los renglones cotizados en una misma moneda y distingue un subtotal de un total completo.
export function summarizePrices(lines, currency = 'ARS') {
    const priced = lines.filter(line => line.priced && line.currency === currency);
    return { currency, hasPrice: priced.length > 0, pending: lines.length - priced.length,
        min: priced.reduce((sum, line) => sum + Math.round(line.min * 100), 0) / 100,
        max: priced.reduce((sum, line) => sum + Math.round(line.max * 100), 0) / 100 };
}
