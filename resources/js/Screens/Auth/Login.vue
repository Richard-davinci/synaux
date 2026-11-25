<script setup>
import {useForm} from '@inertiajs/vue3';
import AuthFrame from '../../Components/Auth/AuthFrame.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import {useSubmit} from '../../support/useSubmit';

const props = defineProps({data: {type: Object, required: true}});

// Laravel valida las credenciales y decide el destino según el rol y la sesión previa.
const form = useForm({email: props.data.email ?? '', password: '', remember: false});
const submit = useSubmit();

function send() {
    submit(form, '/ingresar', {
        onSuccess: () => form.reset('password'),
        previewTarget: 'cliente',
    });
}
</script>

<template>
    <AuthFrame eyebrow="Bienvenido de nuevo" title="Ingresá a tu cuenta"
               :description="data.loginRequired ? 'Ingresá para acceder a la página que intentaste abrir.' : 'Consultá solicitudes, presupuestos y proyectos.'"
               :alternative="{ prompt: '¿Todavía no tenés cuenta?', label: 'Crear cuenta', href: '/crear-cuenta' }">
        <form @submit.prevent="send">
            <F v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" required
               :error="form.errors.email || (data.referenceKey === 'ingreso-error' ? 'El correo o la contraseña no son correctos.' : '')"/>
            <F v-model="form.password" label="Contraseña" type="password" autocomplete="current-password" required
               :error="form.errors.password"/>
            <B href="/recuperar-acceso" variant="ghost">¿Olvidaste tu contraseña?</B>
            <B type="submit" :disabled="form.processing">Ingresar</B>
        </form>
    </AuthFrame>
</template>
