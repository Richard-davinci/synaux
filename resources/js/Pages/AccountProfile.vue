<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import B from '../Components/UI/AppButton.vue';
import F from '../Components/UI/FormField.vue';
import I from '../Components/UI/AppIcon.vue';
import { date, initials } from '../support/formatters';
import { demoNotice } from '../support/prototype';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';

// Cada sección guarda sus propios datos y errores; el tipo de cuenta es informativo y no editable.
const props = defineProps({ profile: Object, issuer: Object, preview: Boolean });
const personalValues = () => Object.fromEntries(['name', 'email', 'phone', 'company', 'address'].map(key => [key, props.profile[key] ?? '']));
const form = useForm({ ...personalValues(), current_password: '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const issuerValues = () => Object.fromEntries(['name', 'email', 'phone', 'address'].map(key => [key, props.issuer?.[key] ?? '']));
const issuerForm = useForm(issuerValues());
const emailChanged = computed(() => form.email.trim().toLowerCase() !== props.profile.email);
const accountHref = computed(() => props.profile.is_admin ? '/gestion' : '/cliente');
function isPreview() {
    if (!props.preview) return false;
    demoNotice.value = 'Vista de referencia: los cambios no se guardan en cuentas reales.';
    return true;
}
function savePersonal() {
    if (isPreview()) return;
    form.patch('/mi-perfil', {
        preserveScroll: true,
        onSuccess: () => { form.defaults({ ...personalValues(), current_password: '' }); form.reset(); },
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
        onSuccess: () => { issuerForm.defaults(issuerValues()); issuerForm.reset(); },
    });
}

</script>

<template>
    <Head title="Mi perfil" />
    <PublicLayout>
        <section class="page shell account-profile">
            <B :href="accountHref" variant="ghost">← Volver al panel</B>
            <header class="account-heading"><p class="eyebrow">Mi cuenta</p><h1>Mi perfil</h1><p>{{ profile.is_admin ? 'Administrá tus datos de contacto, el acceso a tu cuenta y tus datos de emisión.' : 'Administrá tus datos de contacto y la seguridad de tu cuenta.' }}</p></header>
            <div class="account-grid">
                <aside class="account-sidebar">
                    <v-card class="card"><span class="account-avatar" aria-hidden="true">{{ initials(profile.name) }}</span><h2>{{ profile.name }}</h2><p class="account-email">{{ profile.email }}</p><span class="account-role"><I name="user" />{{ profile.is_admin ? 'Administrador' : 'Cliente' }}</span><p v-if="profile.created_at" class="account-help">Cuenta creada el {{ date(profile.created_at) }}</p>
                        <nav aria-label="Secciones de Mi perfil"><a href="#datos-personales">Datos personales y contacto</a><a href="#seguridad">Seguridad</a><a v-if="profile.is_admin" href="#emision">Datos de emisión</a></nav>
                        
                    </v-card>
                </aside>
                <div class="account-panels">
                    <v-card id="datos-personales" class="card">
                        <div class="account-section-title"><I name="user" /><h2>Datos personales y contacto</h2></div>
                        <p>Usaremos estos datos para completar tus nuevas solicitudes. Podrás revisarlos antes de enviar cada pedido.</p>
                        <form @submit.prevent="savePersonal">
                            <F v-model="form.name" label="Nombre y apellido" autocomplete="name" maxlength="255" required :error="form.errors.name" />
                            <F v-model="form.email" label="Correo de ingreso y contacto" type="email" autocomplete="email" maxlength="254" required :error="form.errors.email" />
                            <F v-if="emailChanged" v-model="form.current_password" label="Contraseña actual para cambiar el correo" type="password" autocomplete="current-password" hint="Confirmá tu identidad para cambiar el correo con el que ingresás." required :error="form.errors.current_password" />
                            <div class="form-grid"><F v-model="form.phone" label="Teléfono (opcional)" type="tel" autocomplete="tel" maxlength="50" :error="form.errors.phone" /><F v-model="form.company" label="Empresa u organización (opcional)" autocomplete="organization" maxlength="255" :error="form.errors.company" /></div>
                            <F v-model="form.address" label="Dirección (opcional)" autocomplete="street-address" maxlength="500" :error="form.errors.address" />
                            <p class="account-note"><I name="info" /> Los pedidos ya guardados conservan sus datos de contacto originales.</p>
                            <div class="form-actions"><B type="submit" :disabled="form.processing || !form.isDirty">{{ form.processing ? 'Guardando…' : 'Guardar datos' }}</B></div>
                        </form>
                    </v-card>
                    <v-card id="seguridad" class="card">
                        <div class="account-section-title"><I name="check" /><h2>Seguridad</h2></div><p>Para cambiar tu contraseña necesitás ingresar la actual y confirmar la nueva.</p>
                        <p v-if="preview" class="account-note">El cambio de contraseña está deshabilitado en esta demostración.</p>
                        <form @submit.prevent="savePassword">
                            <F v-model="passwordForm.current_password" label="Contraseña actual" type="password" autocomplete="current-password" :disabled="preview" required :error="passwordForm.errors.current_password" />
                            <F v-model="passwordForm.password" label="Nueva contraseña" type="password" autocomplete="new-password" minlength="8" :disabled="preview" hint="Usá al menos 8 caracteres y una contraseña diferente de la actual." required :error="passwordForm.errors.password" />
                            <F v-model="passwordForm.password_confirmation" label="Repetí la nueva contraseña" type="password" autocomplete="new-password" minlength="8" :disabled="preview" required :error="passwordForm.errors.password_confirmation" />
                            <div class="form-actions"><B type="submit" :disabled="preview || passwordForm.processing || !passwordForm.isDirty">{{ passwordForm.processing ? 'Actualizando…' : 'Cambiar contraseña' }}</B></div>
                        </form>
                    </v-card>
                    <v-card v-if="profile.is_admin" id="emision" class="card">
                        <div class="account-section-title"><I name="file" /><h2>Datos de emisión</h2></div>
                        <p>Estos datos identifican a synaux en los nuevos presupuestos y su PDF. El correo de emisión puede ser diferente del correo de ingreso.</p>
                        <form @submit.prevent="saveIssuer">
                            <F v-model="issuerForm.name" label="Nombre del emisor o razón social" autocomplete="organization" maxlength="255" required :error="issuerForm.errors.name" />
                            <F v-model="issuerForm.email" label="Correo de emisión" type="email" maxlength="254" required :error="issuerForm.errors.email" />
                            <F v-model="issuerForm.phone" label="Teléfono del emisor (opcional)" type="tel" maxlength="50" :error="issuerForm.errors.phone" />
                            <F v-model="issuerForm.address" label="Dirección del emisor (opcional)" maxlength="500" :error="issuerForm.errors.address" />
                            <p class="account-note"><I name="info" /> Los presupuestos ya enviados conservan la identidad de su versión original.</p>
                            <div class="form-actions"><B type="submit" :disabled="issuerForm.processing">{{ issuerForm.processing ? 'Guardando…' : 'Guardar datos de emisión' }}</B></div>
                        </form>
                    </v-card>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* Los datos, la seguridad y la emisión tienen tarjetas independientes y controles cómodos en móvil. */
.account-heading { margin: 24px 0 32px; max-width: 760px; }
.account-heading h1 { font-size: clamp(2.3rem, 5vw, 3.6rem); font-weight: 700; line-height: 1.1; margin-bottom: 16px; }
.account-grid { display: grid; grid-template-columns: minmax(0, .8fr) minmax(0, 1.8fr); gap: 24px; align-items: start; }
.account-grid > * { min-width: 0; }
.account-sidebar { position: sticky; top: 24px; }
.account-avatar { width: 64px; height: 64px; display: grid; place-items: center; border-radius: 50%; background: #153b64; color: white; font-size: 1.4rem; font-weight: 700; margin-bottom: 18px; }
.account-profile h2 { font-size: clamp(1.3rem, 2vw, 1.7rem); font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
.account-email { overflow-wrap: anywhere; }
.account-role { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f5f9fa; border: 1px solid #1f6f7f33; border-radius: 20px; font-weight: 700; }
.account-role .icon { width: 18px; height: 18px; }
.account-help { font-size: .9rem; margin-top: 16px; }
.account-sidebar nav { display: grid; gap: 4px; margin: 20px 0; }
.account-sidebar nav a { display: block; padding: 12px 0; color: #153b64; border-bottom: 1px solid #1f6f7f22; }
.account-sidebar nav a:focus-visible { outline: 3px solid #1f6f7f; outline-offset: 3px; }
.account-sidebar .btn { width: 100%; }
.account-panels { display: grid; gap: 24px; }
.account-panels > .card { scroll-margin-top: 24px; }
.account-section-title { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.account-section-title h2 { margin: 0; }
.account-section-title > .icon { width: 24px; height: 24px; color: #1f6f7f; flex: 0 0 24px; }
.account-note { display: flex; gap: 10px; padding: 14px; background: #f5f9fa; border-radius: 10px; font-size: .9rem; }
.account-note .icon { width: 20px; height: 20px; flex: 0 0 20px; }
@media (max-width: 900px) { .account-grid { grid-template-columns: 1fr; } .account-sidebar { position: static; } }
@media (max-width: 600px) { .form-actions .btn { width: 100%; } }
</style>
