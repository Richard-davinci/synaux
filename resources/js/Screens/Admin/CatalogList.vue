<script setup>
import {computed, ref, useId, watch} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import {demoNotice} from '../../support/prototype';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import S from '../../Components/UI/StatusBadge.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import AccionIcono from '../../Components/UI/AccionIcono.vue';
import ServiciosDestacados from '../../Components/Admin/ServiciosDestacados.vue';
import {useSubmit} from '../../support/useSubmit';
import {slugify} from './catalogSlug';

const props = defineProps({data: {type: Object, required: true}});
const submit = useSubmit();
const page = usePage();
const formularioCategoria = useId();

// El mismo listado consulta servicios o categorías según la sección autorizada.
const catalog = computed(() => props.data.administration);
const categoriesView = computed(() => props.data.section === 'categorias');
const search = ref('');
const categoryFilter = ref('');
const stateFilter = ref('');
const pagina = ref(1);
const porPagina = 10;
const columnaOrden = ref('nombre');
const descendente = ref(false);
const entidad = computed(() => categoriesView.value ? 'categoría' : 'servicio');
const categoryOptions = computed(() => catalog.value.categories.map(category => ({
    value: category.id,
    label: category.name + (category.active ? '' : ' (inactiva)')
})));
const categoryName = id => catalog.value.categories.find(category => category.id === Number(id))?.name ?? 'Sin categoría';
const rows = computed(() => (categoriesView.value ? catalog.value.categories : catalog.value.services).filter(row =>
    row.name.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es'))
    && (!categoryFilter.value || categoriesView.value || Number(row.service_category_id) === Number(categoryFilter.value))
    && (stateFilter.value === '' || Boolean(row.active) === (stateFilter.value === 'active'))));

// Ordena el resultado completo con los mismos valores que aparecen en cada columna.
const comparador = new Intl.Collator('es-AR', {sensitivity: 'base', numeric: true});
const cantidadServicios = row => conteos.value.get(Number(row.id)) ?? 0;
const modalidad = row => row.estimation_mode === 'automatic' ? 'Automático' : 'Profesional';
const columnas = computed(() => [
    {clave: 'nombre', titulo: categoriesView.value ? 'Categoría' : 'Servicio', valor: row => row.name},
    categoriesView.value
        ? {clave: 'cantidad', titulo: 'Servicios', valor: cantidadServicios}
        : {clave: 'categoria', titulo: 'Categoría', valor: row => categoryName(row.service_category_id)},
    ...(!categoriesView.value ? [{clave: 'modalidad', titulo: 'Modalidad', valor: modalidad}] : []),
    {clave: 'estado', titulo: 'Estado', valor: row => row.active ? 'Activo' : 'Inactivo'},
]);
const filasOrdenadas = computed(() => {
    const columna = columnas.value.find(item => item.clave === columnaOrden.value) ?? columnas.value[0];
    const sentido = descendente.value ? -1 : 1;
    return [...rows.value].sort((a, b) => sentido * comparador.compare(columna.valor(a), columna.valor(b))
        || comparador.compare(a.name, b.name) || a.id - b.id);
});

function ordenarPor(clave) {
    descendente.value = columnaOrden.value === clave && !descendente.value;
    columnaOrden.value = clave;
    pagina.value = 1;
}

// Pagina después de ordenar y ajusta el número de página si desaparece la última fila.
const totalPaginas = computed(() => Math.max(1, Math.ceil(rows.value.length / porPagina)));
const inicio = computed(() => (pagina.value - 1) * porPagina);
const filasVisibles = computed(() => filasOrdenadas.value.slice(inicio.value, inicio.value + porPagina));
watch([search, categoryFilter, stateFilter], () => { pagina.value = 1; });
watch(totalPaginas, total => { pagina.value = Math.min(pagina.value, total); });
watch(categoriesView, () => {
    columnaOrden.value = 'nombre';
    descendente.value = false;
    pagina.value = 1;
});

const conteos = computed(() => {
    const totales = new Map();
    for (const servicio of catalog.value.services) {
        const id = Number(servicio.service_category_id);
        totales.set(id, (totales.get(id) ?? 0) + 1);
    }
    return totales;
});

function clearFilters() {
    search.value = '';
    categoryFilter.value = '';
    stateFilter.value = '';
}

