<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import BrandLogo from '../Components/BrandLogo.vue';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import { referenceHref, useSubmit } from '../support/synaux';
// Un formulario conserva el correo ante errores y solo envía los campos necesarios para cada pantalla.
const props = defineProps({ data: Object });
const submit = useSubmit();
// El enlace alternativo conserva las rutas reales y su equivalente en la galería de referencia.
const page = usePage();
const form = useForm({ name: '', email: props.data.email ?? '', password: '', password_confirmation: '', token: props.data.token ?? '', remember: false });
const register = computed(() => props.data.screen === 'registro');
const recover = computed(() => props.data.screen === 'recuperar');
const reset = computed(() => props.data.screen === 'restablecer');
const title = computed(() => register.value ? 'Creá una cuenta' : recover.value ? 'Recuperá tu acceso' : reset.value ? 'Elegí una contraseña nueva' : 'Ingresá a tu cuenta');
function send() { submit(form, register.value ? '/crear-cuenta' : recover.value ? '/recuperar-acceso' : reset.value ? '/restablecer' : '/ingresar', { onSuccess: () => form.reset('password', 'password_confirmation'), previewTarget: recover.value ? 'ingreso' : 'cliente' }); }
</script>
<template>
    <section class="auth-page"><v-card class="card auth-card"><BrandLogo /><p class="eyebrow">{{ register ? 'Creá tu espacio' : 'Bienvenido de nuevo' }}</p><h1>{{ title }}</h1><p>{{ recover ? 'Ingresá tu correo para recibir las instrucciones de recuperación.' : register ? 'Guardá estimaciones y seguí todo el proceso.' : 'Consultá solicitudes, presupuestos y proyectos.' }}</p>
        <!-- La validación y el acceso pertenecen a Laravel; no se precargan credenciales de demostración. -->
        <form @submit.prevent="send"><F v-if="register" v-model="form.name" label="Nombre y apellido" autocomplete="name" required :error="form.errors.name" /><F v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" required :error="form.errors.email || (data.referenceKey === 'ingreso-error' ? 'El correo o la contraseña no son correctos.' : '')" /><F v-if="!recover" v-model="form.password" label="Contraseña" type="password" :autocomplete="register || reset ? 'new-password' : 'current-password'" required :error="form.errors.password" /><F v-if="register || reset" v-model="form.password_confirmation" label="Repetí la contraseña" type="password" autocomplete="new-password" required hint="Usá al menos 8 caracteres." /><B v-if="!register && !recover && !reset" href="/recuperar-acceso" variant="ghost">¿Olvidaste tu contraseña?</B><B type="submit" :disabled="form.processing">{{ recover ? 'Enviar instrucciones' : reset ? 'Actualizar contraseña' : register ? 'Crear cuenta' : 'Ingresar' }}</B></form>
        <!-- La pregunta y el enlace permiten saltos de línea sin comprimirse como un botón de ancho completo. -->
        <p class="auth-alt"><span>{{ register ? '¿Ya tenés una cuenta?' : recover || reset ? '¿Ya podés ingresar?' : '¿Todavía no tenés cuenta?' }}</span> <a :href="referenceHref(register || recover || reset ? '/ingresar' : '/crear-cuenta', page.props)">{{ register || recover || reset ? 'Ingresar' : 'Crear cuenta' }}</a></p>
    </v-card></section>
</template>

<style scoped>
/* La pregunta admite varias líneas y mantiene separación legible respecto del enlace de navegación. */
.auth-alt { display: block; margin: 25px 0 0; text-align: center; line-height: 1.7; }
.auth-alt a { display: inline-block; margin-inline-start: 4px; color: #1f6f7f; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
.auth-alt a:hover { color: #153b64; }
.auth-alt a:focus-visible { outline: 2px solid #1f6f7f; outline-offset: 3px; border-radius: 2px; }
</style>
