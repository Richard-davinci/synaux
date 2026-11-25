<script setup>
import B from '../../Components/UI/AppButton.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import {date} from '../../support/formatters';

defineProps({data: {type: Object, required: true}});
</script>

<template>
    <section class="page shell workspace-view user-profile" aria-label="Ficha de usuario">
        <B :href="data.userDirectoryUrl" variant="ghost">← Volver a Usuarios</B>
        <T eyebrow="Ficha de usuario" :title="data.selectedUser.name"/>

        <!-- La ficha identifica la cuenta y muestra el acceso vigente que se le asignó. -->
        <v-card class="card account-summary">
            <h2>Datos de la cuenta</h2>
            <dl class="data-list">
                <div>
                    <dt>Correo electrónico</dt>
                    <dd>{{ data.selectedUser.email }}</dd>
                </div>
                <div>
                    <dt>Rol</dt>
                    <dd>{{ data.selectedUser.is_admin ? 'Administrador' : 'Cliente' }}</dd>
                </div>
                <div>
                    <dt>Acceso</dt>
                    <dd>{{ data.selectedUser.is_admin ? 'Gestión y Administración' : 'Área cliente' }}</dd>
                </div>
                <div>
                    <dt>Fecha de alta</dt>
                    <dd>{{ data.selectedUser.created_at ? date(data.selectedUser.created_at) : 'Sin registrar' }}</dd>
                </div>
            </dl>
        </v-card>

        <!-- Cada enlace abre el detalle operativo del proyecto en el panel de Gestión. -->
        <section class="user-projects" aria-labelledby="user-projects-title">
            <div class="section-head">
                <div>
                    <h2 id="user-projects-title">Proyectos del cliente</h2>
                    <p>Abrí un proyecto en Gestión para consultar o actualizar su avance.</p>
                </div>
            </div>
            <div v-if="data.clientProjects.data.length" class="stack">
                <v-card v-for="project in data.clientProjects.data" :key="project.id" class="card list-row user-project">
                    <div>
                        <h3>{{ project.title }}</h3>
                        <div class="user-project-status">
                            <S :status="project.status"/>
                            <span>{{ project.progress }}% de avance</span>
                        </div>
                    </div>
                    <B :href="'/proyectos/' + project.id" variant="secondary" arrow>Abrir en Gestión</B>
                </v-card>
            </div>
            <p v-else class="user-projects-empty">Esta cuenta todavía no tiene proyectos asociados.</p>
            <nav v-if="data.clientProjects.prev_page_url || data.clientProjects.next_page_url"
                 class="user-project-pages" aria-label="Páginas de proyectos">
                <B v-if="data.clientProjects.prev_page_url" :href="data.clientProjects.prev_page_url" compact variant="secondary">Anterior</B>
                <B v-if="data.clientProjects.next_page_url" :href="data.clientProjects.next_page_url" compact variant="secondary">Siguiente</B>
            </nav>
        </section>
    </section>
</template>

<style scoped>
/* La ficha agrupa los datos de cuenta y mantiene los proyectos en una sección independiente. */
.user-profile > .btn {
    margin-bottom: 16px;
}

.account-summary h2,
.user-project h3 {
    margin-bottom: 10px;
    overflow-wrap: anywhere;
}

.account-summary dd {
    overflow-wrap: anywhere;
}

.user-projects {
    margin-top: 28px;
}

.user-project {
    gap: 12px;
}

.user-project-status,
.user-project-pages {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 12px;
    color: #446075;
}

.user-project-pages {
    justify-content: flex-end;
    margin-top: 16px;
}

.user-projects-empty {
    color: #446075;
}

@media (max-width: 540px) {
    .user-project {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
