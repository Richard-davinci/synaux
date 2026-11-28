import {computed} from 'vue';
import {referenceLine, summarizePrices} from '../../support/calculoPreciosSolicitud';
import {claveTipoCliente, esSelectorTipoCliente, referenciaPorTipoCliente} from '../../support/visualizacionPregunta';
import {usePrevisualizacionEstimacion} from '../../support/usePrevisualizacionEstimacion';

// Los textos informativos solo actualizan el cálculo cuando pasan de vacíos a completos.
function economicAnswers(questions, answers) {
    return questions.map(question => {
        const value = answers?.[question.id];
        if (question.input_type === 'text') return [question.id, Boolean(String(value ?? '').trim())];
        return [question.id, Array.isArray(value) ? [...value].map(String).sort() : value ?? null];
    });
}

// Contrasta todas las preguntas activas para invalidar referencias históricas solo cuando se modifican sus respuestas.
function sameAnswers(questionsForService, snapshot, readValue) {
    const normalize = value => Array.isArray(value) ? value.map(String).sort() : String(value ?? '').trim();
    const saved = new Map(snapshot.map(answer => [String(answer.id), answer.value]));
    return questionsForService.every(question => {
        const empty = question.input_type === 'multiple_choice' ? [] : '';
        return JSON.stringify(normalize(readValue(question) ?? empty)) === JSON.stringify(normalize(saved.get(String(question.id)) ?? empty));
    });
}

