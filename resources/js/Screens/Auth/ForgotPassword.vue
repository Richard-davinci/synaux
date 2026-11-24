<script setup>
import {useForm} from '@inertiajs/vue3';
import AuthFrame from '../../Components/Auth/AuthFrame.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import {useSubmit} from '../../support/useSubmit';

const props = defineProps({data: {type: Object, required: true}});

// La recuperación solo envía el correo y Laravel devuelve el aviso correspondiente.
const form = useForm({email: props.data.email ?? ''});
const submit = useSubmit();

function send() {
    submit(form, '/recuperar-acceso', {previewTarget: 'ingreso'});
}
</script>

<template>
    <AuthFrame eyebrow="Bienvenido de nuevo" title="Recuperá tu acceso"
               description="Ingresá tu correo para recibir las instrucciones de recuperación."
               :alternative="{ prompt: '¿Ya podés ingresar?', label: 'Ingresar', href: '/ingresar' }">
        <form @submit.prevent="send">
            <F v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" required
               :error="form.errors.email"/>
            <B type="submit" :disabled="form.processing">Enviar instrucciones</B>
        </form>
    </AuthFrame>
</template>
