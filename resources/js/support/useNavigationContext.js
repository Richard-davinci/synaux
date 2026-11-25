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
        ['Clientes', '/gestion/clientes'],
        ['Portfolio', '/gestion/portfolio'],
    ],
};

const identificadoresCliente = {
    estimacion: ['EST', 'id'],
    solicitud: ['SOL', 'id'],
    presupuesto: ['PRE', 'budget_id'],
    proyecto: ['PRY', 'id'],
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
    // El destino efectivo llega del servidor y es independiente de la sección que se está viendo.
    const panelCuenta = computed(() => page.props.auth?.panel_principal
        ?? (page.props.auth?.is_admin || (page.props.preview && areaDePagina.value === 'gestion') ? '/gestion'
            : areaDePagina.value === 'profesional' ? '/profesional' : '/cliente'));
    const paginaDePanel = computed(() => areaDePagina.value !== 'public');
    // Una página pública conserva la navegación de la cuenta sin marcar una sección privada.
    const area = computed(() => {
        if (paginaDePanel.value || page.props.preview || !page.props.auth) return areaDePagina.value;
        return panelCuenta.value === '/mi-perfil' ? 'public' : panelCuenta.value.slice(1);
    });

    // Las secciones de la segunda etapa aparecen al entrar expresamente en el área correspondiente.
    const nav = computed(() => {
        return (workspaceNav[area.value] ?? publicNav).filter(([, href]) =>
            !stageTwoPaths.includes(href) || (page.props.stageTwo && stageTwoPaths.includes(path.value))
        );
    });
    const hasWorkspace = computed(() => page.props.preview ? paginaDePanel.value : Boolean(page.props.auth));
    const accountName = computed(() => page.props.auth?.name ?? (page.props.preview ? 'Cuenta de ejemplo' : ''));
    // El rol visible describe la cuenta. El cliente se identifica solo por su nombre e iniciales.
    const accountRole = computed(() => {
        const auth = page.props.auth;
        if (auth?.is_admin || (page.props.preview && area.value === 'gestion')) return 'Administrador';
        const roles = {administrador: 'Administrador', supervisor: 'Supervisor', profesional: 'Profesional interno'};
        return roles[auth?.rol_organizacion] ?? (area.value === 'profesional' ? 'Profesional' : '');
    });
    const currentSection = computed(() => {
        if (!paginaDePanel.value) return null;
        if (path.value === '/mi-perfil' || ['cuenta-admin', 'cuenta-cliente'].includes(page.props.referenceKey)) return 'mi-perfil';
        if (page.props.screen === 'admin' || page.url.startsWith('/admin')) return 'administracion';
        return sectionsByScreen[page.props.screen] ?? (page.props.section || 'resumen');
    });
    // Las migas del cliente reutilizan el nombre y destino de la sección del menú lateral.
    const migasCliente = computed(() => {
        if (areaDePagina.value !== 'cliente' || !hasWorkspace.value) return [];

        const inicio = ['Mi espacio', '/cliente'];
        if (currentSection.value === 'mi-perfil') return [inicio, ['Mi perfil']];

        const seccion = nav.value.find(([, href]) => href === '/cliente/' + currentSection.value);
        if (!seccion) return [];

        const {screen, selected} = page.props;
        if (screen === 'publicar') return [inicio, seccion, ['Nueva solicitud']];
        const identificador = identificadoresCliente[screen];
        if (identificador) {
            const [prefijo, campo] = identificador;
            const id = selected?.[campo] ?? selected?.id;
            if (id != null) return [inicio, seccion, [prefijo + '-' + id]];
        }
        return [inicio, [seccion[0]]];
    });

    const adminView = computed(() => area.value === 'gestion' && currentSection.value === 'administracion');
    // La línea del navbar identifica el área abierta, incluso al navegar con la misma cuenta administradora.
    const navbarRole = computed(() => {
        if (!hasWorkspace.value) return null;
        return adminView.value ? 'admin' : area.value;
    });
    const workspaceTitle = computed(() => {
        if (adminView.value) return 'Administración';
        if (area.value === 'gestion') return 'Gestión';
        return area.value === 'profesional' ? 'Área profesional' : 'Mi espacio';
    });
    const showAreaSwitch = computed(() => page.props.preview ? area.value === 'gestion' : Boolean(page.props.auth?.puede_administrar ?? page.props.auth?.is_admin));

    return {
        area,
        nav,
        hasWorkspace,
        paginaDePanel,
        navbarRole,
        accountName,
        accountRole,
        currentSection,
        migasCliente,
        adminView,
        workspaceTitle,
        showAreaSwitch,
        panelCuenta
    };
}
