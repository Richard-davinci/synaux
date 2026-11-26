// Resume el estado de una solicitud según la etapa de revisión y la versión más reciente de su presupuesto.
export function etapaSolicitud(solicitud, presupuestos = []) {
    const presupuesto = presupuestos
        .filter(version => Number(version.request_id) === Number(solicitud.id))
        .sort((a, b) => Number(b.version_number) - Number(a.version_number) || Number(b.id) - Number(a.id))[0] ?? null;

    if (presupuesto) {
        const estado = presupuesto.display_status ?? presupuesto.status;
        const etiquetas = {
            draft: 'Presupuesto en preparación',
            sent: 'Presupuesto enviado',
            issued: 'Presupuesto emitido',
            accepted: 'Presupuesto aceptado',
            rejected: 'Presupuesto rechazado',
            expired: 'Presupuesto vencido',
        };

        return {presupuesto, estado, estadoVisual: estado === 'draft' ? 'review' : estado,
            filtro: estado, etiqueta: etiquetas[estado] ?? estado};
    }

    if (solicitud.status === 'open' && solicitud.reviewed_at) {
        return {presupuesto: null, estado: 'pending', estadoVisual: 'pending',
            filtro: 'pendiente_presupuesto', etiqueta: 'Pendiente de presupuesto'};
    }
    if (solicitud.status === 'open') {
        return {presupuesto: null, estado: 'pending', estadoVisual: 'pending',
            filtro: 'pendiente_revision', etiqueta: 'Pendiente de revisión'};
    }

    return {presupuesto: null, estado: solicitud.status, estadoVisual: solicitud.status,
        filtro: solicitud.status, etiqueta: undefined};
}
