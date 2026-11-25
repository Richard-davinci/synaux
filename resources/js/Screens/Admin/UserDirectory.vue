<script setup>
import {router} from '@inertiajs/vue3';
import {ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {date} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});

// La búsqueda vuelve a la primera página y la URL conserva el filtro al navegar por el listado.
const search = ref(props.data.userSearch);
function findUsers() {
    const term = search.value.trim();
    router.get('/admin/usuarios', term ? {buscar: term} : {});
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Usuarios" description="Consultá las cuentas registradas en synaux."/>

        <form class="filters workspace-filters workspace-filters-single user-search" role="search"
              aria-label="Buscar usuarios" @submit.prevent="findUsers">
            <F v-model="search" type="search" label="Buscar por nombre o correo" :maxlength="120"/>
            <div class="user-search-actions">
                <B type="submit">Buscar</B>
                <B v-if="data.userSearch" href="/admin/usuarios" variant="ghost">Limpiar búsqueda</B>
            </div>
        </form>

        <!-- La tabla prioriza la identidad de la cuenta y conserva los encabezados al desplazarse en móvil. -->
        <div class="table-scroll workspace-table users-table" role="region" aria-label="Usuarios registrados" tabindex="0">
            <table>
                <thead>
                <tr>
                    <th scope="col">Usuario</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Fecha de alta</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="user in data.users.data" :key="user.id">
                    <td>
                        {{ user.name }}
                        <span class="user-email">{{ user.email }}</span>
                    </td>
                    <td>
                        <span class="user-role" :class="{ 'user-role-admin': user.is_admin }">
                            {{ user.is_admin ? 'Administrador' : 'Cliente' }}
                        </span>
                    </td>
                    <td><time v-if="user.created_at" :datetime="user.created_at">{{ date(user.created_at) }}</time><span v-else>—</span></td>
                </tr>
                <tr v-if="!data.users.data.length">
                    <td colspan="3">{{ data.userSearch ? 'No encontramos usuarios con esa búsqueda.' : 'No hay usuarios para mostrar.' }}</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div v-if="data.users.data.length || data.users.prev_page_url" class="users-results">
            <p v-if="data.users.data.length">{{ data.users.from }}–{{ data.users.to }} de {{ data.users.total }} usuarios</p>
            <nav v-if="data.users.prev_page_url || data.users.next_page_url" aria-label="Páginas de usuarios">
                <B v-if="data.users.prev_page_url" :href="data.users.prev_page_url" compact variant="secondary">Anterior</B>
                <B v-if="data.users.next_page_url" :href="data.users.next_page_url" compact variant="secondary">Siguiente</B>
            </nav>
        </div>
    </section>
</template>

<style scoped>
/* La búsqueda y la paginación se adaptan a una línea nueva si falta ancho. */
.user-search-actions,
.users-results,
.users-results nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.users-results {
    justify-content: space-between;
    margin-top: 16px;
    color: #446075;
}

/* El correo puede cortarse en varias líneas y los roles tienen nombre visible además del color. */
.users-table table {
    min-width: 440px;
}

.users-table:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.user-email {
    display: block;
    color: #446075;
    font-weight: 400;
}

.user-role {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f5f9fa;
    color: #446075;
    font-weight: 700;
}

.user-role-admin {
    background: #2db3a31a;
    color: #153b64;
}

@media (max-width: 560px) {
    .workspace-view .user-search {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
