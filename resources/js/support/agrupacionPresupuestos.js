// En los listados de presupuestos, las versiones de un mismo pedido forman una entrada, incluso si tienen distintos números de presupuesto.
export function presupuestosSinProyecto(presupuestos) {
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
        const [principal, ...historial] = versiones;
        // La fila muestra la versión principal. El historial conserva las demás.
        return {...principal, claveGrupo, historial};
    }).filter(grupo => {
        // El proyecto conserva toda la historia de una solicitud con una versión aceptada.
        return grupo.status !== 'accepted' && !grupo.historial.some(version => version.status === 'accepted');
    }).sort(porFecha);
}
