// Este grupo de opciones define el tipo de cliente y se presenta como selector segmentado.
export function esSelectorTipoCliente(question) {
    if (question?.service_id != null || question?.input_type !== 'single_choice') return false;
    const texto = String(question.question ?? '').trim().toLocaleLowerCase('es');
    const opciones = (question.options ?? []).map(option => String(option.label ?? '').trim().toLocaleLowerCase('es'));
    return texto === '¿para qué tipo de cliente?' &&
        ['particular', 'pyme', 'empresa'].every(label => opciones.includes(label));
}
