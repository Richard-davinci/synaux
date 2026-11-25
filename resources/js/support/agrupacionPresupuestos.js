// En el área cliente, las versiones de un mismo pedido forman una entrada, incluso si tienen distintos números de presupuesto.
export function agruparPresupuestos(presupuestos) {
    const grupos = new Map();
    for (const version of presupuestos) {
        const clave = version.request_id == null
            ? 'presupuesto-' + (version.budget_id ?? version.id)
            : 'solicitud-' + version.request_id;
        if (!grupos.has(clave)) grupos.set(clave, []);
        grupos.get(clave).push(version);
    }

    const porFecha = (a, b) => (b.sent_at ?? b.created_at ?? '').localeCompare(a.sent_at ?? a.created_at ?? '')
        || Number(b.id) - Number(a.id);

    return Array.from(grupos, ([claveGrupo, versiones]) => {
        versiones.sort(porFecha);
        // El acuerdo aceptado identifica al proyecto aunque existan otras propuestas en el historial.
        const principal = versiones.find(version => version.status === 'accepted') ?? versiones[0];
        // La fila muestra la versión principal. El historial conserva las demás.
        return {...principal, claveGrupo, historial: versiones.filter(version => version !== principal)};
    }).sort(porFecha);
}
