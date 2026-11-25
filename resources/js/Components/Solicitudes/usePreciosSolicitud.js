import {computed} from 'vue';
import {referenceLine, summarizePrices} from '../../support/calculoPreciosSolicitud';

// Contrasta todas las preguntas activas para invalidar la referencia al cambiar cualquier respuesta.
function sameAnswers(questionsForService, snapshot, readValue) {
    const normalize = value => Array.isArray(value) ? value.map(String).sort() : String(value ?? '').trim();
    const saved = new Map(snapshot.map(answer => [String(answer.id), answer.value]));
    return questionsForService.every(question => {
        const empty = question.input_type === 'multiple_choice' ? [] : '';
        return JSON.stringify(normalize(readValue(question) ?? empty)) === JSON.stringify(normalize(saved.get(String(question.id)) ?? empty));
    });
}

// Encuentra el segmento de la tarifa usando las opciones del propio servicio.
function segmentoElegido(referencia, preguntas, respuestas) {
    if (!referencia?.segments?.length) return null;

    for (const pregunta of preguntas) {
        const opcion = pregunta.options?.find(item => String(item.id) === String(respuestas[pregunta.id]));
        const segmento = referencia.segments.find(item => item.label === opcion?.label);
        if (segmento) return segmento;
    }
    return null;
}

// Usa las instantáneas del servidor cuando coinciden las respuestas y las referencias publicadas al editar.
export function useRequestPricing(data, form, {estimate, old, selectedService, questions, extraQuestions}) {
    const matchingCalculation = computed(() => {
        const calculation = data.value.requestCalculation;
        if (!calculation || calculation.service?.database_id != form.service_id) return null;

        const questionsForService = data.value.requestQuestions?.[form.service_id] ?? [];
        const matches = sameAnswers(questionsForService, calculation.answers, question =>
            question.from_project_description ? form.description : form.answers[question.id]);
        return matches ? calculation : null;
    });
    const reference = computed(() => {
        if (estimate) return estimate.price_min == null ? null : {
            min: estimate.price_min, max: estimate.price_max ?? estimate.price_min,
            currency: estimate.currency_snapshot, date: estimate.created_at,
        };
        const calculation = matchingCalculation.value;
        if (calculation) return calculation.min == null ? null : {
            min: calculation.min, max: calculation.max, currency: calculation.currency, date: calculation.date,
        };

        const published = data.value.references?.[selectedService.value?.id];
        const segment = segmentoElegido(published, questions.value, form.answers);
        return segment ? {...published, min: segment.min, max: segment.max, segment: segment.label} : published;
    });
    const preliminaryReference = computed(() => Boolean(reference.value && !estimate && !matchingCalculation.value));
    // Cada adicional tiene su propia referencia confirmada, en la misma moneda que el resumen.
    const summaryCurrency = computed(() => reference.value?.currency ?? estimate?.currency_snapshot ?? matchingCalculation.value?.currency ?? 'ARS');
    const adicionalesRevisados = computed(() => data.value.requestAdditionalPricing ?? []);
    const extraSnapshots = computed(() => adicionalesRevisados.value.length
        ? adicionalesRevisados.value : old?.additional_services ?? []);

    function matchingExtra(item) {
        const previous = extraSnapshots.value.find(snapshot => snapshot.service_id == item.service_id && Array.isArray(snapshot.answers));
        return previous && sameAnswers(extraQuestions(item.service_id), previous.answers, question => item.answers[question.id]) ? previous : null;
    }

    const additionalPrices = computed(() => form.additional_services.map(item => {
        const verified = matchingExtra(item);
        if (verified && (verified.pricing_reference || adicionalesRevisados.value.length)) {
            return referenceLine(verified.pricing_reference, item.quantity, null, summaryCurrency.value);
        }

        // Un borrador incompleto puede conservar una referencia orientativa hasta el cálculo del servidor.
        const service = data.value.services.find(service => service.database_id == item.service_id);
        const published = data.value.references?.[service?.id];
        const preguntas = extraQuestions(item.service_id);
        const segmento = segmentoElegido(published, preguntas, item.answers);
        const linea = referenceLine(published, item.quantity, segmento?.label, summaryCurrency.value);

        return {...linea, preliminar: linea.priced && preguntas.length > 0};
    }));
    const pendingExtraCalculation = computed(() => additionalPrices.value.some(linea => linea.preliminar));
    // Una fila vacía no cuenta como servicio pendiente ni altera el total.
    const summaryPrice = computed(() => summarizePrices([
        referenceLine(reference.value, 1, null, summaryCurrency.value),
        ...additionalPrices.value.filter((_, indice) => form.additional_services[indice].service_id),
    ], summaryCurrency.value));

    return {reference, preliminaryReference, summaryCurrency, pendingExtraCalculation, additionalPrices, summaryPrice};
}
