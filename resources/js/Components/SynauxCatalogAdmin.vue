<script setup>
import SidePanel from './SynauxSidePanel.vue';
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import B from './SynauxButton.vue';
import F from './SynauxField.vue';
import T from './SynauxTitle.vue';
import SynauxServiceConfiguration from './SynauxServiceConfiguration.vue';
import { useSubmit } from '../support/synaux';

// El catálogo concentra información, preguntas, reglas y publicación versionada de precios del servicio.
const props = defineProps({ data: Object });
const page = usePage();
const submit = useSubmit();
const catalog = computed(() => props.data.administration);
const categoriesView = computed(() => props.data.section === 'categorias');
const path = computed(() => page.url.split('?')[0]);
const editor = computed(() => /\/servicios\/(nuevo|\d+\/editar)$/.test(path.value) || Boolean(props.data.preview && props.data.catalogServiceId));
const configurationTab = ref(new URLSearchParams(page.url.split('?')[1] ?? '').get('config') ?? 'informacion');
const tabs = [['informacion','Información y alcance'], ['preguntas','Preguntas'], ['estimacion','Estimación'], ['precios','Precios vigentes']];
const selected = computed(() => catalog.value.services.find(service => service.id === Number(props.data.preview ? props.data.catalogServiceId : path.value.match(/\/servicios\/(\d+)\/editar$/)?.[1])));
const search = ref('');
const categoryFilter = ref('');
const stateFilter = ref('');
const categoryOptions = computed(() => catalog.value.categories.map(category => ({ value: category.id, label: category.name + (category.active ? '' : ' (inactiva)') })));
const categoryName = id => catalog.value.categories.find(category => category.id === Number(id))?.name ?? 'Sin categoría';
const rows = computed(() => (categoriesView.value ? catalog.value.categories : catalog.value.services).filter(row =>
    row.name.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es'))
    && (!categoryFilter.value || categoriesView.value || Number(row.service_category_id) === Number(categoryFilter.value))
    && (stateFilter.value === '' || Boolean(row.active) === (stateFilter.value === 'active'))));

