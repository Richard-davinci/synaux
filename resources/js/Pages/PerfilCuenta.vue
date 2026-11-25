<script setup>
import {computed} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import B from '../Components/UI/AppButton.vue';
import F from '../Components/UI/FormField.vue';
import I from '../Components/UI/AppIcon.vue';
import PageTitle from '../Components/UI/PageTitle.vue';
import CamposUbicacion from '../Components/Cuenta/CamposUbicacion.vue';
import {date, initials} from '../support/formatters';
import {demoNotice} from '../support/prototype';
import '../../css/panel.css';
import '../../css/ajustes-panel.css';

// Cada sección guarda sus propios datos y errores; el tipo de cuenta es informativo y no editable.
const props = defineProps({profile: Object, issuer: Object, preview: Boolean});
const valoresCampos = (datos, claves) => Object.fromEntries(claves.map(clave => [clave, datos?.[clave] ?? '']));
const clavesUbicacion = ['country', 'region', 'city'];
const personalValues = () => ({
    ...valoresCampos(props.profile, ['name', 'email', 'phone', 'company', 'address']),
    details: {
        ...valoresCampos(props.profile.details, ['position', 'website']),
        location: valoresCampos(props.profile.details?.location, clavesUbicacion),
        billing_address: valoresCampos(props.profile.details?.billing_address, [...clavesUbicacion, 'street', 'number', 'unit', 'postal_code']),
    },
});
const form = useForm({...personalValues(), current_password: ''});
const passwordForm = useForm({current_password: '', password: '', password_confirmation: ''});
const issuerValues = () => Object.fromEntries(['name', 'email', 'phone', 'address'].map(key => [key, props.issuer?.[key] ?? '']));
const issuerForm = useForm(issuerValues());
const emailChanged = computed(() => form.email.trim().toLowerCase() !== props.profile.email);
const accountHref = computed(() => props.profile.is_admin ? '/gestion' : '/cliente');
const accountDescription = computed(() => props.profile.is_admin
    ? 'Actualizá tus datos de contacto, tu contraseña y los datos de emisión de los presupuestos.'
    : 'Actualizá tus datos de contacto y tu contraseña.');

// Al copiar la ubicación, los dos grupos siguen siendo editables por separado.
function copiarUbicacion() {
    Object.assign(form.details.billing_address, form.details.location);
}

function isPreview() {
    if (!props.preview) return false;
    demoNotice.value = 'Vista de referencia: los cambios no se guardan en cuentas reales.';
    return true;
}

function savePersonal() {
    if (isPreview()) return;
    form.patch('/mi-perfil', {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({...personalValues(), current_password: ''});
            form.reset();
        },
        onFinish: () => form.reset('current_password'),
    });
}

