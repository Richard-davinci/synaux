<script setup>
import {computed, reactive} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import B from '../Components/UI/AppButton.vue';
import F from '../Components/UI/FormField.vue';
import I from '../Components/UI/AppIcon.vue';
import PageTitle from '../Components/UI/PageTitle.vue';
import CamposUbicacion from '../Components/Cuenta/CamposUbicacion.vue';
import TarjetaPerfil from '../Components/Cuenta/TarjetaPerfil.vue';
import {date, initials} from '../support/formatters';
import {demoNotice} from '../support/prototype';
import {useNavigationContext} from '../support/useNavigationContext';
import '../../css/panel.css';
import '../../css/ajustes-panel.css';

// Cada tarjeta edita solo sus datos. Los valores de consulta provienen del perfil guardado.
const props = defineProps({profile: Object, preview: Boolean});
const valoresCampos = (datos, claves) => Object.fromEntries(claves.map(clave => [clave, datos?.[clave] ?? '']));
const clavesUbicacion = ['country', 'region', 'city'];
const valores = {
    contacto: () => ({...valoresCampos(props.profile, ['name', 'email', 'phone']), current_password: ''}),
    organizacion: () => ({company: props.profile.company ?? '', details: valoresCampos(props.profile.details, ['position', 'website'])}),
    ubicacion: () => ({details: {location: valoresCampos(props.profile.details?.location, clavesUbicacion)}}),
    domicilio: () => ({
        address: props.profile.address ?? '',
        details: {billing_address: valoresCampos(props.profile.details?.billing_address, [...clavesUbicacion, 'street', 'number', 'unit', 'postal_code'])},
    }),
    clave: () => ({current_password: '', password: '', password_confirmation: ''}),
};
const formularios = Object.fromEntries(Object.entries(valores).map(([seccion, crear]) => [seccion, useForm(crear())]));
const {
    contacto: contactoForm, organizacion: organizacionForm, ubicacion: ubicacionForm,
    domicilio: domicilioForm, clave: passwordForm,
} = formularios;
const editando = reactive({});
const emailChanged = computed(() => contactoForm.email.trim().toLowerCase() !== props.profile.email);
const {accountRole, panelCuenta} = useNavigationContext();
// Los integrantes del equipo administran aquí su cuenta personal. La ficha de la empresa tiene su propia sección.
const esCuentaEquipo = computed(() => Boolean(props.profile.is_admin || props.profile.correo_fijo));
const accountDescription = 'Actualizá tus datos de contacto y tu contraseña.';

const conDatos = filas => filas.filter(([, dato]) => dato);
const filasUbicacion = ubicacion => [
    ['País', ubicacion?.country], ['Provincia, estado o región', ubicacion?.region], ['Localidad o ciudad', ubicacion?.city],
];
const datosContacto = computed(() => conDatos([
    ['Nombre y apellido', props.profile.name], ['Correo de ingreso y contacto', props.profile.email],
    ['Teléfono o WhatsApp', props.profile.phone],
]));
const datosOrganizacion = computed(() => conDatos([
    ['Empresa u organización', props.profile.company], ['Cargo', props.profile.details?.position],
    ['Sitio web', props.profile.details?.website],
]));
const datosUbicacion = computed(() => conDatos(filasUbicacion(props.profile.details?.location)));
const datosDomicilio = computed(() => {
    const domicilio = props.profile.details?.billing_address;
    const filas = conDatos([
        ['Calle y número', [domicilio?.street, domicilio?.number].filter(Boolean).join(' ')],
        ['Piso y departamento', domicilio?.unit], ['Código postal', domicilio?.postal_code],
        ...filasUbicacion(domicilio),
    ]);
    return filas.length ? filas : conDatos([['Dirección de contacto anterior', props.profile.address]]);
});

function copiarUbicacion() {
    Object.assign(domicilioForm.details.billing_address, valoresCampos(props.profile.details?.location, clavesUbicacion));
}

// Al abrir o cancelar se parte de los datos guardados y se descarta solo el borrador de esa tarjeta.
function cambiarEdicion(seccion, estado) {
    formularios[seccion].defaults(valores[seccion]());
    formularios[seccion].resetAndClearErrors();
    editando[seccion] = estado;
}