// El asistente conserva todos los pasos en un único formulario y vuelve al campo rechazado por Laravel.
const steps = ['Información', 'Alcance', 'Modalidad', 'Publicación'];
const step = ref(0);
const slugEdited = ref(false);
const service = useForm({ id: null, name: '', slug: '', service_category_id: '', summary: '', description: '', deliverables: '', estimation_mode: 'manual', active: false });
const slugify = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
watch(() => service.name, name => { if (!slugEdited.value) service.slug = slugify(name); });
watch(() => page.url, () => {
    const row = selected.value;
    configurationTab.value = new URLSearchParams(page.url.split('?')[1] ?? '').get('config') ?? 'informacion';
    slugEdited.value = Boolean(row);
    step.value = 0;
    service.defaults({ id: row?.id ?? null, name: row?.name ?? '', slug: row?.slug ?? '', service_category_id: row?.service_category_id ?? '', summary: row?.summary ?? '', description: row?.description ?? '', deliverables: Array.isArray(row?.deliverables) ? row.deliverables.join('\n') : '', estimation_mode: row?.estimation_mode ?? 'manual', active: Boolean(row?.active) });
    service.reset();
    service.clearErrors();
}, { immediate: true });
function advance() {
    if (step.value < 3) { step.value++; return; }
    service.transform(values => ({...values, configure_after_save:true}));
    submit(service, '/admin/guardar/servicios', {
        onError: errors => {
            const fields = [['name', 'slug', 'service_category_id', 'summary'], ['description', 'deliverables'], ['estimation_mode'], ['active']];
            const invalid = fields.findIndex(group => group.some(field => errors[field]));
            if (invalid >= 0) step.value = invalid;
        },
    });
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
    <section class="page shell catalog-admin" :class="{ 'catalog-editor': editor }">
        <!-- Cada paso muestra solo los campos comerciales necesarios y conserva los valores al retroceder. -->
        <template v-if="editor">
            <B href="/admin/servicios" variant="ghost">← Volver a servicios</B>
            <T eyebrow="Catálogo" :title="selected ? 'Editar servicio' : 'Nuevo servicio'" :description="selected ? 'Administrá el alcance, las preguntas y la estimación de este servicio.' : 'Completá los datos del servicio. Después podés configurar sus preguntas, estimación y precios.'" />
            <!-- Las pestañas conservan el contexto del servicio sin repetir pantallas administrativas. -->
            <nav v-if="selected" class="service-tabs" aria-label="Configuración del servicio"><B v-for="[key,label] in tabs" :key="key" compact :variant="configurationTab === key ? 'primary' : 'secondary'" :aria-current="configurationTab === key ? 'page' : undefined" @click="configurationTab = key">{{ label }}</B></nav>
            <v-card v-if="selected && configurationTab !== 'informacion'" class="card"><SynauxServiceConfiguration :key="selected.id" :service="selected" :administration="catalog" :section="configurationTab" /></v-card>
            <template v-else>
            <ol class="catalog-steps" aria-label="Pasos del servicio"><li v-for="(label, index) in steps" :key="label" :aria-current="step === index ? 'step' : undefined"><span>{{ index + 1 }}</span>{{ label }}</li></ol>
            <v-card class="card form-card">
                <form class="catalog-form" @submit.prevent="advance">
                    <template v-if="step === 0">
                        <h2>Información general</h2><p>Identificá el servicio y cómo aparecerá en el catálogo.</p>
                        <F class="catalog-half" v-model="service.name" label="Nombre del servicio" required :error="service.errors.name" maxlength="255" />
                        <F class="catalog-half" v-model="service.service_category_id" label="Categoría" type="select" :options="categoryOptions" required :error="service.errors.service_category_id" />
                        <p v-if="!categoryOptions.length">Primero creá una categoría desde <a href="/admin/categorias">Catálogo → Categorías</a>.</p>
                        <F v-model="service.summary" label="Descripción breve" type="textarea" required maxlength="1000" :error="service.errors.summary" />
                        <F :model-value="service.slug" @update:model-value="service.slug = $event; slugEdited = true" label="Identificador en la dirección (slug)" required maxlength="150" hint="Se genera desde el nombre. Podés ajustarlo usando letras, números y guiones." :error="service.errors.slug" />
                    </template>
                    <template v-else-if="step === 1">
                        <h2>Alcance del servicio</h2><p>Explicá qué ofrecés y qué recibirá el cliente.</p>
                        <F class="catalog-half catalog-long-field" v-model="service.description" label="Descripción completa" type="textarea" required maxlength="10000" :error="service.errors.description" />
                        <F class="catalog-half catalog-long-field" v-model="service.deliverables" label="Entregables (uno por línea)" type="textarea" required maxlength="10000" :error="service.errors.deliverables" />
                    </template>
                    <template v-else-if="step === 2">
                        <h2>Modalidad de estimación</h2>
                        <F v-model="service.estimation_mode" label="¿Cómo se cotiza este servicio?" type="select" :options="[{ value: 'manual', label: 'Cotización profesional' }, { value: 'automatic', label: 'Estimación automática' }]" required :error="service.errors.estimation_mode" />
                        <p v-if="service.estimation_mode === 'manual'">Un profesional revisará el alcance y preparará un presupuesto formal. Este servicio no necesita un precio automático.</p>
                        <div v-else class="notice"><p>La estimación es orientativa. Para calcularla, el servicio necesita componentes, reglas y precios en un tarifario. Después de guardar, completalos en las pestañas de este servicio.</p></div>
                        <p>Los precios se administran en versiones del tarifario para conservar los valores de estimaciones anteriores.</p>
                    </template>
                    <template v-else>
                        <h2>Revisión y publicación</h2>
                        <dl class="catalog-summary"><dt>Servicio</dt><dd>{{ service.name }}</dd><dt>Categoría</dt><dd>{{ categoryName(service.service_category_id) }}</dd><dt>Descripción breve</dt><dd>{{ service.summary }}</dd><dt>Modalidad</dt><dd>{{ service.estimation_mode === 'automatic' ? 'Estimación automática' : 'Cotización profesional' }}</dd></dl>
                        <label class="select-row"><input v-model="service.active" type="checkbox">Mostrar este servicio en el catálogo</label>
                        <p v-if="service.errors.active" role="alert">{{ service.errors.active }}</p>
                        <p>Dejalo desactivado mientras completás su configuración.</p>
                        <p v-if="service.estimation_mode === 'automatic'">Antes de activarlo, revisá que tenga cobertura en el tarifario vigente. Guardarlo no genera precios ni reglas automáticamente.</p>
                    </template>
                    <div class="form-actions"><B v-if="step > 0" variant="secondary" :disabled="service.processing" @click="step--">Anterior</B><B type="submit" :disabled="service.processing">{{ service.processing ? 'Guardando…' : step === 3 ? 'Guardar servicio' : 'Siguiente' }}</B></div>
                </form>
            </v-card>
            </template>
        </template>
        <!-- Los listados permiten localizar servicios por nombre, categoría y estado sin editar sus precios. -->
        <template v-else>
            <T eyebrow="Catálogo" :title="categoriesView ? 'Categorías de servicios' : 'Catálogo de servicios'" :description="categoriesView ? 'Organizá los servicios de synaux por especialidad.' : 'Administrá los servicios de synaux.'"><B v-if="categoriesView" @click="editCategory()">Nueva categoría</B><B v-else href="/admin/servicios/nuevo">Nuevo servicio</B></T>
            <div class="filters"><F v-model="search" label="Buscar por nombre" /><F v-if="!categoriesView" v-model="categoryFilter" label="Categoría" type="select" :options="[{ value: '', label: 'Todas' }, ...categoryOptions]" /><F v-model="stateFilter" label="Estado" type="select" :options="[{ value: '', label: 'Todos' }, { value: 'active', label: 'Activos' }, { value: 'inactive', label: 'Inactivos' }]" /></div>
            <div class="table-scroll"><table><thead><tr><th>{{ categoriesView ? 'Categoría' : 'Servicio' }}</th><th>{{ categoriesView ? 'Servicios' : 'Categoría' }}</th><th v-if="!categoriesView">Modalidad</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
                <tr v-for="row in rows" :key="row.id"><td>{{ row.name }}</td><td>{{ categoriesView ? catalog.services.filter(service => service.service_category_id === row.id).length : categoryName(row.service_category_id) }}</td><td v-if="!categoriesView">{{ row.estimation_mode === 'automatic' ? 'Automático' : 'Profesional' }}</td><td>{{ row.active ? 'Activo' : 'Inactivo' }}</td><td><B v-if="categoriesView" variant="ghost" @click="editCategory(row)">Editar</B><B v-else variant="ghost" :href="`/admin/servicios/${row.id}/editar`">Editar</B></td></tr>
                <tr v-if="!rows.length"><td :colspan="categoriesView ? 4 : 5">No hay resultados para mostrar.</td></tr>
            </tbody></table></div>
        </template>
        <!-- El formulario de categoría conserva campos y errores mientras Laravel valida el guardado. -->
        <SidePanel title="Categorías de servicios" v-model="categoryDialog" max-width="640" :persistent="category.processing"><div class="synaux-app"><v-card class="card modal-card"><h2>{{ category.id ? 'Editar categoría' : 'Nueva categoría' }}</h2><form @submit.prevent="saveCategory"><F v-model="category.name" label="Nombre" required maxlength="255" :error="category.errors.name" /><F :model-value="category.slug" @update:model-value="category.slug = $event; categorySlugEdited = true" label="Identificador (slug)" required :error="category.errors.slug" /><F v-model="category.description" label="Descripción" type="textarea" required maxlength="10000" :error="category.errors.description" /><label class="select-row"><input v-model="category.active" type="checkbox">Categoría activa</label><p v-if="category.errors.active" role="alert">{{ category.errors.active }}</p><p>Desactivar la categoría conserva los servicios vinculados y no cambia su publicación.</p><div class="form-actions"><B variant="secondary" :disabled="category.processing" @click="categoryDialog = false">Cancelar</B><B type="submit" :disabled="category.processing">{{ category.processing ? 'Guardando…' : 'Guardar categoría' }}</B></div></form></v-card></div></SidePanel>
    </section>
</template>

<style scoped>
/* Los pasos y el resumen se adaptan al ancho disponible usando la paleta existente de synaux. */
.catalog-steps { display: flex; flex-wrap: wrap; gap: 12px 24px; list-style: none; padding: 0; margin: 0 0 28px; color: #446075; }
.catalog-steps li { display: flex; align-items: center; gap: 8px; }
.catalog-steps li span { display: grid; place-items: center; width: 30px; height: 30px; border: 1px solid #1f6f7f33; border-radius: 50%; }
.catalog-steps li[aria-current] { color: #153b64; font-weight: 700; }
.catalog-steps li[aria-current] span { background: #153b64; color: #fff; }
.catalog-summary { display: grid; grid-template-columns: minmax(100px, 1fr) 2fr; gap: 12px 20px; margin-bottom: 24px; }
.catalog-summary dt { font-weight: 700; }
.catalog-summary dd { margin: 0; overflow-wrap: anywhere; }
.service-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:24px; }
/* El asistente ocupa una columna centrada amplia; los campos breves y de alcance usan dos columnas. */
.catalog-admin.catalog-editor { max-width:1040px; margin-inline:auto; padding-top:32px; }
.catalog-editor :deep(.page-title) { margin-bottom:24px; }
.catalog-editor :deep(.page-title h1) { font-family:'Space Grotesk',sans-serif; font-size:clamp(1.75rem,3vw,2rem); font-weight:700; line-height:1.2; margin:0 0 10px; }
.catalog-editor :deep(.page-title p:not(.eyebrow)) { max-width:68ch; margin:0; font-size:1rem; line-height:1.6; text-wrap:pretty; }
.catalog-editor .form-card { width:100%; max-width:none; padding:28px; }
.catalog-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px 24px; }
.catalog-form > * { grid-column:1 / -1; min-width:0; }
.catalog-form > :deep(.catalog-half) { grid-column:auto; }
.catalog-form > :deep(.field) { margin:0; }
.catalog-form > h2 { font-family:'Space Grotesk',sans-serif; font-size:1.5rem; font-weight:600; line-height:1.3; margin:0; }
.catalog-form > p { margin:0; color:#446075; text-wrap:pretty; }
.catalog-form :deep(textarea) { min-height:104px; }
.catalog-form :deep(.catalog-long-field textarea) { min-height:200px; }
.catalog-form .form-actions { display:flex; align-items:center; justify-content:flex-end; gap:12px; margin:8px 0 0; padding-top:20px; border-top:1px solid #1f6f7f22; }
.catalog-form .form-actions > :first-child:not(:last-child) { margin-right:auto; }
/* Los cuatro pasos aprovechan el ancho del formulario sin agrandar sus títulos. */
.catalog-editor .catalog-steps { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-bottom:20px; font-size:.875rem; }
.catalog-editor .catalog-steps li { gap:8px; padding:10px 12px; border:1px solid #1f6f7f22; border-radius:8px; background:#f5f9fa; }
.catalog-editor .catalog-steps li[aria-current] { background:#153b640a; border-color:#153b64; }
.catalog-editor .catalog-steps li span { width:26px; height:26px; flex-shrink:0; }
@media (max-width:680px) {
    .catalog-admin.catalog-editor { padding-top:24px; }
    .catalog-editor .form-card { padding:20px; }
    .catalog-form { grid-template-columns:minmax(0,1fr); gap:16px; }
    .catalog-form > :deep(.catalog-half) { grid-column:1; }
    .catalog-editor .catalog-steps { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .catalog-form :deep(.catalog-long-field textarea) { min-height:140px; }
}
.catalog-admin a:not(.btn) { color: #1f6f7f; text-decoration: underline; }
</style>
