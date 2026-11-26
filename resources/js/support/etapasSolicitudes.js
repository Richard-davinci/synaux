// Resume el estado de una solicitud según la etapa de revisión y la versión más reciente de su presupuesto.
export function etapaSolicitud(solicitud, presupuestos = []) {
    const presupuesto = presupuestos
        .filter(version => Number(version.request_id) === Number(solicitud.id))
        .sort((a, b) => Number(b.version_number) - Number(a.version_number) || Number(b.id) - Number(a.id))[0] ?? null;

    // El estado propio de la solicitud prevalece sobre el historial de presupuestos asociado.
    if (solicitud.status === 'closed' || solicitud.status === 'cancelled') {
        return {presupuesto, estadoVisual: solicitud.status, filtro: solicitud.status,
            etiqueta: solicitud.status === 'closed' ? 'Cerrada' : 'Cancelada'};
    }

    if (presupuesto) {
        const estado = presupuesto.display_status ?? presupuesto.status;
        // La lista de solicitudes indica si hay un borrador o un presupuesto vinculado;
        // el estado comercial detallado se consulta en Presupuestos.
        if (estado === 'draft') return {presupuesto, estado, estadoVisual: 'review',
            filtro: 'presupuesto_en_preparacion', etiqueta: 'Presupuesto en preparación'};

        return {presupuesto, estado, estadoVisual: 'pending', filtro: 'con_presupuesto',
            etiqueta: 'Con presupuesto asociado'};
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
