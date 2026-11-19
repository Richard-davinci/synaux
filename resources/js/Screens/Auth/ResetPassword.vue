<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthFrame from '../../Components/Auth/AuthFrame.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import { useSubmit } from '../../support/useSubmit';

const props = defineProps({ data: { type: Object, required: true } });

// El token llega desde la ruta de recuperación y Laravel comprueba su vigencia.
const form = useForm({
    token: props.data.token ?? '',
    email: props.data.email ?? '',
    password: '',
    password_confirmation: '',
});
const submit = useSubmit();

function send() {
    submit(form, '/restablecer', {
        onSuccess: () => form.reset('password', 'password_confirmation'),
        previewTarget: 'cliente',
    });
}
</script>

<template>
    <AuthFrame eyebrow="Bienvenido de nuevo" title="Elegí una contraseña nueva" description="Consultá solicitudes, presupuestos y proyectos."
        :alternative="{ prompt: '¿Ya podés ingresar?', label: 'Ingresar', href: '/ingresar' }">
        <form @submit.prevent="send">
            <F v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" required
                :error="form.errors.email" />
            <F v-model="form.password" label="Contraseña" type="password" autocomplete="new-password" required
                :error="form.errors.password" />
            <F v-model="form.password_confirmation" label="Repetí la contraseña" type="password" autocomplete="new-password"
                required hint="Usá al menos 8 caracteres." />
            <B type="submit" :disabled="form.processing">Actualizar contraseña</B>
        </form>
    </AuthFrame>
</template>