function guardar(seccion) {
    if (props.preview) {
        demoNotice.value = 'Vista de referencia: los cambios no se guardan en cuentas reales.';
        return;
    }
    const opciones = {preserveScroll: true, onSuccess: () => cambiarEdicion(seccion, false)};
    if (seccion === 'clave') {
        passwordForm.put('/mi-perfil/clave', {...opciones, onFinish: () => passwordForm.reset()});
    } else {
        formularios[seccion].patch('/mi-perfil', {
            ...opciones,
            onFinish: seccion === 'contacto' ? () => contactoForm.reset('current_password') : undefined,
        });
    }
}
</script>

<template>
    <Head title="Mi perfil"/>
    <PublicLayout>
        <section class="page shell workspace-view account-profile">
            <PageTitle eyebrow="Mi cuenta" title="Mi perfil" :description="accountDescription">
                <B v-if="panelCuenta !== '/mi-perfil'" :href="panelCuenta" variant="secondary" compact>← Volver al panel</B>
            </PageTitle>
            <div class="account-grid">
                <!-- La ficha identifica la cuenta y da acceso directo a cada formulario. -->
                <aside class="account-sidebar">
                    <v-card class="card account-summary">
                        <div class="account-identity">
                            <span class="account-avatar" aria-hidden="true">{{ initials(profile.name) }}</span>
                            <h2>{{ profile.name }}</h2>
                        </div>
                        <p class="account-email">{{ profile.email }}</p>
                        <span class="account-role"><I name="user"/>{{ accountRole || (profile.is_admin ? 'Administrador' : 'Cliente') }}</span>
                        <p v-if="profile.created_at" class="account-help">Cuenta creada el {{ date(profile.created_at) }}</p>
                        <nav aria-label="Secciones de Mi perfil">
                            <a href="#datos-personales">Datos personales</a>
                            <template v-if="!esCuentaEquipo">
                                <a href="#organizacion">Organización</a>
                                <a href="#ubicacion">Ubicación</a>
                                <a href="#facturacion">Domicilio de facturación</a>
                            </template>
                            <a href="#seguridad">Seguridad</a>
                        </nav>
                    </v-card>
                </aside>
                <div class="account-panels">
                    <TarjetaPerfil id="datos-personales" titulo="Datos personales y contacto" icono="user"
                                   :datos="datosContacto" accion-guardar="Guardar contacto"
                                   :editando="editando.contacto" :guardando="contactoForm.processing" :modificado="contactoForm.isDirty"
                                   @editar="cambiarEdicion('contacto', true)" @cancelar="cambiarEdicion('contacto', false)"
                                   @guardar="guardar('contacto')">
                        <div class="account-fields">
                            <F v-model="contactoForm.name" label="Nombre y apellido" autocomplete="name" maxlength="255"
                               required :error="contactoForm.errors.name"/>
                            <!-- El correo del equipo se puede leer y copiar mientras se editan los demás datos. -->
                            <F v-model="contactoForm.email" label="Correo de ingreso y contacto" type="email"
                               :class="{'account-email-fixed': profile.correo_fijo}" :readonly="profile.correo_fijo"
                               :hint="profile.correo_fijo ? 'El correo del equipo es fijo.' : undefined"
                               autocomplete="email" maxlength="254" :required="!profile.correo_fijo"
                               :error="contactoForm.errors.email"/>
                            <F v-if="emailChanged" v-model="contactoForm.current_password" class="account-field-wide"
                               label="Contraseña actual para cambiar el correo" type="password"
                               autocomplete="current-password"
                               hint="Confirmá tu identidad para cambiar el correo con el que ingresás." required
                               :error="contactoForm.errors.current_password"/>
                            <F v-model="contactoForm.phone" label="Teléfono o WhatsApp (opcional)" type="tel"
                               autocomplete="tel" placeholder="Ej.: +54 9 221 555 0123"
                               maxlength="50" :error="contactoForm.errors.phone"/>
                        </div>
                    </TarjetaPerfil>
                    <TarjetaPerfil v-if="!esCuentaEquipo" id="organizacion" titulo="Organización" icono="folder"
                                   :datos="datosOrganizacion" vacio="Sin datos de organización." accion-guardar="Guardar organización"
                                   :editando="editando.organizacion" :guardando="organizacionForm.processing" :modificado="organizacionForm.isDirty"
                                   @editar="cambiarEdicion('organizacion', true)" @cancelar="cambiarEdicion('organizacion', false)"
                                   @guardar="guardar('organizacion')">
                        <div class="account-fields">
                            <F v-model="organizacionForm.company" label="Empresa u organización"
                               autocomplete="organization" maxlength="255" :error="organizacionForm.errors.company"/>
                            <F v-model="organizacionForm.details.position" label="Cargo" autocomplete="organization-title"
                               maxlength="120" :error="organizacionForm.errors['details.position']"/>
                            <F v-model="organizacionForm.details.website" class="account-field-wide" label="Sitio web"
                               type="url" autocomplete="url" placeholder="https://ejemplo.com" maxlength="255"
                               :error="organizacionForm.errors['details.website']"/>
                        </div>
                    </TarjetaPerfil>
                    <TarjetaPerfil v-if="!esCuentaEquipo" id="ubicacion" titulo="Ubicación" icono="location"
                                   :datos="datosUbicacion" vacio="Sin ubicación guardada." accion-guardar="Guardar ubicación"
                                   :editando="editando.ubicacion" :guardando="ubicacionForm.processing" :modificado="ubicacionForm.isDirty"
                                   @editar="cambiarEdicion('ubicacion', true)" @cancelar="cambiarEdicion('ubicacion', false)"
                                   @guardar="guardar('ubicacion')">
                        <div class="account-fields">
                            <CamposUbicacion v-model="ubicacionForm.details.location" :errores="ubicacionForm.errors"
                                             prefijo="details.location"/>
                        </div>
                    </TarjetaPerfil>
                    <TarjetaPerfil v-if="!esCuentaEquipo" id="facturacion" titulo="Domicilio de facturación" icono="receipt"
                                   :datos="datosDomicilio" vacio="Sin domicilio de facturación guardado." accion-guardar="Guardar domicilio"
                                   :editando="editando.domicilio" :guardando="domicilioForm.processing" :modificado="domicilioForm.isDirty"
                                   @editar="cambiarEdicion('domicilio', true)" @cancelar="cambiarEdicion('domicilio', false)"
                                   @guardar="guardar('domicilio')">
                        <B v-if="datosUbicacion.length" type="button" variant="secondary" compact
                           class="account-copy-location" @click="copiarUbicacion">Usar mi ubicación</B>
                        <div class="account-fields">
                            <CamposUbicacion v-model="domicilioForm.details.billing_address" :errores="domicilioForm.errors"
                                             prefijo="details.billing_address" facturacion/>
                            <F v-model="domicilioForm.details.billing_address.postal_code" label="Código postal"
                               autocomplete="section-facturacion billing postal-code" maxlength="16"
                               :error="domicilioForm.errors['details.billing_address.postal_code']"/>
                            <F v-model="domicilioForm.details.billing_address.street" label="Calle" maxlength="150"
                               autocomplete="section-facturacion billing address-line1"
                               :error="domicilioForm.errors['details.billing_address.street']"/>
                            <F v-model="domicilioForm.details.billing_address.number" label="Número" maxlength="20"
                               :error="domicilioForm.errors['details.billing_address.number']"/>
                            <F v-model="domicilioForm.details.billing_address.unit" label="Piso y departamento"
                               autocomplete="section-facturacion billing address-line2" maxlength="40"
                               :error="domicilioForm.errors['details.billing_address.unit']"/>
                            <F v-if="profile.address || domicilioForm.address" v-model="domicilioForm.address"
                               class="account-field-wide" label="Dirección de contacto anterior"
                               hint="Se usa cuando todavía no guardaste un domicilio de facturación."
                               maxlength="500" :error="domicilioForm.errors.address"/>
                        </div>
                    </TarjetaPerfil>
                    <TarjetaPerfil id="seguridad" titulo="Seguridad" icono="check"
                                   accion-editar="Cambiar contraseña" accion-guardar="Cambiar contraseña" :guardado-deshabilitado="preview"
                                   :editando="editando.clave" :guardando="passwordForm.processing" :modificado="passwordForm.isDirty"
                                   @editar="cambiarEdicion('clave', true)" @cancelar="cambiarEdicion('clave', false)"
                                   @guardar="guardar('clave')">
                        <p v-if="preview" class="account-note">El cambio de contraseña no está disponible en esta vista.</p>
                        <F v-model="passwordForm.current_password" label="Contraseña actual" type="password"
                           autocomplete="current-password" :disabled="preview" required
                           :error="passwordForm.errors.current_password"/>
                        <F v-model="passwordForm.password" label="Nueva contraseña" type="password"
                           autocomplete="new-password" minlength="8" :disabled="preview"
                           hint="Usá al menos 8 caracteres y una contraseña diferente de la actual." required
                           :error="passwordForm.errors.password"/>
                        <F v-model="passwordForm.password_confirmation" label="Repetí la nueva contraseña"
                           type="password" autocomplete="new-password" minlength="8" :disabled="preview" required
                           :error="passwordForm.errors.password_confirmation"/>
                    </TarjetaPerfil>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* El perfil usa la escala del panel y reserva más espacio para los datos que para la navegación local. */
