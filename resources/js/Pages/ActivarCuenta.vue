<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import AuthFrame from '../Components/Auth/AuthFrame.vue';
import B from '../Components/UI/AppButton.vue';
import F from '../Components/UI/FormField.vue';

// La activación se abre desde un enlace externo y carga por sí sola los estilos del sitio y de acceso.
import '../../css/panel.css';

const props = defineProps({token: String, vigente: Boolean});
const form = useForm({password: '', password_confirmation: ''});

// La clave viaja directamente a la validación del enlace y se limpia después de cada intento.
function activar() {
    form.post('/activar-cuenta/' + props.token, {onFinish: () => form.reset('password', 'password_confirmation')});
}
</script>

<template>
    <Head title="Activar cuenta"/>
    <PublicLayout>
        <AuthFrame eyebrow="Acceso al equipo"
                   :title="vigente ? 'Elegí tu contraseña' : 'Enlace de activación no disponible'"
                   :description="vigente ? 'Creá la contraseña que vas a usar para ingresar a synaux.' : 'Si todavía necesitás activar tu cuenta, pedile un enlace nuevo a quien administra tu equipo.'"
                   :alternative="{prompt: '¿Ya activaste tu cuenta?', label: 'Ingresar', href: '/ingresar'}">
            <form v-if="vigente" @submit.prevent="activar">
                <F v-model="form.password" label="Contraseña" type="password" autocomplete="new-password"
                   minlength="8" required :error="form.errors.password"/>
                <F v-model="form.password_confirmation" label="Repetí la contraseña" type="password"
                   autocomplete="new-password" minlength="8" required hint="Usá al menos 8 caracteres."/>
                <B type="submit" :disabled="form.processing">{{ form.processing ? 'Activando…' : 'Activar cuenta' }}</B>
            </form>
        </AuthFrame>
    </PublicLayout>
</template>