// Las claves se limpian al terminar el intento y nunca forman parte de los datos que devuelve Laravel.
function savePassword() {
    if (isPreview()) return;
    passwordForm.put('/mi-perfil/clave', {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}

function saveIssuer() {
    if (isPreview()) return;
    issuerForm.patch('/mi-perfil/emision', {
        preserveScroll: true,
        onSuccess: () => {
            issuerForm.defaults(issuerValues());
            issuerForm.reset();
        },
    });
}

</script>

<template>
    <Head title="Mi perfil"/>
    <PublicLayout>
        <section class="page shell workspace-view account-profile">
            <PageTitle eyebrow="Mi cuenta" title="Mi perfil" :description="accountDescription">
                <B :href="accountHref" variant="secondary" compact>← Volver al panel</B>
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
                        <span class="account-role"><I name="user"/>{{ profile.is_admin ? 'Administrador' : 'Cliente' }}</span>
                        <p v-if="profile.created_at" class="account-help">Cuenta creada el {{ date(profile.created_at) }}</p>
                        <nav aria-label="Secciones de Mi perfil">
                            <a href="#datos-personales">Datos personales</a>
                            <a href="#organizacion">Organización</a>
                            <a href="#ubicacion">Ubicación</a>
                            <a href="#facturacion">Domicilio de facturación</a>
                            <a href="#seguridad">Seguridad</a>
                            <a v-if="profile.is_admin" href="#emision">Datos de emisión</a>
                        </nav>
                    </v-card>
                </aside>
                <div class="account-panels">
                    <!-- Las tarjetas de contacto comparten un solo guardado para conservar los cambios entre secciones. -->
                    <form class="account-personal-form" @submit.prevent="savePersonal">
                        <v-card id="datos-personales" tag="section" class="card" aria-labelledby="titulo-datos-personales">
                            <div class="account-section-title">
                                <I name="user"/>
                                <h2 id="titulo-datos-personales">Datos personales y contacto</h2>
                            </div>
                            <p class="account-description">Tu contacto se completa automáticamente en las próximas solicitudes.</p>
                            <!-- Los datos principales se leen en una fila y los campos largos ocupan todo el ancho. -->
                            <div class="account-fields">
                                <F v-model="form.name" label="Nombre y apellido" autocomplete="name" maxlength="255"
                                   required :error="form.errors.name"/>
                                <F v-model="form.email" label="Correo de ingreso y contacto" type="email"
                                   autocomplete="email" maxlength="254" required :error="form.errors.email"/>
                                <F v-if="emailChanged" v-model="form.current_password" class="account-field-wide"
                                   label="Contraseña actual para cambiar el correo" type="password"
                                   autocomplete="current-password"
                                   hint="Confirmá tu identidad para cambiar el correo con el que ingresás." required
                                   :error="form.errors.current_password"/>
                                <F v-model="form.phone" label="Teléfono o WhatsApp (opcional)" type="tel"
                                   autocomplete="tel" placeholder="Ej.: +54 9 221 555 0123"
                                   maxlength="50" :error="form.errors.phone"/>
                            </div>
                        </v-card>
                        <v-card id="organizacion" tag="section" class="card" aria-labelledby="titulo-organizacion">
                            <div class="account-section-title">
                                <I name="folder"/>
                                <h2 id="titulo-organizacion">Organización</h2>
                                <span class="account-optional">Opcional</span>
                            </div>
                            <div class="account-fields">
                                <F v-model="form.company" label="Empresa u organización"
                                   autocomplete="organization" maxlength="255" :error="form.errors.company"/>
                                <F v-model="form.details.position" label="Cargo" autocomplete="organization-title"
                                   maxlength="120" :error="form.errors['details.position']"/>
                                <F v-model="form.details.website" class="account-field-wide" label="Sitio web"
                                   type="url" autocomplete="url" placeholder="https://ejemplo.com" maxlength="255"
                                   :error="form.errors['details.website']"/>
                            </div>
                        </v-card>
                        <v-card id="ubicacion" tag="section" class="card" aria-labelledby="titulo-ubicacion">
                            <div class="account-section-title">
                                <I name="location"/>
                                <h2 id="titulo-ubicacion">Ubicación</h2>
                                <span class="account-optional">Opcional</span>
                            </div>
                            <div class="account-fields">
                                <CamposUbicacion v-model="form.details.location" :errores="form.errors"
                                                 prefijo="details.location"/>
                            </div>
                        </v-card>
                        <v-card id="facturacion" tag="section" class="card" aria-labelledby="titulo-facturacion">
                            <div class="account-section-title">
                                <I name="receipt"/>
                                <h2 id="titulo-facturacion">Domicilio de facturación</h2>
                                <span class="account-optional">Opcional</span>
                            </div>
                            <B v-if="Object.values(form.details.location).some(Boolean)" type="button"
                               variant="secondary" compact class="account-copy-location" @click="copiarUbicacion">
                                Usar mi ubicación
                            </B>
                            <div class="account-fields">
                                <CamposUbicacion v-model="form.details.billing_address" :errores="form.errors"
                                                 prefijo="details.billing_address" facturacion/>
                                <F v-model="form.details.billing_address.postal_code" label="Código postal"
                                   autocomplete="section-facturacion billing postal-code" maxlength="16"
                                   :error="form.errors['details.billing_address.postal_code']"/>
                                <F v-model="form.details.billing_address.street" label="Calle" maxlength="150"
                                   autocomplete="section-facturacion billing address-line1"
                                   :error="form.errors['details.billing_address.street']"/>
                                <F v-model="form.details.billing_address.number" label="Número" maxlength="20"
                                   :error="form.errors['details.billing_address.number']"/>
                                <F v-model="form.details.billing_address.unit" label="Piso y departamento"
                                   autocomplete="section-facturacion billing address-line2" maxlength="40"
                                   :error="form.errors['details.billing_address.unit']"/>
                                <F v-if="profile.address || form.address" v-model="form.address"
                                   class="account-field-wide" label="Dirección de contacto anterior"
                                   hint="Se usa cuando todavía no guardaste un domicilio de facturación."
                                   maxlength="500" :error="form.errors.address"/>
                            </div>
                        </v-card>
                        <div class="form-actions account-profile-actions">
                            <B type="submit" :disabled="form.processing || !form.isDirty">{{
                                form.processing ? 'Guardando…' : 'Guardar datos del perfil'
                            }}</B>
                        </div>
                    </form>
                    <v-card id="seguridad" tag="section" class="card" aria-labelledby="titulo-seguridad">
                        <div class="account-section-title"><I name="check"/>
                            <h2 id="titulo-seguridad">Seguridad</h2></div>
                        <p class="account-description">Para cambiar tu contraseña necesitás ingresar la actual y confirmar la nueva.</p>
                        <p v-if="preview" class="account-note">El cambio de contraseña está deshabilitado en esta
                            demostración.</p>
                        <form @submit.prevent="savePassword">
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
                            <div class="form-actions"><B type="submit"
                                                         :disabled="preview || passwordForm.processing || !passwordForm.isDirty">{{
                                    passwordForm.processing ? 'Actualizando…' : 'Cambiar contraseña'
                                }}</B></div>
                        </form>
                    </v-card>
                    <v-card v-if="profile.is_admin" id="emision" tag="section" class="card" aria-labelledby="titulo-emision">
                        <div class="account-section-title"><I name="file"/>
                            <h2 id="titulo-emision">Datos de emisión</h2></div>
                        <p class="account-description">Estos datos identifican a synaux en los nuevos presupuestos y su PDF. El correo de emisión
                            puede ser diferente del correo de ingreso.</p>
                        <form @submit.prevent="saveIssuer">
                            <F v-model="issuerForm.name" label="Nombre del emisor o razón social"
                               autocomplete="organization" maxlength="255" required :error="issuerForm.errors.name"/>
                            <F v-model="issuerForm.email" label="Correo de emisión" type="email" maxlength="254"
                               required :error="issuerForm.errors.email"/>
                            <F v-model="issuerForm.phone" label="Teléfono del emisor (opcional)" type="tel"
                               maxlength="50" :error="issuerForm.errors.phone"/>
                            <F v-model="issuerForm.address" label="Dirección del emisor (opcional)" maxlength="500"
                               :error="issuerForm.errors.address"/>
                            <p class="account-note"><I name="info"/> Los presupuestos ya enviados conservan la identidad
                                de su versión original.</p>
                            <div class="form-actions"><B type="submit" :disabled="issuerForm.processing">{{
                                    issuerForm.processing ? 'Guardando…' : 'Guardar datos de emisión'
                                }}</B></div>
                        </form>
                    </v-card>
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
.account-panels,
.account-personal-form {
    display: grid;
    gap: 20px;
    min-width: 0;
}

.account-panels > .card,
.account-personal-form > .card {
    padding: clamp(18px, 2vw, 28px);
    scroll-margin-top: var(--account-offset);
}

.account-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.account-profile .account-section-title h2 {
    margin: 0;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.account-section-title > .icon {
    width: 20px;
    height: 20px;
    color: #1f6f7f;
    flex: 0 0 20px;
}

.account-panels .account-description {
    max-width: 66ch;
    margin-bottom: 16px;
}

.account-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 16px;
}

.account-field-wide {
    grid-column: 1 / -1;
}

/* El rótulo indica los grupos que pueden quedar vacíos. */
.account-optional {
    flex-shrink: 0;
    margin-left: auto;
    color: #446075;
    font-size: .8rem;
}

.account-personal-form .account-section-title {
    margin-bottom: 12px;
}

.account-personal-form .account-fields > :last-child {
    margin-bottom: 0;
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

.account-panels .form-actions {
    margin-top: 16px;
}

.account-panels .account-profile-actions {
    margin-top: 0;
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

    .form-actions .btn {
        width: 100%;
    }
}
</style>