// La vista previa lee los datos guardados y ofrece el detalle público cuando está disponible.
const vistaPrevia = ref(null);
const serviciosVinculados = computed(() => catalog.value.services.filter(s => Number(s.service_category_id) === Number(vistaPrevia.value?.id)));
const publicado = computed(() => Boolean(vistaPrevia.value?.active && catalog.value.categories.some(c =>
    c.active && Number(c.id) === Number(vistaPrevia.value?.service_category_id))));

// La eliminación requiere confirmación y muestra los vínculos que detecta el servidor.
const registroAEliminar = ref(null);
const eliminacion = useForm({});
function confirmarEliminacion(row) {
    eliminacion.clearErrors();
    registroAEliminar.value = row;
}

function eliminarRegistro() {
    if (page.props.preview) {
        demoNotice.value = 'Vista de referencia: no se eliminan registros reales.';
        registroAEliminar.value = null;
        return;
    }
    const recurso = categoriesView.value ? 'categorias' : 'servicios';
    eliminacion.delete('/admin/catalogo/' + recurso + '/' + registroAEliminar.value.id, {
        preserveScroll: true,
        onSuccess: () => { registroAEliminar.value = null; },
    });
}

// Las categorías usan el guardado existente; desactivar conserva sus servicios y el historial asociado.
const categoryDialog = ref(false);
const category = useForm({id: null, name: '', slug: '', description: '', active: true});
const categorySlugEdited = ref(false);
watch(() => category.name, name => {
    if (!categorySlugEdited.value) category.slug = slugify(name);
});

function editCategory(row) {
    categorySlugEdited.value = Boolean(row);
    category.defaults({
        id: row?.id ?? null,
        name: row?.name ?? '',
        slug: row?.slug ?? '',
        description: row?.description ?? '',
        active: row ? Boolean(row.active) : true
    });
    category.resetAndClearErrors();
    categoryDialog.value = true;
}

function saveCategory() {
    submit(category, '/admin/guardar/categorias', {
        onSuccess: () => {
            categoryDialog.value = false;
        }
    });
}
</script>