// Usa el motor del servidor para las solicitudes nuevas. Los documentos existentes conservan su snapshot guardado.
export function useRequestPricing(data, form, {estimate, old, selectedService, questions, extraQuestions}) {
    const tipoPrincipal = computed(() => estimate ? data.value.requestEstimateClientType
        : claveTipoCliente(questions.value, pregunta => form.answers[pregunta.id]));
    const enabled = computed(() => !estimate && !old && Boolean(selectedService.value));
    const payload = computed(() => ({
        service: selectedService.value?.id ?? '',
        answers: {...form.answers},
        additional_services: form.additional_services.filter(item => item.service_id).map(item => ({
            service_id: item.service_id,
            quantity: item.quantity,
            answers: {...item.answers},
        })),
    }));
    const fingerprint = computed(() => JSON.stringify({
        service: payload.value.service,
        answers: economicAnswers(questions.value, form.answers),
        additional: payload.value.additional_services.map(item => ({
            service_id: item.service_id,
            quantity: item.quantity,
            answers: economicAnswers(extraQuestions(item.service_id), item.answers),
        })),
    }));
    const live = usePrevisualizacionEstimacion(payload, fingerprint, enabled);
    const matchingCalculation = computed(() => {
        const calculation = data.value.requestCalculation;
        if (!calculation || calculation.service?.database_id != form.service_id) return null;
        const questionsForService = data.value.requestQuestions?.[form.service_id] ?? [];
        const matches = sameAnswers(questionsForService, calculation.answers, question =>
            question.from_project_description ? form.description : form.answers[question.id]);
        return matches ? calculation : null;
    });
    const reference = computed(() => {
        if (live.requested.value && enabled.value) {
            const principal = live.result.value?.principal;
            const min = principal?.total_estimado?.minimo ?? principal?.subtotal_minimo;
            const max = principal?.total_estimado?.maximo ?? principal?.subtotal_maximo;
            return min == null ? null : {
                min, max: max ?? min, currency: live.result.value.moneda,
                partial: !principal.completa, segment: principal.segmento,
            };
        }
        if (estimate) return estimate.price_min == null ? estimate.pricing_partial ?? null : {
            min: estimate.price_min, max: estimate.price_max ?? estimate.price_min,
            currency: estimate.currency_snapshot, date: estimate.created_at,
        };
        const calculation = matchingCalculation.value;
        if (calculation) return calculation.min == null ? (calculation.subtotal_min == null ? null : {
            min: calculation.subtotal_min, max: calculation.subtotal_max ?? calculation.subtotal_min,
            currency: calculation.currency, partial: true,
        }) : {
            min: calculation.min, max: calculation.max, currency: calculation.currency, date: calculation.date,
        };
        const published = data.value.references?.[selectedService.value?.id];
        const tipoLocal = published?.segmentos_heredados
            ? claveTipoCliente(questions.value.filter(pregunta => !esSelectorTipoCliente(pregunta)), pregunta => form.answers[pregunta.id]) : null;
        return referenciaPorTipoCliente(published, tipoLocal ?? tipoPrincipal.value);
    });
    const preliminaryReference = computed(() => Boolean(reference.value && !estimate && !matchingCalculation.value && !live.result.value));
    const summaryCurrency = computed(() => live.result.value?.moneda ?? reference.value?.currency ?? estimate?.currency_snapshot ?? matchingCalculation.value?.currency ?? 'ARS');
    const adicionalesRevisados = computed(() => data.value.requestAdditionalPricing ?? []);
    const extraSnapshots = computed(() => adicionalesRevisados.value.length
        ? adicionalesRevisados.value : old?.additional_services ?? []);

    function matchingExtra(item) {
        const previous = extraSnapshots.value.find(snapshot => snapshot.service_id == item.service_id && Array.isArray(snapshot.answers));
        return previous && (!Object.hasOwn(previous, 'segmento_cliente') || previous.segmento_cliente === (tipoPrincipal.value ?? null))
            && sameAnswers(extraQuestions(item.service_id), previous.answers, question => item.answers[question.id]) ? previous : null;
    }

    const additionalPrices = computed(() => form.additional_services.map(item => {
        if (live.requested.value && enabled.value) {
            const row = live.result.value?.servicios_adicionales?.find(extra => extra.service_id == item.service_id);
            if (!row) return {priced: false};
            const known = row.subtotal_minimo != null && row.subtotal_maximo != null;
            const unit = row.unit_minimo != null && row.unit_maximo != null
                ? {min: row.unit_minimo, max: row.unit_maximo, currency: row.moneda, partial: row.estado !== 'listo'} : null;
            return unit ? referenceLine(unit, item.quantity, null, summaryCurrency.value)
                : {priced: false, hasKnownAmount: known, partial: true, quantity: item.quantity,
                    min: row.subtotal_minimo, max: row.subtotal_maximo, currency: row.moneda};
        }
        const verified = matchingExtra(item);
        if (verified && (verified.pricing_reference || verified.pricing_partial || adicionalesRevisados.value.length)) {
            return referenceLine(verified.pricing_reference ?? verified.pricing_partial, item.quantity, null, summaryCurrency.value);
        }
        const service = data.value.services.find(service => service.database_id == item.service_id);
        const published = data.value.references?.[service?.id];
        const preguntas = extraQuestions(item.service_id);
        const tipoLocal = published?.segmentos_heredados ? claveTipoCliente(preguntas, pregunta => item.answers[pregunta.id]) : null;
        const tipo = tipoLocal ?? tipoPrincipal.value;
        const referencia = published?.segments?.length && !tipo ? null : referenciaPorTipoCliente(published, tipo);
        const linea = referenceLine(referencia, item.quantity, null, summaryCurrency.value);
        return {...linea, preliminar: linea.priced && preguntas.length > 0};
    }));
    const pendingExtraCalculation = computed(() => !live.requested.value && additionalPrices.value.some(linea => linea.preliminar));
    const summaryPrice = computed(() => {
        if (live.requested.value) {
            const subtotal = live.result.value?.subtotal_conocido;
            return {
                currency: summaryCurrency.value, hasPrice: Boolean(subtotal),
                pending: live.result.value?.completa ? 0 : 1,
                min: subtotal?.minimo ?? 0, max: subtotal?.maximo ?? 0,
            };
        }
        return summarizePrices([
            referenceLine(reference.value, 1, null, summaryCurrency.value),
            ...additionalPrices.value.filter((_, indice) => form.additional_services[indice].service_id),
        ], summaryCurrency.value);
    });

    return {
        reference, preliminaryReference, summaryCurrency, pendingExtraCalculation, additionalPrices, summaryPrice,
        livePreview: live.result, previewLoading: live.loading, previewError: live.error, previewRequested: live.requested,
        refresh: live.refresh,
    };
}
