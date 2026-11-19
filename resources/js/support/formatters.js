// Los importes usan la moneda del registro y conservan hasta dos decimales.
export function money(value, currency = 'ARS') {
    if (value == null) return 'A definir con el profesional';

    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: currency || 'ARS',
        minimumFractionDigits: Number.isInteger(Number(value)) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

// Las fechas sin hora se interpretan al mediodía para conservar el día en Buenos Aires.
export function date(value) {
    if (!value) return 'A coordinar';

    const timestamp = value.length === 10 ? `${value}T12:00:00` : value.replace(' ', 'T');

    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'medium',
        timeZone: 'America/Argentina/Buenos_Aires',
    }).format(new Date(timestamp));
}

// Los avatares se forman con los dos primeros nombres disponibles.
export function initials(value) {
    return (value ?? 'synaux').split(' ').slice(0, 2).map(word => word[0]).join('').toUpperCase();
}