.account-grid {
    --account-offset: calc(var(--navbar-height, 76px) + var(--workspace-bar-height, 0px) + 16px);
    display: grid;
    grid-template-columns: minmax(220px, 252px) minmax(0, 1fr);
    gap: clamp(16px, 2vw, 24px);
    align-items: start;
}

.account-sidebar {
    position: sticky;
    top: var(--account-offset);
    min-width: 0;
}

.account-profile .account-summary {
    padding: 18px;
}

.account-identity {
    display: flex;
    align-items: center;
    gap: 12px;
}

.account-avatar {
    display: grid;
    flex: 0 0 44px;
    height: 44px;
    place-items: center;
    border-radius: 50%;
    background: #153b64;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
}

.account-profile .account-identity h2 {
    margin: 0;
    font-size: 1rem;
    overflow-wrap: anywhere;
}

.account-summary .account-email {
    margin: 10px 0 12px;
    overflow-wrap: anywhere;
}

.account-role {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 9px;
    background: #f5f9fa;
    border: 1px solid #1f6f7f33;
    border-radius: 20px;
    color: #153b64;
    font-size: .8rem;
    font-weight: 700;
}

.account-role .icon {
    width: 15px;
    height: 15px;
}

.account-summary .account-help {
    margin: 8px 0 0;
    font-size: .8rem;
}

