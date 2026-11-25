<script setup>
import {router, useForm} from '@inertiajs/vue3';
import {nextTick, ref, watch} from 'vue';
import axios from 'axios';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {date} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});

// La búsqueda vuelve a la primera página y la URL conserva el filtro al navegar por el listado.
const search = ref(props.data.userSearch);
watch(() => props.data.userSearch, valor => search.value = valor);

// El formulario permite incorporar una cuenta nueva o cambiar el rol de una cuenta registrada.
const abierto = ref(false);
const tituloEquipo = ref(null);
const equipo = useForm({modo: 'nueva', email: '', name: '', rol: ''});
const roles = [
    {value: 'administrador', label: 'Administrador de la organización'},
    {value: 'supervisor', label: 'Supervisor'},
    {value: 'profesional', label: 'Profesional'},
];
const modos = [{value: 'existente', label: 'Cuenta existente'}, {value: 'nueva', label: 'Crear una cuenta'}];

async function abrirEquipo(user = null) {
    equipo.resetAndClearErrors();
    if (user) equipo.modo = 'existente';
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
    equipo.post('/admin/usuarios/equipo', {preserveScroll: true, onSuccess: cerrarEquipo});
}

// El enlace solo llega al navegador del administrador cuando elige compartirlo.
const enlace = ref('');
const preparandoEnlace = ref(false);
const campoEnlace = ref(null);
const contacto = ref('');
const mensajeEnlace = ref('');
const compartirNativo = typeof navigator !== 'undefined' && Boolean(navigator.share);

async function prepararEnlace(user) {
    preparandoEnlace.value = true;
    enlace.value = '';
    contacto.value = user.name;
    mensajeEnlace.value = '';
    try {
        const {data} = await axios.post('/admin/usuarios/' + user.id + '/activacion');
        enlace.value = data.enlace;
        await nextTick();
        campoEnlace.value?.focus();
    } catch (error) {
        mensajeEnlace.value = error.response?.status === 404
            ? 'La cuenta ya no está pendiente de activación. Actualizá el listado.'
            : 'No pudimos generar el enlace. Volvé a intentarlo.';
    } finally {
        preparandoEnlace.value = false;
    }
}

async function copiarEnlace() {
    try {
        await navigator.clipboard.writeText(enlace.value);
        mensajeEnlace.value = 'Enlace copiado.';
    } catch {
        campoEnlace.value?.focus();
        campoEnlace.value?.select();
        mensajeEnlace.value = 'El enlace quedó seleccionado para que lo copies.';
    }
}

async function compartirEnlace() {
    try {
        await navigator.share({title: 'Activá tu cuenta en synaux', url: enlace.value});
    } catch (error) {
        if (error.name !== 'AbortError') mensajeEnlace.value = 'Usá Copiar enlace para compartirlo.';
    }
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
                    <p v-if="equipo.modo === 'nueva'" class="detalle-activacion">
                        La persona elegirá su contraseña con un enlace de activación.
                    </p>
                </div>
                <div class="user-search-actions">
                    <B type="submit" :disabled="equipo.processing">{{ equipo.processing ? 'Guardando…' : 'Guardar usuario' }}</B>
                    <B variant="secondary" :disabled="equipo.processing" @click="cerrarEquipo">Cancelar</B>
                </div>
            </form>
        </v-card>

        <!-- Se puede copiar el enlace incluso si el navegador no ofrece el menú nativo de compartir. -->
        <v-card v-if="enlace" tag="section" class="card alta-equipo" aria-labelledby="titulo-enlace">
            <h2 id="titulo-enlace">Enlace para {{ contacto }}</h2>
            <p class="detalle-activacion">Compartilo por privado. Se puede usar una vez y vence en 48 horas. Si generás otro, el anterior queda invalidado.</p>
            <label for="enlace-activacion">Enlace de activación</label>
            <input id="enlace-activacion" ref="campoEnlace" class="enlace-activacion" type="text" :value="enlace" readonly/>
            <div class="user-search-actions">
                <B @click="copiarEnlace">Copiar enlace</B>
                <B v-if="compartirNativo" variant="secondary" @click="compartirEnlace">Compartir enlace</B>
                <B variant="ghost" @click="enlace = ''; mensajeEnlace = ''">Cerrar</B>
            </div>
        </v-card>
        <p v-if="mensajeEnlace" role="status">{{ mensajeEnlace }}</p>

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
                        <span v-if="user.activacion_pendiente" class="estado-activacion">Pendiente de activación</span>
                    </td>
                    <td>
                        <span class="user-role" :class="{ 'user-role-admin': user.is_admin }">
                            {{ user.is_admin ? 'Administrador de la plataforma' : roles.find(rol => rol.value === user.rol_organizacion)?.label ?? 'Cliente' }}
                        </span>
                    </td>
                    <td><time v-if="user.created_at" :datetime="user.created_at">{{ date(user.created_at) }}</time><span v-else>—</span></td>
                    <td><div class="user-search-actions">
                        <B :href="profileHref(user.id)" :aria-label="'Ver ficha de ' + user.name" compact variant="secondary">Ver ficha</B>
                        <B v-if="user.activacion_pendiente && user.rol_organizacion" compact variant="secondary"
                           :disabled="preparandoEnlace" :aria-label="'Compartir enlace de activación de ' + user.name"
                           @click="prepararEnlace(user)">Compartir</B>
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
.detalle-activacion { margin: 0 0 14px; color: #446075; }
.campos-equipo .detalle-activacion { grid-column: 1 / -1; }
.enlace-activacion { display: block; width: 100%; margin: 5px 0 14px; padding: 9px 12px; border: 1px solid #1f6f7f55; border-radius: 8px; }
.estado-activacion { display: block; margin-top: 4px; color: #1f6f7f; font-size: .875rem; font-weight: 700; }
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
