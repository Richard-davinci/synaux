<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthFrame from '../../Components/Auth/AuthFrame.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import { useSubmit } from '../../support/synaux';

const props = defineProps({ data: { type: Object, required: true } });

// El alta envía los datos de la cuenta nueva a Laravel.
const form = useForm({ name: '', email: props.data.email ?? '', password: '', password_confirmation: '' });
const submit = useSubmit();

function send() {
    submit(form, '/crear-cuenta', {
        onSuccess: () => form.reset('password', 'password_confirmation'),
        previewTarget: 'cliente',
    });
}
</script>

<template>
    <AuthFrame eyebrow="Creá tu espacio" title="Creá una cuenta" description="Guardá estimaciones y seguí todo el proceso."
        :alternative="{ prompt: '¿Ya tenés una cuenta?', label: 'Ingresar', href: '/ingresar' }">
        <form @submit.prevent="send">
            <F v-model="form.name" label="Nombre y apellido" autocomplete="name" required :error="form.errors.name" />
            <F v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" required
                :error="form.errors.email" />
            <F v-model="form.password" label="Contraseña" type="password" autocomplete="new-password" required
                :error="form.errors.password" />
            <F v-model="form.password_confirmation" label="Repetí la contraseña" type="password" autocomplete="new-password"
                required hint="Usá al menos 8 caracteres." />
            <B type="submit" :disabled="form.processing">Crear cuenta</B>
        </form>
    </AuthFrame>
</template>
