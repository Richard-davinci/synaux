<script setup>
import {computed, ref, watch} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import S from '../../Components/UI/StatusBadge.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import {useSubmit} from '../../support/useSubmit';
import {slugify} from './catalogSlug';

const props = defineProps({data: {type: Object, required: true}});
const submit = useSubmit();

// El mismo listado consulta servicios o categorías según la sección autorizada.
const catalog = computed(() => props.data.administration);
const categoriesView = computed(() => props.data.section === 'categorias');
const search = ref('');
const categoryFilter = ref('');
const stateFilter = ref('');
const categoryOptions = computed(() => catalog.value.categories.map(category => ({
    value: category.id,
    label: category.name + (category.active ? '' : ' (inactiva)')
})));
const categoryName = id => catalog.value.categories.find(category => category.id === Number(id))?.name ?? 'Sin categoría';
const rows = computed(() => (categoriesView.value ? catalog.value.categories : catalog.value.services).filter(row =>
    row.name.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es'))
    && (!categoryFilter.value || categoriesView.value || Number(row.service_category_id) === Number(categoryFilter.value))
    && (stateFilter.value === '' || Boolean(row.active) === (stateFilter.value === 'active'))));

function clearFilters() {
    search.value = '';
    categoryFilter.value = '';
    stateFilter.value = '';
}

// El panel guarda la selección por orden y solo ofrece servicios visibles en el catálogo.
const featuredOpen = ref(false);
const featuredCategory = ref('');
const featuredTrigger = ref(null);
const featuredForm = useForm({service_ids: []});
const featuredLimit = computed(() => catalog.value.featuredServiceLimit ?? 3);
const activeCategoryIds = computed(() => new Set(catalog.value.categories.filter(row => row.active).map(row => Number(row.id))));
const featuredOptions = computed(() => catalog.value.services
    .filter(row => row.active && activeCategoryIds.value.has(Number(row.service_category_id)))
    .toSorted((a, b) => a.name.localeCompare(b.name, 'es')));
// Cambiar de categoría solo oculta opciones; mantiene la selección y su orden.
const featuredCategories = computed(() => {
    const availableIds = new Set(featuredOptions.value.map(service => Number(service.service_category_id)));
    return categoryOptions.value.filter(option => availableIds.has(Number(option.value)));
});
const visibleFeaturedOptions = computed(() => featuredOptions.value.filter(service =>
    !featuredCategory.value || Number(service.service_category_id) === Number(featuredCategory.value)));
const featuredIds = computed(() => (catalog.value.featuredServiceIds ?? []).map(Number));
const featuredNames = computed(() => featuredIds.value.map(id => catalog.value.services.find(row => Number(row.id) === id)?.name).filter(Boolean));
const featuredError = computed(() => featuredForm.errors.service_ids ?? Object.values(featuredForm.errors)[0]);

function openFeatured() {
    featuredForm.defaults({service_ids: [...featuredIds.value]});
    featuredForm.reset();
    featuredForm.clearErrors();
    featuredCategory.value = '';
    featuredOpen.value = true;
}

