// La vista usa las mismas claves de pregunta y respuesta que valida el servidor.
export function preguntasVisibles(preguntas = [], respuestas = {}) {
    const ordenadas = [...preguntas].sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || Number(a.id) - Number(b.id));
    const porId = new Map(ordenadas.map(pregunta => [String(pregunta.id), pregunta]));
    const visibles = new Set();
    return ordenadas.filter(pregunta => {
        // El selector técnico del segmento de cliente nunca depende de otra respuesta.
        if (pregunta.clave_sistema === 'tipo_cliente' || pregunta.visibility_question_id == null) {
            visibles.add(String(pregunta.id));
            return true;
        }
        const origenId = String(pregunta.visibility_question_id);
        const origen = porId.get(origenId);
        const valor = respuestas[origenId] ?? respuestas[pregunta.visibility_question_id];
        if (!origen || !visibles.has(origenId) || valor === '' || valor == null || (Array.isArray(valor) && !valor.length)) return false;
        const seleccionadas = (Array.isArray(valor) ? valor : [valor]).map(String);
        const contiene = seleccionadas.includes(String(pregunta.visibility_option_id));
        const visible = pregunta.visibility_operator === 'equals' ? !Array.isArray(valor) && contiene
            : pregunta.visibility_operator === 'not_equals' ? !Array.isArray(valor) && !contiene
                : pregunta.visibility_operator === 'contains' ? Array.isArray(valor) && contiene
                    : pregunta.visibility_operator === 'not_contains' ? Array.isArray(valor) && !contiene : false;
        if (visible) visibles.add(String(pregunta.id));
        return visible;
    });
}
