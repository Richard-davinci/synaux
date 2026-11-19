import { router, usePage } from '@inertiajs/vue3';
import { demoNotice } from './prototype';

// Inertia conserva los errores del formulario; en el prototipo se simula el envío.
export function useSubmit() {
    const page = usePage();

    return (form, action, options = {}) => {
        if (page.props.preview) {
            demoNotice.value = 'Vista de referencia: los cambios no se guardan en cuentas reales.';
            if (options.previewTarget) router.get(`/prototipo/${options.previewTarget}`);
            return;
        }

        const url = action.startsWith('/') ? action : `/acciones/${action}`;
        form.post(url, { preserveScroll: true, ...options });
    };
}
