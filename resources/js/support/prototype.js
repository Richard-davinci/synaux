import { ref } from 'vue';

export const demoNotice = ref('');

const detailScreens = [
    ['/servicios/', 'servicio-web'],
    ['/oportunidades/', 'oportunidad'],
    ['/profesionales/', 'perfil-profesional'],
    ['/presupuestos/', 'presupuesto'],
    ['/solicitudes/', 'solicitud'],
    ['/proyectos/', 'proyecto'],
    ['/estimaciones/', 'estimaciones'],
    ['/editor/presupuesto/', 'editor-presupuesto'],
    ['/editor/trabajo/', 'editor-trabajo'],
    ['/trabajos/', 'trabajo'],
];

// La galería usa sus rutas explícitas y adapta los enlaces a detalles de ejemplo.
export function referenceHref(href, props) {
    if (!href || !props.preview) return href;

    const path = href.split('?')[0];
    const key = props.referenceRoutes?.[path] ?? detailScreens.find(([prefix]) => path.startsWith(prefix))?.[1];

    return key ? `/prototipo/${key}` : href;
}
