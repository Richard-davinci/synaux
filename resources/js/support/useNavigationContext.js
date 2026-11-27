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
    publicar: 'estimaciones',
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
        return panelCuenta.value === '/mi-perfil' ? 'public' : panelCuenta.value.split('/')[1];
    });

    // Las secciones de la segunda etapa aparecen al entrar expresamente en el área correspondiente.
    const nav = computed(() => {
        if (panelCuenta.value === '/profesional/proyectos' && !page.props.stageTwo) {
            return [['Mis proyectos', '/profesional/proyectos']];
        }
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
        if (page.props.screen === 'solicitud' && page.props.selected?.status === 'draft') return 'estimaciones';
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
        // El detalle de presupuesto ya muestra sus propias migas dentro del documento.
        if (screen === 'presupuesto') return [];
        if (screen === 'publicar') return [inicio, seccion, ['Nueva estimación']];
        if (screen === 'solicitud' && selected?.status === 'draft') {
            return [inicio, seccion, ['EST-' + (selected.estimate_id ?? selected.id)]];
        }
        const identificador = identificadoresCliente[screen];
        if (identificador) {
            const [prefijo, campo] = identificador;
            const id = selected?.[campo] ?? selected?.id;
            if (id != null) return [inicio, seccion, [prefijo + '-' + id]];
        }
        return [inicio, [seccion[0]]];
    });

    const adminView = computed(() => area.value === 'gestion' && currentSection.value === 'administracion');
    // Las áreas privadas comparten migas; las fichas que ya las dibujan conservan un único encabezado de navegación.
    const migasPanel = computed(() => {
        if (!hasWorkspace.value || !paginaDePanel.value) return [];
        if (area.value === 'cliente') return migasCliente.value;

        const {screen, selected, section} = page.props;
        const pathname = path.value;
        const esEditorServicio = screen === 'admin' && /\/servicios\/(nuevo|\d+\/editar)$/.test(pathname);
        const tieneMigasPropias = ['editor-presupuesto', 'presupuesto'].includes(screen)
            || (screen === 'solicitud' && area.value === 'gestion' && selected?.status !== 'draft')
            || esEditorServicio;
        if (tieneMigasPropias) return [];

        if (adminView.value) {
            const clave = section || pathname.split('/')[2] || 'servicios';
            const nombres = {
                servicios: 'Servicios', categorias: 'Categorías', tarifario: 'Tarifarios',
                organizacion: 'Datos de la organización', usuarios: 'Equipo', preguntas: 'Preguntas',
                componentes: 'Componentes', reglas: 'Reglas'
            };
            return [['Administración', '/admin/servicios'], [nombres[clave] || 'Administración']];
        }

        const raices = {
            gestion: ['Gestión', '/gestion'],
            profesional: ['Área profesional', '/profesional']
        };
        const raiz = raices[area.value];
        if (!raiz) return [];
        if (pathname === raiz[1]) return [[raiz[0]]];

        const prefijo = raiz[1] + '/';
        const seccion = nav.value.find(([, href]) => href === prefijo + (section || currentSection.value));
        if (!seccion) return [[...raiz], [section || 'Panel']];

        const niveles = [[...raiz], [seccion[0], seccion[1]]];
        const identificador = identificadoresCliente[screen];
        if (identificador && selected) {
            const [prefijoId, campo] = identificador;
            const id = selected[campo] ?? selected.id;
            if (id != null) niveles.push([prefijoId + '-' + id]);
        }
        return niveles;
    });

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
        migasPanel,
        adminView,
        workspaceTitle,
        showAreaSwitch,
        panelCuenta
    };
}
