<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { VMenu, VList, VListItem } from 'vuetify/components';

// El menú comparte las acciones de cuenta en escritorio y móvil con navegación de teclado de Vuetify.
const page = usePage();
const open = ref(false);
const signingOut = ref(false);
function logout() {
    signingOut.value = true;
    router.post('/salir', {}, { onFinish: () => { signingOut.value = false; } });
}
</script>
<template>
    <VMenu v-model="open" location="bottom end">
        <template #activator="{ props }">
            <v-btn v-bind="props" variant="outlined" class="btn" aria-label="Mi cuenta">Mi cuenta ▾</v-btn>
        </template>
        <VList aria-label="Opciones de mi cuenta">
            <VListItem :title="page.props.auth.name" disabled />
            <VListItem :href="page.props.auth.is_admin ? '/gestion' : '/cliente'" title="Mi cuenta" />
            <VListItem href="/mi-perfil" title="Mi perfil" />
            <template v-if="page.props.auth.is_admin">
                <VListItem href="/gestion" title="Gestión" />
                <VListItem href="/admin/servicios" title="Administración" />
            </template>

            <VListItem title="Cerrar sesión" :disabled="signingOut" @click="logout" />
        </VList>
    </VMenu>
</template>