function saveFeatured() {
    submit(featuredForm, '/admin/guardar/servicios-destacados', {
        onSuccess: () => { featuredOpen.value = false; },
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
    category.reset();
    category.clearErrors();
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
        <!-- La selección guardada se puede verificar desde el listado de servicios. -->
        <section v-if="!categoriesView && $page.props.auth?.is_admin && !$page.props.preview"
                 class="featured-overview" aria-labelledby="featured-overview-title">
            <div>
                <h2 id="featured-overview-title">Destacados del Inicio</h2>
                <p v-if="featuredNames.length">{{ featuredNames.join(' · ') }}</p>
                <p v-else>Actualmente no hay servicios destacados.</p>
            </div>
            <B ref="featuredTrigger" compact variant="secondary" @click="openFeatured">Elegir destacados</B>
        </section>
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
                    <th>{{ categoriesView ? 'Categoría' : 'Servicio' }}</th>
                    <th>{{ categoriesView ? 'Servicios' : 'Categoría' }}</th>
                    <th v-if="!categoriesView">Modalidad</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="row in rows" :key="row.id">
                    <td>{{ row.name }}</td>
                    <td>{{
                            categoriesView ? catalog.services.filter(service => service.service_category_id === row.id).length : categoryName(row.service_category_id)
                        }}
                    </td>
                    <td v-if="!categoriesView">{{
                            row.estimation_mode === 'automatic' ? 'Automático' : 'Profesional'
                        }}
                    </td>
                    <td><S :status="row.active ? 'enabled' : 'disabled'"/></td>
                    <td>
                        <B v-if="categoriesView" compact variant="secondary" @click="editCategory(row)">Editar</B>
                        <B v-else compact variant="secondary"
                           :href="'/admin/servicios/' + row.id + '/editar'">Editar</B>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="categoriesView ? 4 : 5">No hay resultados para mostrar.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- El contador y los límites se aplican al conjunto, incluso si cambia el filtro de la tabla. -->
        <SidePanel v-model="featuredOpen" title="Servicios destacados" max-width="520"
                   :persistent="featuredForm.processing" @after-leave="featuredTrigger?.$el?.focus()">
            <form class="featured-form" @submit.prevent="saveFeatured">
                <p>Elegí hasta {{ featuredLimit }} servicios activos. Se muestran en el Inicio en el orden de selección.</p>
                <p class="featured-count" aria-live="polite">{{ featuredForm.service_ids.length }} de {{ featuredLimit }} seleccionados</p>
                <F v-if="featuredCategories.length" v-model="featuredCategory" label="Categoría" type="select"
                   :options="[{ value: '', label: 'Todas las categorías' }, ...featuredCategories]"
                   :disabled="featuredForm.processing"/>
                <fieldset v-if="visibleFeaturedOptions.length" class="featured-options">
                    <legend>Servicios disponibles</legend>
                    <label v-for="service in visibleFeaturedOptions" :key="service.id">
                        <input v-model="featuredForm.service_ids" type="checkbox" :value="Number(service.id)"
                               :disabled="featuredForm.processing || (featuredForm.service_ids.length >= featuredLimit && !featuredForm.service_ids.includes(Number(service.id)))">
                        <span>{{ service.name }}<small>{{ categoryName(service.service_category_id) }}</small></span>
                        <span v-if="featuredForm.service_ids.includes(Number(service.id))" class="featured-position">
                            {{ featuredForm.service_ids.indexOf(Number(service.id)) + 1 }}
                        </span>
                    </label>
                </fieldset>
                <p v-else>No hay servicios activos disponibles.</p>
                <p v-if="featuredError" role="alert">{{ featuredError }}</p>
                <div class="form-actions">
                    <B variant="secondary" :disabled="featuredForm.processing" @click="featuredOpen = false">Cancelar</B>
                    <B type="submit" :disabled="featuredForm.processing || !featuredForm.isDirty">
                        {{ featuredForm.processing ? 'Guardando…' : 'Guardar destacados' }}
                    </B>
                </div>
            </form>
        </SidePanel>

        <!-- El panel conserva los errores de validación mientras se edita la categoría. -->
        <SidePanel title="Categorías de servicios" v-model="categoryDialog" max-width="480"
                   :persistent="category.processing">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <h2>{{ category.id ? 'Editar categoría' : 'Nueva categoría' }}</h2>
                    <form @submit.prevent="saveCategory">
                        <F v-model="category.name" label="Nombre" required maxlength="255"
                           :error="category.errors.name"/>
                        <F :model-value="category.slug"
                           @update:model-value="category.slug = $event; categorySlugEdited = true"
                           label="Identificador (slug)" required :error="category.errors.slug"/>
                        <F v-model="category.description" label="Descripción" type="textarea" required maxlength="10000"
                           :error="category.errors.description"/>
                        <label class="select-row">
                            <input v-model="category.active" type="checkbox">Categoría activa
                        </label>
                        <p v-if="category.errors.active" role="alert">{{ category.errors.active }}</p>
                        <p>Desactivar la categoría conserva los servicios vinculados y no cambia su publicación.</p>
                        <div class="form-actions">
                            <B variant="secondary" :disabled="category.processing" @click="categoryDialog = false">Cancelar</B>
                            <B type="submit" :disabled="category.processing">{{
                                    category.processing ? 'Guardando…' : 'Guardar categoría'
                                }}</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>

<style scoped>
/* La selección se destaca dentro de Administración y mantiene controles nativos accesibles. */
.featured-overview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
    padding: 16px 20px;
    border: 1px solid #1f6f7f26;
    border-radius: 12px;
    background: #f5f9fa;
}

.featured-overview h2 {
    margin: 0 0 4px;
    font-size: 1.15rem;
}

.featured-overview p {
    margin: 0;
    overflow-wrap: anywhere;
}

.featured-form {
    padding: 24px;
}

.featured-count {
    margin-bottom: 12px;
    font-weight: 700;
}

.featured-options {
    padding: 0;
    border: 0;
    min-width: 0;
}

.featured-options legend {
    margin-bottom: 8px;
    font-weight: 700;
}

.featured-options label {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 48px;
    padding: 8px 0;
    border-bottom: 1px solid #1f6f7f26;
}

.featured-options label small {
    display: block;
    color: #446075;
}

.featured-options input {
    accent-color: #1f6f7f;
}

.featured-position {
    margin-inline-start: auto;
    color: #1f6f7f;
    font-weight: 700;
}
</style>
