<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import B from '../Components/SynauxButton.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';

// Las rutas operativas mantienen sus permisos por registro. La galería usa datos aislados.
const sections = [
    ['Oportunidades', 'Revisá el muro y las solicitudes públicas.', '/oportunidades'],
    ['Profesionales', 'Consultá el directorio y sus perfiles.', '/profesionales'],
    ['Área profesional', 'Revisá el perfil y las propuestas de tu propia cuenta.', '/profesional'],
    ['Publicaciones', 'Consultá las publicaciones de tu cuenta.', '/cliente/publicaciones'],
    ['Propuestas e invitaciones', 'Revisá las propuestas recibidas por tu cuenta.', '/cliente/propuestas'],
];
</script>
<template>
    <Head title="Revisión de etapa 2" />
    <PublicLayout>
        <section class="page shell">
            <B href="/admin/servicios" variant="ghost">← Volver a Administración</B>
            <h1>Etapa 2</h1>
            <p>Acceso de revisión para administradores. Los clientes usan el recorrido privado de la etapa 1.</p>
            <div class="service-grid">
                <v-card v-for="[title, description, href] in sections" :key="href" class="card">
                    <h2>{{ title }}</h2><p>{{ description }}</p><B :href="href" variant="secondary" arrow>Revisar</B>
                </v-card>
                <v-card class="card">
                    <h2>Pantallas de ejemplo</h2>
                    <p>Explorá los estados de la segunda etapa con datos de referencia.</p>
                    <B href="/prototipo/muro" variant="secondary" arrow>Ver pantallas</B>
                </v-card>
            </div>
        </section>
    </PublicLayout>
</template>
