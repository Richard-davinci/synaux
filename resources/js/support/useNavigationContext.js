import {computed} from 'vue';
import {usePage} from '@inertiajs/vue3';

export const publicNav = [
    ['Inicio', '/inicio'],
    ['Catálogo', '/plataforma'],
    ['Portfolio', '/portfolio'],
    ['Nosotros', '/nosotros'],
    ['Contacto', '/contacto'],
];

const workspaceNav = {
    cliente: [
        ['Resumen', '/cliente'],
        ['Estimaciones', '/cliente/estimaciones'],
        ['Solicitudes', '/cliente/solicitudes'],
        ['Publicaciones', '/cliente/publicaciones'],
        ['Propuestas', '/cliente/propuestas'],
        ['Presupuestos', '/cliente/presupuestos'],
        ['Proyectos', '/cliente/proyectos'],
    ],
    profesional: [
        ['Resumen', '/profesional'],
        ['Oportunidades', '/oportunidades'],
        ['Mis propuestas', '/profesional/propuestas'],
        ['Invitaciones', '/profesional/invitaciones'],
        ['Presupuestos', '/profesional/presupuestos'],
        ['Proyectos', '/profesional/proyectos'],
        ['Mi perfil', '/profesional/perfil'],
    ],
    gestion: [
        ['Resumen', '/gestion'],
        ['Solicitudes', '/gestion/solicitudes'],
        ['Presupuestos', '/gestion/presupuestos'],
        ['Proyectos', '/gestion/proyectos'],
        ['Consultas', '/gestion/contactos'],
        ['Portfolio', '/gestion/portfolio'],
    ],
};

const stageTwoPaths = ['/cliente/publicaciones', '/cliente/propuestas', '/cliente/invitaciones'];
const sectionsByScreen = {
    solicitud: 'solicitudes',
    publicar: 'solicitudes',
    presupuesto: 'presupuestos',
    'editor-presupuesto': 'presupuestos',
    proyecto: 'proyectos',
    estimacion: 'estimaciones',
    'editor-trabajo': 'portfolio',
};

// El estado de cada área sale de Inertia. Mostrar un enlace no concede permisos.
export function useNavigationContext() {
    const page = usePage();
    const areaDePagina = computed(() => {
        if (page.props.area != null) return page.props.area;
        if (page.url.startsWith('/gestion') || page.url.startsWith('/admin')) return 'gestion';
        if (page.url.startsWith('/profesional')) return 'profesional';
        return page.url.startsWith('/cliente') ? 'cliente' : 'public';
    });
    const path = computed(() => page.url.split(/[?#]/)[0]);
    const paginaDePanel = computed(() => areaDePagina.value !== 'public');
    // Una página pública conserva la navegación de la cuenta sin marcar una sección privada.
    const area = computed(() => {
        if (paginaDePanel.value || page.props.preview || !page.props.auth) return areaDePagina.value;
        return page.props.auth.is_admin ? 'gestion' : 'cliente';
    });

    // Las secciones de la segunda etapa aparecen al entrar expresamente en el área correspondiente.
    const nav = computed(() => (workspaceNav[area.value] ?? publicNav).filter(([, href]) =>
        !stageTwoPaths.includes(href) || (page.props.stageTwo && stageTwoPaths.includes(path.value))
    ));
    const hasWorkspace = computed(() => page.props.preview ? paginaDePanel.value : Boolean(page.props.auth));
    const navbarRole = computed(() => {
        if (page.props.preview) return hasWorkspace.value ? (area.value === 'gestion' ? 'admin' : area.value) : null;
        if (page.props.auth?.is_admin) return 'admin';
        return page.props.auth ? (area.value === 'profesional' ? 'profesional' : 'cliente') : null;
    });
    const accountName = computed(() => page.props.auth?.name ?? (page.props.preview ? 'Cuenta de ejemplo' : ''));
    const currentSection = computed(() => {
        if (!paginaDePanel.value) return null;
        if (path.value === '/mi-perfil' || ['cuenta-admin', 'cuenta-cliente'].includes(page.props.referenceKey)) return 'mi-perfil';
        if (page.props.screen === 'admin' || page.url.startsWith('/admin')) return 'administracion';
        return sectionsByScreen[page.props.screen] ?? (page.props.section || 'resumen');
    });
    const adminView = computed(() => area.value === 'gestion' && currentSection.value === 'administracion');
    const workspaceTitle = computed(() => {
        if (adminView.value) return 'Administración';
        if (area.value === 'gestion') return 'Panel de gestión';
        return area.value === 'profesional' ? 'Área profesional' : 'Mi espacio';
    });
    const showAreaSwitch = computed(() => page.props.preview ? area.value === 'gestion' : Boolean(page.props.auth?.is_admin));

    return {
        area,
        nav,
        hasWorkspace,
        paginaDePanel,
        navbarRole,
        accountName,
        currentSection,
        adminView,
        workspaceTitle,
        showAreaSwitch
    };
}
