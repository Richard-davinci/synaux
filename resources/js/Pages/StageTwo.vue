<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import B from '../Components/UI/AppButton.vue';
import I from '../Components/UI/AppIcon.vue';
import T from '../Components/UI/PageTitle.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';

// Las rutas operativas mantienen sus permisos por registro. La galería usa datos aislados.
const sections = [
    ['Oportunidades', 'Revisá el muro y las solicitudes públicas.', '/oportunidades', 'web'],
    ['Profesionales', 'Consultá el directorio y sus perfiles.', '/profesionales', 'user'],
    ['Área profesional', 'Revisá el perfil y las propuestas de tu propia cuenta.', '/profesional', 'layout'],
    ['Publicaciones', 'Consultá las publicaciones de tu cuenta.', '/cliente/publicaciones', 'file'],
    ['Propuestas e invitaciones', 'Revisá las propuestas recibidas por tu cuenta.', '/cliente/propuestas', 'message'],
];
</script>
<template>
    <Head title="Revisión de etapa 2" />
    <PublicLayout>
        <section class="page shell workspace-view">
            <B href="/admin/servicios" variant="ghost">← Volver a Administración</B>
            <T eyebrow="Administración" title="Revisión de etapa 2" description="Revisá el recorrido de oportunidades, propuestas y profesionales." />
            <div class="workspace-shortcuts">
                <v-card v-for="[title, description, href, icon] in sections" :key="href" class="card">
                    <I :name="icon" class="workspace-record-icon" />
                    <h2>{{ title }}</h2><p>{{ description }}</p><B compact :href="href" variant="secondary" arrow>Revisar</B>
                </v-card>
                <v-card class="card">
                    <I name="eye" class="workspace-record-icon" />
                    <h2>Pantallas de ejemplo</h2>
                    <p>Explorá los estados de la segunda etapa con datos de referencia.</p>
                    <B compact href="/prototipo/muro" variant="secondary" arrow>Ver pantallas</B>
                </v-card>
            </div>
        </section>
    </PublicLayout>
</template>
