import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

// Formato común de importes y fechas; null significa que todavía no hay un precio configurado.
export const money = (value, currency = 'ARS') => value == null ? 'A definir con el profesional' : new Intl.NumberFormat('es-AR', { style: 'currency', currency:currency || 'ARS', minimumFractionDigits:Number.isInteger(Number(value))?0:2, maximumFractionDigits:2 }).format(Number(value));
export const date = value => value ? new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium', timeZone: 'America/Argentina/Buenos_Aires' }).format(new Date(value.length === 10 ? `${value}T12:00:00` : value.replace(' ', 'T'))) : 'A coordinar';
export const initials = value => (value ?? 'Synaux').split(' ').slice(0, 2).map(s => s[0]).join('').toUpperCase();
export const demoNotice = ref('');

// La galería de referencia adapta enlaces, pero nunca usa el selector visual para autenticar cuentas.
export function referenceHref(href, props) {
    if (!href || !props.preview) return href;
    const clean = href.split('?')[0];
    const key = props.referenceRoutes?.[clean] ?? (clean.startsWith('/servicios/') ? 'servicio-web' : clean.startsWith('/oportunidades/') ? 'oportunidad' : clean.startsWith('/profesionales/') ? 'perfil-profesional' : clean.startsWith('/presupuestos/') ? 'presupuesto' : clean.startsWith('/solicitudes/') ? 'solicitud' : clean.startsWith('/proyectos/') ? 'proyecto' : clean.startsWith('/estimaciones/') ? 'estimaciones' : clean.startsWith('/editor/presupuesto/') ? 'editor-presupuesto' : clean.startsWith('/editor/trabajo/') ? 'editor-trabajo' : clean.startsWith('/trabajos/') ? 'trabajo' : null);
    return key ? `/prototipo/${key}` : href;
}

// En la aplicación normal, Inertia envía el formulario a Laravel y conserva los errores en form.errors.
export function useSubmit() {
    const page = usePage();
    return (form, action, options = {}) => {
        if (page.props.preview) {
            demoNotice.value = 'Vista de referencia: los cambios no se guardan en cuentas reales.';
            if (options.previewTarget) router.get(`/prototipo/${options.previewTarget}`);
            return;
        }
        form.post(action.startsWith('/') ? action : `/acciones/${action}`, { preserveScroll: true, ...options });
    };
}
