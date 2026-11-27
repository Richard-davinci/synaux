<script setup>
import {computed, ref} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import PageTitle from '../Components/UI/PageTitle.vue';
import F from '../Components/UI/FormField.vue';
import TarjetaPerfil from '../Components/Cuenta/TarjetaPerfil.vue';
import '../../css/panel.css';

// El formulario comparte los datos del emisor entre las cuentas de Administración.
const props = defineProps({organizacion: Object, equipo: Array});
const valores = () => ({
    name: props.organizacion.razon_social ?? '',
    cuit: props.organizacion.cuit ?? '',
    email: props.organizacion.correo ?? '',
    phone: props.organizacion.telefono ?? '',
    address: props.organizacion.domicilio ?? '',
});
const form = useForm(valores());
const editando = ref(false);
const datos = computed(() => [
    ['Nombre legal o razón social', props.organizacion.razon_social],
    ['CUIT', props.organizacion.cuit],
    ['Correo comercial', props.organizacion.correo],
    ['Teléfono comercial', props.organizacion.telefono],
    ['Domicilio', props.organizacion.domicilio],
].filter(([, valor]) => valor));
// El dominio del equipo tiene su propio guardado, separado de los datos fiscales.
const dominio = useForm({dominio_correo_equipo: props.organizacion.dominio_correo_equipo ?? ''});
const editandoDominio = ref(false);
function editarDominio(estado) {
    dominio.defaults('dominio_correo_equipo', props.organizacion.dominio_correo_equipo ?? '').resetAndClearErrors();
    editandoDominio.value = estado;
}
function guardarDominio() {
    dominio.patch('/admin/organizacion/dominio-equipo', {preserveScroll: true, onSuccess: () => editarDominio(false)});
}

const roles = {administrador: 'Administrador', supervisor: 'Supervisor', profesional: 'Profesional interno'};

function cambiarEdicion(estado) {
    form.defaults(valores()).resetAndClearErrors();
    editando.value = estado;
}

function guardar() {
    form.patch('/admin/organizacion', {preserveScroll: true, onSuccess: () => cambiarEdicion(false)});
}
</script>

<template>
    <Head title="Datos de la organización"/>
    <PublicLayout>
        <section class="page workspace-view organization-settings-view">
            <div class="organization-settings-content">
                <PageTitle eyebrow="Administración" :title="organizacion.nombre"
                           :description="organizacion.tipo === 'empresa' ? 'Empresa' : 'Profesional independiente'"/>
                <div class="ficha-organizacion">
                <TarjetaPerfil id="emision" class="organizacion-ficha-card" titulo="Datos de emisión" icono="file" :datos="datos"
                               vacio="Agregá los datos que se usarán en los próximos presupuestos."
                               accion-guardar="Guardar datos de emisión" :editando="editando"
                               :guardando="form.processing" :modificado="form.isDirty"
                               @editar="cambiarEdicion(true)" @cancelar="cambiarEdicion(false)" @guardar="guardar">
                    <F v-model="form.name" label="Nombre legal o razón social" maxlength="255" required :error="form.errors.name"/>
                    <F v-model="form.cuit" label="CUIT (opcional)" maxlength="13" hint="11 dígitos, con o sin guiones." :error="form.errors.cuit"/>
                    <F v-model="form.email" label="Correo comercial" type="email" maxlength="254" required :error="form.errors.email"/>
                    <F v-model="form.phone" label="Teléfono comercial (opcional)" type="tel" maxlength="50" :error="form.errors.phone"/>
                    <F v-model="form.address" label="Domicilio (opcional)" maxlength="500" :error="form.errors.address"/>

                </TarjetaPerfil>
                <TarjetaPerfil id="correo-equipo" class="organizacion-ficha-card" titulo="Correo del equipo" icono="user"
                               :datos="organizacion.dominio_correo_equipo ? [['Dominio', organizacion.dominio_correo_equipo]] : []"
                               vacio="Configurá el dominio que usarán las nuevas cuentas del equipo."
                               accion-guardar="Guardar dominio" :editando="editandoDominio" :guardando="dominio.processing" :modificado="dominio.isDirty"
                               @editar="editarDominio(true)" @cancelar="editarDominio(false)" @guardar="guardarDominio">
                    <F v-model="dominio.dominio_correo_equipo" label="Dominio corporativo" maxlength="253" required
                       placeholder="empresa.com" hint="Ingresá el dominio a la derecha de @." :error="dominio.errors.dominio_correo_equipo"/>
                </TarjetaPerfil>
                <v-card v-if="equipo.length" tag="section" class="card equipo-organizacion organizacion-ficha-card" aria-labelledby="titulo-equipo">
                    <h2 id="titulo-equipo">Equipo</h2>
                    <ul>
                        <li v-for="miembro in equipo" :key="miembro.id">
                            <strong>{{ miembro.nombre }}</strong><span>{{ roles[miembro.rol] }}</span>
                        </li>
                    </ul>
                </v-card>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* El fondo suave hace que las tarjetas blancas y sus secciones se distingan del resto de Administración. */
.organization-settings-view {
    width: 100%;
    max-width: none;
    min-height: calc(100vh - var(--navbar-height, 76px));
    margin: 0;
    padding: clamp(28px, 4vw, 48px) clamp(20px, 3.5vw, 56px) 64px;
    background: #f5f9fa;
}

.organization-settings-content {
    width: min(1180px, 100%);
    margin-inline: auto;
}

/* La ficha y el equipo se consultan en una columna y conservan el acento turquesa de Administración. */
.ficha-organizacion { display: grid; gap: 18px; }
.ficha-organizacion :deep(.organizacion-ficha-card) {
    border-top: 3px solid #2db3a3;
}
.ficha-organizacion :deep(.tarjeta-perfil-encabezado) {
    padding: 12px 14px;
    border-left: 3px solid #1f6f7f;
    border-radius: 9px;
    background: #e8f3f4;
}
.ficha-organizacion :deep(.tarjeta-perfil-datos > div) {
    padding: 10px 12px;
    border: 1px solid #1f6f7f1a;
    border-radius: 8px;
    background: #f5f9fa;
}
.ficha-organizacion :deep(.tarjeta-perfil-datos dt) { color: #446075; }
.ficha-organizacion :deep(.tarjeta-perfil-datos dd) { color: #153b64; }
.equipo-organizacion { padding: 20px; }
.equipo-organizacion h2 {
    margin: 0 0 12px;
    padding: 12px 14px;
    border-left: 3px solid #1f6f7f;
    border-radius: 9px;
    background: #e8f3f4;
    font-size: 1.2rem;
}
.equipo-organizacion ul { margin: 0; padding: 0; list-style: none; }
.equipo-organizacion li {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid #1f6f7f26;
}
.equipo-organizacion li:nth-child(even) { background: #f5f9fa; }

@media (max-width: 760px) {
    .organization-settings-view { padding-inline: 16px; }
}
</style>