<template>
    <section class="page shell catalog-admin workspace-view">
        <T eyebrow="Catálogo" :title="categoriesView ? 'Categorías de servicios' : 'Catálogo de servicios'"
           :description="categoriesView ? 'Organizá los servicios de synaux por especialidad.' : 'Administrá la publicación, las preguntas y los precios de cada servicio.'">
            <div class="button-row">
                <B v-if="!categoriesView && $page.props.auth?.is_admin" compact variant="secondary"
                   href="/admin/etapa-2">Revisar etapa 2</B>
                <B v-if="categoriesView" compact @click="editCategory()">Nueva categoría</B>
                <B v-else compact href="/admin/servicios/nuevo">Nuevo servicio</B>
            </div>
        </T>
        <ServiciosDestacados v-if="!categoriesView && $page.props.auth?.is_admin && !$page.props.preview"
                          :catalog="catalog"/>
        <div class="filters workspace-filters catalog-filters" :class="{ 'catalog-category-filters': categoriesView }">
            <F v-model="search" type="search" label="Buscar por nombre"/>
            <F v-if="!categoriesView" v-model="categoryFilter" label="Categoría" type="select"
               :options="[{ value: '', label: 'Todas' }, ...categoryOptions]"/>
            <F v-model="stateFilter" label="Estado" type="select"
               :options="[{ value: '', label: 'Todos' }, { value: 'active', label: 'Activos' }, { value: 'inactive', label: 'Inactivos' }]"/>
            <B compact variant="secondary" :disabled="!search && !categoryFilter && !stateFilter" @click="clearFilters">Limpiar
                filtros</B>
        </div>
        <div class="table-scroll workspace-table">
            <table>
                <thead>
                <tr>
                    <th v-for="columna in columnas" :key="columna.clave" scope="col"
                        :aria-sort="columnaOrden === columna.clave ? (descendente ? 'descending' : 'ascending') : undefined">
                        <button type="button" class="orden-catalogo" @click="ordenarPor(columna.clave)">
                            {{ columna.titulo }}
                            <span aria-hidden="true">{{ columnaOrden === columna.clave ? (descendente ? '↓' : '↑') : '↕' }}</span>
                        </button>
                    </th>
                    <th scope="col" class="celda-acciones-catalogo">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="row in filasVisibles" :key="row.id">
                    <td>{{ row.name }}</td>
                    <td>{{
                            categoriesView ? cantidadServicios(row) : categoryName(row.service_category_id)
                        }}
                    </td>
                    <td v-if="!categoriesView">{{ modalidad(row) }}</td>
                    <td><S :status="row.active ? 'enabled' : 'disabled'"/></td>
                    <td class="celda-acciones-catalogo">
                        <div class="acciones-catalogo">
                            <AccionIcono icono="eye" texto="Vista previa"
                                         :etiqueta="'Vista previa de ' + row.name" @click="vistaPrevia = row"/>
                            <AccionIcono v-if="categoriesView" icono="edit" texto="Editar categoría"
                                         :etiqueta="'Editar categoría ' + row.name" @click="editCategory(row)"/>
                            <AccionIcono v-else icono="edit" texto="Editar servicio" :etiqueta="'Editar servicio ' + row.name"
                                         :href="'/admin/servicios/' + row.id + '/editar'"/>
                            <AccionIcono icono="trash" :texto="'Eliminar ' + entidad"
                                         :etiqueta="'Eliminar ' + entidad + ' ' + row.name" @click="confirmarEliminacion(row)"/>
                        </div>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="categoriesView ? 4 : 5">No hay resultados para mostrar.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Los controles recorren la lista filtrada sin salir de la pantalla. -->
        <div v-if="rows.length" class="paginacion-catalogo">
            <p aria-live="polite">{{ inicio + 1 }}–{{ inicio + filasVisibles.length }} de {{ rows.length }} {{ categoriesView ? 'categorías' : 'servicios' }}</p>
            <nav v-if="totalPaginas > 1" aria-label="Páginas del catálogo">
                <B compact variant="secondary" :disabled="pagina <= 1" @click="pagina--">Anterior</B>
                <span>Página {{ pagina }} de {{ totalPaginas }}</span>
                <B compact variant="secondary" :disabled="pagina >= totalPaginas" @click="pagina++">Siguiente</B>
            </nav>
        </div>

        <!-- Los tres paneles usan el encabezado, el área desplazable y el pie de acciones de synaux. -->
        <SidePanel :title="category.id ? 'Editar categoría' : 'Nueva categoría'" v-model="categoryDialog"
                   :persistent="category.processing">
            <form :id="formularioCategoria" class="contenido-panel-catalogo" @submit.prevent="saveCategory">
                <F v-model="category.name" label="Nombre" required maxlength="255" :error="category.errors.name"/>
                <F :model-value="category.slug"
                   @update:model-value="category.slug = $event; categorySlugEdited = true"
                   label="Identificador (slug)" required maxlength="150" :error="category.errors.slug"/>
                <F v-model="category.description" label="Descripción" type="textarea" required maxlength="10000"
                   :error="category.errors.description"/>
                <fieldset class="estado-panel-catalogo">
                    <legend>Estado</legend>
                    <label class="select-row">
                        <input v-model="category.active" type="checkbox">Categoría activa
                    </label>
                    <p v-if="category.errors.active" class="error" role="alert">{{ category.errors.active }}</p>
                    <p>Desactivar la categoría oculta sus servicios del catálogo público.</p>
                </fieldset>
            </form>
            <template #footer>
                <B variant="secondary" :disabled="category.processing" @click="categoryDialog = false">Cancelar</B>
                <B type="submit" :form="formularioCategoria" :disabled="category.processing">{{ category.processing ? 'Guardando…' : 'Guardar categoría' }}</B>
            </template>
        </SidePanel>

        <SidePanel :title="'Vista previa de ' + entidad" :model-value="Boolean(vistaPrevia)"
                   @update:model-value="vistaPrevia = null">
            <div v-if="vistaPrevia" class="contenido-panel-catalogo">
                <div class="cabecera-panel-catalogo">
                    <S :status="vistaPrevia.active ? 'enabled' : 'disabled'"/>
                    <h2>{{ vistaPrevia.name }}</h2>
                    <p v-if="!categoriesView">{{ categoryName(vistaPrevia.service_category_id) }} · {{ vistaPrevia.estimation_mode === 'automatic' ? 'Estimación automática' : 'Presupuesto profesional' }}</p>
                    <p v-else>{{ serviciosVinculados.length }} {{ serviciosVinculados.length === 1 ? 'servicio vinculado' : 'servicios vinculados' }}</p>
                </div>
                <p v-if="!categoriesView && vistaPrevia.summary">{{ vistaPrevia.summary }}</p>
                <div>
                    <h3>{{ categoriesView ? 'Descripción' : 'Alcance' }}</h3>
                    <p class="descripcion-catalogo">{{ vistaPrevia.description }}</p>
                </div>
                <div v-if="!categoriesView && vistaPrevia.deliverables?.length">
                    <h3>Entregables</h3>
                    <ul><li v-for="(entregable, indice) in vistaPrevia.deliverables" :key="indice">{{ entregable }}</li></ul>
                </div>
                <div v-if="categoriesView && serviciosVinculados.length">
                    <h3>Servicios vinculados</h3>
                    <ul><li v-for="servicio in serviciosVinculados" :key="servicio.id">{{ servicio.name }}</li></ul>
                </div>
            </div>
            <template #footer>
                <B :variant="publicado && !categoriesView ? 'secondary' : 'primary'" @click="vistaPrevia = null">Cerrar</B>
                <B v-if="publicado && !categoriesView" :href="'/servicios/' + encodeURIComponent(vistaPrevia.slug)" arrow>Ver ficha pública</B>
            </template>
        </SidePanel>

        <SidePanel :title="'Eliminar ' + entidad" :model-value="Boolean(registroAEliminar)" max-width="480"
                   :persistent="eliminacion.processing" @update:model-value="registroAEliminar = null">
            <div v-if="registroAEliminar" class="contenido-panel-catalogo">
                <div class="cabecera-panel-catalogo"><h2>{{ registroAEliminar.name }}</h2></div>
                <p>El borrado es definitivo y solo está disponible para registros sin datos asociados.</p>
                <p v-if="eliminacion.errors.eliminar_catalogo" class="aviso-eliminacion" role="alert">{{ eliminacion.errors.eliminar_catalogo }}</p>
            </div>
            <template #footer>
                <B variant="secondary" :disabled="eliminacion.processing" @click="registroAEliminar = null">Cancelar</B>
                <B variant="danger" :disabled="eliminacion.processing" @click="eliminarRegistro">{{ eliminacion.processing ? 'Eliminando…' : 'Eliminar ' + entidad }}</B>
            </template>
        </SidePanel>
    </section>
