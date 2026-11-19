<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import S from '../../Components/UI/StatusBadge.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import { useSubmit } from '../../support/useSubmit';
import { slugify } from './catalogSlug';

const props = defineProps({ data: { type: Object, required: true } });
const submit = useSubmit();

// El mismo listado consulta servicios o categorías según la sección autorizada.
const catalog = computed(() => props.data.administration);
const categoriesView = computed(() => props.data.section === 'categorias');
const search = ref('');
const categoryFilter = ref('');
const stateFilter = ref('');
const categoryOptions = computed(() => catalog.value.categories.map(category => ({ value: category.id, label: category.name + (category.active ? '' : ' (inactiva)') })));
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

// Las categorías usan el guardado existente; desactivar conserva sus servicios y el historial asociado.
const categoryDialog = ref(false);
const category = useForm({ id: null, name: '', slug: '', description: '', active: true });
const categorySlugEdited = ref(false);
watch(() => category.name, name => { if (!categorySlugEdited.value) category.slug = slugify(name); });
function editCategory(row) {
    categorySlugEdited.value = Boolean(row);
    category.defaults({ id: row?.id ?? null, name: row?.name ?? '', slug: row?.slug ?? '', description: row?.description ?? '', active: row ? Boolean(row.active) : true });
    category.reset();
    category.clearErrors();
    categoryDialog.value = true;
}
function saveCategory() {
    submit(category, '/admin/guardar/categorias', { onSuccess: () => { categoryDialog.value = false; } });
}
</script>

<template>
    <section class="page shell catalog-admin workspace-view">
        <T eyebrow="Catálogo" :title="categoriesView ? 'Categorías de servicios' : 'Catálogo de servicios'"
            :description="categoriesView ? 'Organizá los servicios de synaux por especialidad.' : 'Administrá la publicación, las preguntas y los precios de cada servicio.'">
            <div class="button-row">
                <B v-if="!categoriesView && $page.props.auth?.is_admin" compact variant="secondary" href="/admin/etapa-2">Revisar etapa 2</B>
                <B v-if="categoriesView" compact @click="editCategory()">Nueva categoría</B>
                <B v-else compact href="/admin/servicios/nuevo">Nuevo servicio</B>
            </div>
        </T>
        <div class="filters workspace-filters catalog-filters" :class="{ 'catalog-category-filters': categoriesView }">
            <F v-model="search" type="search" label="Buscar por nombre" />
            <F v-if="!categoriesView" v-model="categoryFilter" label="Categoría" type="select"
                :options="[{ value: '', label: 'Todas' }, ...categoryOptions]" />
            <F v-model="stateFilter" label="Estado" type="select"
                :options="[{ value: '', label: 'Todos' }, { value: 'active', label: 'Activos' }, { value: 'inactive', label: 'Inactivos' }]" />
            <B compact variant="secondary" :disabled="!search && !categoryFilter && !stateFilter" @click="clearFilters">Limpiar filtros</B>
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
                        <td>{{ categoriesView ? catalog.services.filter(service => service.service_category_id === row.id).length : categoryName(row.service_category_id) }}</td>
                        <td v-if="!categoriesView">{{ row.estimation_mode === 'automatic' ? 'Automático' : 'Profesional' }}</td>
                        <td><S :status="row.active ? 'enabled' : 'disabled'" /></td>
                        <td>
                            <B v-if="categoriesView" compact variant="secondary" @click="editCategory(row)">Editar</B>
                            <B v-else compact variant="secondary" :href="'/admin/servicios/' + row.id + '/editar'">Editar</B>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td :colspan="categoriesView ? 4 : 5">No hay resultados para mostrar.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- El panel conserva los errores de validación mientras se edita la categoría. -->
        <SidePanel title="Categorías de servicios" v-model="categoryDialog" max-width="480" :persistent="category.processing">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <h2>{{ category.id ? 'Editar categoría' : 'Nueva categoría' }}</h2>
                    <form @submit.prevent="saveCategory">
                        <F v-model="category.name" label="Nombre" required maxlength="255" :error="category.errors.name" />
                        <F :model-value="category.slug" @update:model-value="category.slug = $event; categorySlugEdited = true"
                            label="Identificador (slug)" required :error="category.errors.slug" />
                        <F v-model="category.description" label="Descripción" type="textarea" required maxlength="10000"
                            :error="category.errors.description" />
                        <label class="select-row">
                            <input v-model="category.active" type="checkbox">Categoría activa
                        </label>
                        <p v-if="category.errors.active" role="alert">{{ category.errors.active }}</p>
                        <p>Desactivar la categoría conserva los servicios vinculados y no cambia su publicación.</p>
                        <div class="form-actions">
                            <B variant="secondary" :disabled="category.processing" @click="categoryDialog = false">Cancelar</B>
                            <B type="submit" :disabled="category.processing">{{ category.processing ? 'Guardando…' : 'Guardar categoría' }}</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>
