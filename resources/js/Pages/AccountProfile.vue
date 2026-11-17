<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';

// El formulario edita la cuenta actual y pide confirmar la identidad cuando cambia el correo.
const props = defineProps({ profile: Object });
const form = useForm({ name: props.profile.name, email: props.profile.email, current_password: '' });
const emailChanged = computed(() => form.email.trim().toLowerCase() !== props.profile.email);
function save() {
    form.patch('/mi-perfil', {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({ name: form.name, email: form.email, current_password: '' });
            form.reset('current_password');
        },
        onFinish: () => form.reset('current_password'),
    });
}
</script>
<template>
    <Head title="Mi perfil" />
    <PublicLayout>
        <section class="page shell narrow">
            <h1>Mi perfil</h1>
            <p>Actualizá tus datos personales y el correo que usás para ingresar.</p>
            <v-card class="card">
                <form @submit.prevent="save">
                    <F v-model="form.name" label="Nombre" autocomplete="name" maxlength="255" required :error="form.errors.name" />
                    <F v-model="form.email" label="Correo electrónico" type="email" autocomplete="email" maxlength="254" required :error="form.errors.email" />
                    <F v-if="emailChanged" v-model="form.current_password" label="Contraseña actual" type="password" autocomplete="current-password" hint="Confirmá tu contraseña para cambiar el correo de acceso." required :error="form.errors.current_password" />
                    <div class="form-actions"><B href="/cliente" variant="secondary">Volver a mi cuenta</B><B type="submit" :disabled="form.processing">Guardar cambios</B></div>
                </form>
            </v-card>
        </section>
    </PublicLayout>
</template>