.account-sidebar nav {
    display: grid;
    margin-top: 14px;
    border-top: 1px solid #1f6f7f22;
    padding-top: 4px;
}

.account-sidebar nav a {
    display: flex;
    align-items: center;
    min-height: 44px;
    padding: 4px 8px;
    border-radius: 6px;
    color: #153b64;
    font-size: .9rem;
    font-weight: 700;
    text-decoration: none;
}

.account-sidebar nav a:hover {
    background: #f5f9fa;
    text-decoration: underline;
}

.account-sidebar nav a:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 2px;
}

/* Las tarjetas y los campos comparten alineación, y los enlaces dejan la sección debajo del encabezado fijo. */
.account-panels {
    display: grid;
    gap: 20px;
    min-width: 0;
}

.account-panels > .card {
    padding: clamp(18px, 2vw, 28px);
    scroll-margin-top: var(--account-offset);
}

.account-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    column-gap: 16px;
}

/* La ayuda queda debajo de su campo y el correo fijo conserva el contraste del texto. */
.account-email-fixed :deep(.v-field) {
    background: #f5f9fa;
}

.account-field-wide {
    grid-column: 1 / -1;
}

.account-copy-location {
    margin-bottom: 14px;
}

.account-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
    background: #f5f9fa;
    border-radius: 8px;
    font-size: .9rem;
}

.account-note .icon {
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
}

@media (max-width: 900px) {
    .account-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .account-sidebar {
        position: static;
    }

    .account-sidebar nav {
        display: flex;
        flex-wrap: wrap;
        gap: 0 8px;
    }
}

@media (max-width: 600px) {
    .account-fields {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
