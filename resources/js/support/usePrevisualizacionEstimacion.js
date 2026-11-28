import {onBeforeUnmount, ref, watch} from 'vue';
import axios from 'axios';

/** Solicita una vista orientativa temporal; aborta la anterior y descarta respuestas que lleguen tarde. */
export function usePrevisualizacionEstimacion(payload, fingerprint, enabled = () => true, delay = 250) {
    const result = ref(null);
    const loading = ref(false);
    const error = ref('');
    const requested = ref(false);
    const refreshCount = ref(0);
    let timer = null;
    let controller = null;
    let sequence = 0;

    const stop = watch([fingerprint, enabled, refreshCount], ([, active]) => {
        clearTimeout(timer);
        controller?.abort();
        const current = ++sequence;
        error.value = '';
        if (!active || !payload.value?.service) {
            result.value = null;
            loading.value = false;
            return;
        }

        requested.value = true;
        result.value = null;
        loading.value = true;
        const body = JSON.parse(JSON.stringify(payload.value));
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const response = await axios.post('/estimador/previsualizar', body, {signal: controller.signal});
                if (current === sequence) result.value = response.data;
            } catch (cause) {
                if (current === sequence && cause.code !== 'ERR_CANCELED') {
                    error.value = cause.response?.data?.message || 'No pudimos actualizar la estimación. Revisá tu conexión e intentá de nuevo.';
                }
            } finally {
                if (current === sequence) loading.value = false;
            }
        }, delay);
    }, {immediate: true});

    onBeforeUnmount(() => {
        stop();
        clearTimeout(timer);
        controller?.abort();
        sequence++;
    });

    return {result, loading, error, requested, refresh: () => refreshCount.value++};
}