</template>

<style scoped>
/* Los encabezados ordenables conservan el aspecto de la tabla y muestran el sentido activo. */
.orden-catalogo {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 32px;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.orden-catalogo:hover { text-decoration: underline; }
.orden-catalogo:focus-visible { outline: 2px solid #1f6f7f; outline-offset: 3px; }
.orden-catalogo span { color: #446075; }
th[aria-sort] .orden-catalogo, th[aria-sort] .orden-catalogo span { color: #153b64; }

/* La columna de acciones queda a la derecha aunque cambie el ancho de la tabla. */
.workspace-table .celda-acciones-catalogo { text-align: right; }
.acciones-catalogo { justify-content: flex-end; }

/* La navegación queda debajo de la tabla. */
.acciones-catalogo, .paginacion-catalogo, .paginacion-catalogo nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.paginacion-catalogo, .paginacion-catalogo nav {
    flex-wrap: wrap;
    gap: 12px;
}

.paginacion-catalogo {
    justify-content: space-between;
    margin-top: 16px;
    color: #446075;
    font-size: .875rem;
}

.paginacion-catalogo p { margin: 0; }

/* El cuerpo del panel comparte márgenes con su encabezado y agrupa los datos relacionados. */
.contenido-panel-catalogo {
    display: grid;
    gap: 16px;
    padding: 20px clamp(16px, 4vw, 24px) 24px;
    overflow-wrap: anywhere;
}

.contenido-panel-catalogo :deep(.field) { margin: 0; }

.contenido-panel-catalogo :is(h2, h3, p, ul) { margin: 0; }
.contenido-panel-catalogo h2 { font-size: 1.25rem; }
.contenido-panel-catalogo h3 { font-size: 1rem; margin-bottom: 6px; }
.contenido-panel-catalogo ul { padding-inline-start: 20px; }
.descripcion-catalogo { white-space: pre-line; }

.cabecera-panel-catalogo, .estado-panel-catalogo {
    padding: 12px 14px;
    border: 1px solid #1f6f7f26;
    border-radius: 10px;
    background: #f5f9fa;
}

.cabecera-panel-catalogo {
    display: grid;
    justify-items: start;
    gap: 6px;
}

.estado-panel-catalogo { margin: 0; min-width: 0; }
.estado-panel-catalogo legend { padding-inline: 5px; font-weight: 700; }
.estado-panel-catalogo .select-row { margin: 0 0 8px; cursor: pointer; }
.estado-panel-catalogo p { color: #446075; font-size: .875rem; }

.aviso-eliminacion {
    padding: 12px 14px;
    border-radius: 8px;
    color: var(--danger);
    background: var(--danger-bg);
}
</style>
