<script setup>
import {router, useForm} from '@inertiajs/vue3';
import {nextTick, ref, watch} from 'vue';
import axios from 'axios';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import AccionIcono from '../../Components/UI/AccionIcono.vue';
import {date} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});

// La búsqueda vuelve a la primera página y la URL conserva el filtro al navegar por el listado.
const search = ref(props.data.userSearch);
watch(() => props.data.userSearch, valor => search.value = valor);

// El alta y el cambio de rol usan paneles independientes y envían datos distintos.
const esEquipo = props.data.section === 'usuarios';
const rutaListado = esEquipo ? '/admin/usuarios' : '/gestion/clientes';
const altaAbierta = ref(false);
const rolAbierto = ref(false);
const miembro = ref(null);
const confirmarBaja = ref(false);
const equipo = useForm({email: '', name: '', rol: ''});
const cambio = useForm({rol: ''});
const baja = useForm({});
const roles = [
    {value: 'administrador', label: 'Administrador de la organización'},
    {value: 'supervisor', label: 'Supervisor'},
    {value: 'profesional', label: 'Profesional'},
];
function editarRol(user) {
    miembro.value = user;
    cambio.resetAndClearErrors();
    cambio.rol = user.rol_organizacion;
    confirmarBaja.value = false;
    rolAbierto.value = true;
}

function guardarEquipo() {
    equipo.post('/admin/usuarios/equipo', {preserveScroll: true, onSuccess: () => {
        equipo.resetAndClearErrors();
        altaAbierta.value = false;
    }});
}

function guardarRol() {
    cambio.patch('/admin/usuarios/' + miembro.value.id + '/rol', {
        preserveScroll: true, onSuccess: () => rolAbierto.value = false,
    });
}

function quitarDelEquipo() {
    baja.delete('/admin/usuarios/' + miembro.value.id + '/equipo', {
        preserveScroll: true, onSuccess: () => {
            enlace.value = '';
            mensajeEnlace.value = '';
            rolAbierto.value = false;
        },
    });
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

// En HTTP local, se usa el campo de solo lectura para copiar dentro del clic del usuario.
async function copiarEnlace(evento) {
    const texto = enlace.value;
    const boton = evento.currentTarget;
    if (!texto) return;

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(texto);
            if (enlace.value === texto) mensajeEnlace.value = 'Enlace copiado.';
            return;
        } catch {
            // Si el navegador deniega el acceso, se intenta copiar desde la selección.
        }
    }

    const campo = campoEnlace.value;
    if (!campo || campo.value !== texto) return;
    campo.focus();
    campo.select();

    try {
        if (document.execCommand?.('copy')) {
            mensajeEnlace.value = 'Enlace copiado.';
            boton?.focus();
            return;
        }
    } catch {
        // Si se bloquean ambos métodos, la selección queda lista para copiar manualmente.
    }
    mensajeEnlace.value = 'El enlace quedó seleccionado. Copialo con Ctrl+C o desde el menú Copiar.';
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
    return '/gestion/clientes/' + id + (filters.size ? '?' + filters.toString() : '');
}

function findUsers() {
    const term = search.value.trim();
    router.get(rutaListado, term ? {buscar: term} : {});
}
</script>

