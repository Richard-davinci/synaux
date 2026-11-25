<script setup>
import {router, useForm} from '@inertiajs/vue3';
import {nextTick, ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {date} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});

// La búsqueda vuelve a la primera página y la URL conserva el filtro al navegar por el listado.
const search = ref(props.data.userSearch);

// El formulario permite incorporar una cuenta nueva o cambiar el rol de una cuenta registrada.
const abierto = ref(false);
const tituloEquipo = ref(null);
const equipo = useForm({modo: 'existente', email: '', name: '', password: '', rol: ''});
const roles = [
    {value: 'administrador', label: 'Administrador de la organización'},
    {value: 'supervisor', label: 'Supervisor'},
    {value: 'profesional', label: 'Profesional'},
];
const modos = [{value: 'existente', label: 'Cuenta existente'}, {value: 'nueva', label: 'Crear una cuenta'}];

async function abrirEquipo(user = null) {
    equipo.resetAndClearErrors();
    equipo.email = user?.email ?? '';
    equipo.rol = user?.rol_organizacion ?? '';
    abierto.value = true;
    await nextTick();
    tituloEquipo.value?.focus();
}

function cerrarEquipo() {
    equipo.resetAndClearErrors();
    abierto.value = false;
}

function guardarEquipo() {
    if (equipo.modo === 'existente') equipo.reset('password');
    equipo.post('/admin/usuarios/equipo', {preserveScroll: true, onSuccess: cerrarEquipo});
}

// La ficha conserva el texto y la página del listado para poder regresar al mismo resultado.
function profileHref(id) {
    const filters = new URLSearchParams();
    if (props.data.userSearch) filters.set('buscar', props.data.userSearch);
    if (props.data.users.current_page > 1) filters.set('pagina_usuarios', props.data.users.current_page);
    return '/admin/usuarios/' + id + (filters.size ? '?' + filters.toString() : '');
}

function findUsers() {
    const term = search.value.trim();
    router.get('/admin/usuarios', term ? {buscar: term} : {});
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Usuarios" description="Consultá las cuentas y administrá el equipo de synaux.">
            <B v-if="!abierto" @click="abrirEquipo()">Agregar usuario al equipo</B>
        </T>

        <!-- El alta y los cambios de rol se guardan en la organización de synaux. -->
        <v-card v-if="abierto" tag="section" class="card alta-equipo" aria-labelledby="titulo-equipo">
            <h2 id="titulo-equipo" ref="tituloEquipo" tabindex="-1">Usuario del equipo</h2>
            <form @submit.prevent="guardarEquipo">
                <div class="campos-equipo">
                    <F v-model="equipo.modo" label="Tipo de cuenta" type="select" :options="modos" required :error="equipo.errors.modo"/>
                    <F v-model="equipo.rol" label="Rol en synaux" type="select" :options="roles" required :error="equipo.errors.rol"/>
                    <F v-if="equipo.modo === 'nueva'" v-model="equipo.name" label="Nombre y apellido" maxlength="255" required :error="equipo.errors.name"/>
                    <F v-model="equipo.email" label="Correo de la cuenta" type="email" maxlength="254" required :error="equipo.errors.email"/>
                    <F v-if="equipo.modo === 'nueva'" v-model="equipo.password" label="Contraseña inicial" type="password"
                       autocomplete="new-password" minlength="12" maxlength="72" required :error="equipo.errors.password"/>
                </div>
                <div class="user-search-actions">
                    <B type="submit" :disabled="equipo.processing">{{ equipo.processing ? 'Guardando…' : 'Guardar usuario' }}</B>
                    <B variant="secondary" :disabled="equipo.processing" @click="cerrarEquipo">Cancelar</B>
                </div>
            </form>
        </v-card>

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
                    <th scope="col">Acción</th>
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
                            {{ user.is_admin ? 'Administrador de la plataforma' : roles.find(rol => rol.value === user.rol_organizacion)?.label ?? 'Cliente' }}
                        </span>
                    </td>
                    <td><time v-if="user.created_at" :datetime="user.created_at">{{ date(user.created_at) }}</time><span v-else>—</span></td>
                    <td><div class="user-search-actions">
                        <B :href="profileHref(user.id)" :aria-label="'Ver ficha de ' + user.name" compact variant="secondary">Ver ficha</B>
                        <B v-if="!user.is_admin" compact variant="ghost" @click="abrirEquipo(user)">{{ user.rol_organizacion ? 'Cambiar rol' : 'Agregar al equipo' }}</B>
                    </div></td>
                </tr>
                <tr v-if="!data.users.data.length">
                    <td colspan="4">{{ data.userSearch ? 'No encontramos usuarios con esa búsqueda.' : 'No hay usuarios para mostrar.' }}</td>
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
/* La ficha de equipo comparte la grilla de los formularios del panel. */
.alta-equipo { padding: 20px; margin-bottom: 20px; }
.alta-equipo h2 { margin: 0 0 16px; }
.campos-equipo { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); gap: 0 16px; }

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
    min-width: 520px;
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
