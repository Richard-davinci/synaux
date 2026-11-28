// Este grupo de opciones define el tipo de cliente y se presenta como selector segmentado.
export function esSelectorTipoCliente(question) {
    return question?.clave_sistema === 'tipo_cliente';
}

// El precio de referencia se elige por la clave de la opción, aunque cambie su etiqueta.
export function claveTipoCliente(preguntas, leerRespuesta) {
    const sistema = preguntas.find(esSelectorTipoCliente);
    const candidatas = sistema ? [sistema, ...preguntas.filter(pregunta => pregunta !== sistema)] : preguntas;
    for (const pregunta of candidatas) {
        const id = leerRespuesta(pregunta);
        const opcion = pregunta.options?.find(opcion => String(opcion.id) === String(id));
        if (opcion?.clave_segmento) return opcion.clave_segmento;
    }
    return null;
}

export function referenciaPorTipoCliente(referencia, clave) {
    if (!referencia?.segments?.length || !clave) return referencia;
    const segmento = referencia.segments.find(opcion => opcion.key === clave);
    return segmento ? {...referencia, min: segmento.min, max: segmento.max, segment: segmento.label} : null;
}