<template>
    <section class="page shell workspace-view">
        <T :eyebrow="esEquipo ? 'Administración' : 'Gestión'" :title="esEquipo ? 'Equipo' : 'Clientes'"
           :description="esEquipo ? 'Administrá las cuentas y los roles del equipo de synaux.' : 'Consultá las cuentas vinculadas a la cartera de synaux.'">
            <SidePanel v-if="esEquipo" v-model="altaAbierta" title="Agregar miembro" :persistent="equipo.processing">
                <template #activator="{props: activador}">
                    <B v-bind="activador" @click="equipo.resetAndClearErrors()">Agregar miembro</B>
                </template>
                <form class="formulario-equipo" @submit.prevent="guardarEquipo">
                    <template v-if="data.dominioEquipo">
                        <F v-model="equipo.name" label="Nombre y apellido" maxlength="255" required :error="equipo.errors.name"/>
                        <F v-model="equipo.email" label="Correo de la empresa" type="email" maxlength="254" required
                           :hint="'El correo debe terminar en @' + data.dominioEquipo + '.'" :error="equipo.errors.email"/>
                        <F v-model="equipo.rol" label="Rol en synaux" type="select" :options="roles" required :error="equipo.errors.rol"/>
                        <p class="detalle-activacion">La persona elegirá su contraseña con un enlace de activación.</p>
                        <div class="user-search-actions">
                            <B type="submit" :disabled="equipo.processing">{{ equipo.processing ? 'Guardando…' : 'Crear cuenta' }}</B>
                            <B variant="secondary" :disabled="equipo.processing" @click="altaAbierta = false">Cancelar</B>
                        </div>
                    </template>
                    <template v-else>
                        <p class="detalle-activacion">Configurá el dominio de correo del equipo para poder crear cuentas.</p>
                        <B href="/admin/organizacion#correo-equipo">Configurar correo del equipo</B>
                    </template>
                </form>
            </SidePanel>
        </T>

        <!-- El perfil elegido identifica a la cuenta. Solo el rol se envía al guardar. -->
        <SidePanel v-if="esEquipo" v-model="rolAbierto" title="Cambiar rol" :persistent="cambio.processing || baja.processing">
            <div v-if="miembro" class="formulario-equipo">
                <h2>{{ miembro.name }}</h2>
                <p class="detalle-activacion">{{ miembro.email }}</p>
                <form @submit.prevent="guardarRol">
                    <fieldset class="roles-equipo">
                        <legend>Rol en synaux</legend>
                        <label v-for="rol in roles" :key="rol.value">
                            <input v-model="cambio.rol" type="radio" name="rol-equipo" :value="rol.value" required/>
                            <span>{{ rol.label }}</span>
                        </label>
                    </fieldset>
                    <p v-if="cambio.errors.rol" class="error" role="alert">{{ cambio.errors.rol }}</p>
                    <div class="user-search-actions">
                        <B type="submit" :disabled="cambio.processing || baja.processing || cambio.rol === miembro.rol_organizacion">Guardar rol</B>
                        <B variant="secondary" :disabled="cambio.processing || baja.processing" @click="rolAbierto = false">Cancelar</B>
                    </div>
                </form>
                <div class="baja-equipo">
                    <B v-if="!confirmarBaja" variant="ghost" :disabled="cambio.processing" @click="confirmarBaja = true">Quitar del equipo</B>
                    <template v-else>
                        <p class="detalle-activacion">Se quitará el rol de esta cuenta y se revocará su enlace de activación pendiente.</p>
                        <div class="user-search-actions">
                            <B variant="secondary" :disabled="baja.processing" @click="quitarDelEquipo">Confirmar baja del equipo</B>
                            <B variant="ghost" :disabled="baja.processing" @click="confirmarBaja = false">Volver</B>
                        </div>
                    </template>
                </div>
            </div>
        </SidePanel>

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
                <B v-if="data.userSearch" :href="rutaListado" variant="ghost">Limpiar búsqueda</B>
            </div>
        </form>

        <!-- La tabla prioriza la identidad de la cuenta y conserva los encabezados al desplazarse en móvil. -->
        <div class="table-scroll workspace-table users-table" role="region" :aria-label="esEquipo ? 'Miembros del equipo' : 'Clientes vinculados'" tabindex="0">
            <table>
                <thead>
                <tr>
                    <th scope="col">Usuario</th>
                    <th v-if="esEquipo" scope="col">Rol</th>
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
                    <td v-if="esEquipo">
                        <span class="user-role" :class="{ 'user-role-admin': user.is_admin }">
                            {{ user.is_admin ? 'Administrador de la plataforma' : roles.find(rol => rol.value === user.rol_organizacion)?.label }}
                        </span>
                    </td>
                    <td><time v-if="user.created_at" :datetime="user.created_at">{{ date(user.created_at) }}</time><span v-else>—</span></td>
                    <td><div class="user-search-actions">
                        <AccionIcono v-if="!esEquipo" icono="eye" texto="Ver ficha" :href="profileHref(user.id)"
                                     :etiqueta="'Ver ficha de ' + user.name"/>
                        <AccionIcono v-if="user.activacion_pendiente && user.rol_organizacion" icono="share"
                                     texto="Compartir enlace de activación" :deshabilitada="preparandoEnlace"
                                     :etiqueta="'Compartir enlace de activación de ' + user.name" @click="prepararEnlace(user)"/>
                        <AccionIcono v-if="esEquipo && !user.is_admin" icono="edit" texto="Cambiar rol"
                                     :etiqueta="'Cambiar rol de ' + user.name" @click="editarRol(user)"/>
                    </div></td>
                </tr>
                <tr v-if="!data.users.data.length">
                    <td :colspan="esEquipo ? 4 : 3">{{ data.userSearch ? 'No encontramos resultados con esa búsqueda.' : esEquipo ? 'Todavía no hay miembros en el equipo.' : 'Todavía no hay clientes vinculados.' }}</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div v-if="data.users.data.length || data.users.prev_page_url" class="users-results">
            <p v-if="data.users.data.length">{{ data.users.from }}–{{ data.users.to }} de {{ data.users.total }} {{ esEquipo ? 'miembros' : 'clientes' }}</p>
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
.enlace-activacion { display: block; width: 100%; margin: 5px 0 14px; padding: 9px 12px; border: 1px solid #1f6f7f55; border-radius: 8px; }
.estado-activacion { display: block; margin-top: 4px; color: #1f6f7f; font-size: .875rem; font-weight: 700; }
.formulario-equipo { padding: 24px; }
.formulario-equipo h2 { margin: 0 0 4px; font-size: 1.2rem; }
/* Cada rol ocupa una fila pulsable. El radio mantiene su medida frente a los estilos generales de inputs. */
.roles-equipo {
    display: grid;
    gap: 4px;
    min-inline-size: 0;
    margin: 0 0 18px;
    padding: 8px;
    border: 1px solid #1f6f7f33;
    border-radius: 8px;
}

.roles-equipo legend {
    padding-inline: 6px;
    font-weight: 700;
}

.roles-equipo label {
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    min-height: 44px;
    padding: 8px 12px;
    border: 1px solid transparent;
    border-radius: 6px;
    cursor: pointer;
}

.roles-equipo label:hover {
    background: #f5f9fa;
}

.roles-equipo label:has(input:checked) {
    background: #2db3a31a;
    border-color: #1f6f7f44;
}

.roles-equipo label:has(input:focus-visible) {
    outline: 2px solid #1f6f7f;
    outline-offset: 1px;
}

.roles-equipo input[type='radio'] {
    width: 18px;
    height: 18px;
    min-height: 0;
    padding: 0;
    margin: 0;
    accent-color: #1f6f7f;
    cursor: pointer;
}

.roles-equipo span {
    overflow-wrap: anywhere;
}
.baja-equipo { margin-top: 24px; padding-top: 16px; border-top: 1px solid #1f6f7f22; }

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
