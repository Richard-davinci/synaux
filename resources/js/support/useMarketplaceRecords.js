import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Las listas distinguen registros recibidos y enviados dentro de las colecciones autorizadas por Laravel.
export function useMarketplaceRecords(props) {
    const page = usePage();
    const professional = computed(() => props.data.screen === 'profesional');
    const ownId = computed(() => page.props.auth?.id ?? (props.data.area === 'profesional' ? 2 : 1));

    function forAccount(record) {
        return props.data.preview || (professional.value ? record.professional_user_id === ownId.value : record.owner_id === ownId.value);
    }

    const proposals = computed(() => (props.data.proposals ?? []).filter(forAccount));
    const invitations = computed(() => (props.data.invitations ?? []).filter(forAccount));

    return { professional, proposals, invitations };
}
