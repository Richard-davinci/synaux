<script setup>
import {computed, ref, watch} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import ServiceConfiguration from '../../Components/Admin/ServiceConfiguration.vue';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {useSubmit} from '../../support/useSubmit';
import {slugify} from './catalogSlug';

const props = defineProps({data: {type: Object, required: true}});
const page = usePage();
const submit = useSubmit();
const catalog = computed(() => props.data.administration);
const tabs = [
    ['informacion', 'Información y alcance'],
    ['preguntas', 'Preguntas'],
    ['estimacion', 'Estimación'],
    ['precios', 'Precios vigentes'],
];
const configurationTab = ref('informacion');
const serviceId = computed(() => Number(props.data.preview
    ? props.data.catalogServiceId
    : page.url.split('?')[0].match(/\/servicios\/(\d+)\/editar$/)?.[1]));
const selected = computed(() => catalog.value.services.find(service => service.id === serviceId.value));
const categoryOptions = computed(() => catalog.value.categories.map(category => ({
    value: category.id,
    label: category.name + (category.active ? '' : ' (inactiva)'),
})));
const modalidades = [
    {value: 'manual', label: 'Cotización profesional'},
    {value: 'automatic', label: 'Estimación automática'},
];

// La ficha reúne la información, el alcance y la publicación en un único guardado.
const slugEdited = ref(false);
const service = useForm({
    id: null, name: '', slug: '', service_category_id: '', summary: '', description: '',
    deliverables: '', estimation_mode: 'manual', active: false,
});
watch(() => service.name, name => {
    if (!slugEdited.value) service.slug = slugify(name);
});
watch(() => page.url, () => {
    const row = selected.value;
    const tab = new URLSearchParams(page.url.split('?')[1] ?? '').get('config');
    configurationTab.value = tabs.some(([id]) => id === tab) ? tab : 'informacion';
    slugEdited.value = Boolean(row);
    service.defaults({
        id: row?.id ?? null, name: row?.name ?? '', slug: row?.slug ?? '',
        service_category_id: row?.service_category_id ?? '', summary: row?.summary ?? '',
        description: row?.description ?? '', deliverables: Array.isArray(row?.deliverables) ? row.deliverables.join('\n') : '',
        estimation_mode: row?.estimation_mode ?? 'manual', active: Boolean(row?.active),
    });
    service.resetAndClearErrors();
}, {immediate: true});

function guardar() {
    service.transform(values => ({...values, configure_after_save: values.id == null}));
    submit(service, '/admin/guardar/servicios');
}
</script>

<template>
    <section class="page shell catalog-admin workspace-view catalog-editor">
        <B href="/admin/servicios" variant="ghost">← Volver a servicios</B>
        <T eyebrow="Catálogo" :title="selected ? selected.name : 'Nuevo servicio'"
           :description="selected ? 'Editá la ficha pública, las preguntas y los precios de este servicio.' : 'Guardá la ficha para configurar sus preguntas y precios.'"/>
        <!-- Cada pestaña mantiene el contexto del servicio que se está editando. -->
        <nav v-if="selected" class="service-tabs" aria-label="Configuración del servicio">
            <B v-for="[key, label] in tabs" :key="key" compact :variant="configurationTab === key ? 'primary' : 'secondary'"
               :aria-current="configurationTab === key ? 'page' : undefined" @click="configurationTab = key">{{ label }}</B>
        </nav>
        <v-card v-if="selected && configurationTab !== 'informacion'" class="card">
            <ServiceConfiguration :key="selected.id" :service="selected" :administration="catalog" :section="configurationTab"/>
        </v-card>
        <v-card v-else class="card form-card">
            <form class="catalog-form" @submit.prevent="guardar">
                <section class="catalog-section" aria-labelledby="ficha-general">
                    <h2 id="ficha-general">Información general</h2>
                    <div class="catalog-fields">
                        <F v-model="service.name" label="Nombre del servicio" required :error="service.errors.name" maxlength="255"/>
                        <F v-model="service.service_category_id" label="Categoría" type="select"
                           :options="categoryOptions" required :error="service.errors.service_category_id"/>
                        <p v-if="!categoryOptions.length" class="catalog-wide">Primero creá una categoría desde <a href="/admin/categorias">Catálogo → Categorías</a>.</p>
                        <F v-model="service.summary" class="catalog-wide" label="Descripción breve" type="textarea"
                           required maxlength="1000" :error="service.errors.summary"/>
                        <F :model-value="service.slug" @update:model-value="service.slug = $event; slugEdited = true"
                           class="catalog-wide" label="Dirección del servicio (slug)" required maxlength="150"
                           hint="Se genera desde el nombre. Podés ajustarla con letras, números y guiones." :error="service.errors.slug"/>
                    </div>
                </section>
                <section class="catalog-section" aria-labelledby="ficha-alcance">
                    <h2 id="ficha-alcance">Alcance del servicio</h2>
                    <div class="catalog-fields">
                        <F v-model="service.description" label="Descripción completa" type="textarea"
                           hint="Explicá el objetivo y los límites del servicio." required maxlength="10000" :error="service.errors.description"/>
                        <F v-model="service.deliverables" label="Entregables (uno por línea)" type="textarea"
                           hint="Listá lo que recibe el cliente en «Qué incluye»." required maxlength="10000" :error="service.errors.deliverables"/>
                    </div>
                </section>
                <section class="catalog-section" aria-labelledby="ficha-modalidad">
                    <h2 id="ficha-modalidad">Modalidad de estimación</h2>
                    <F v-model="service.estimation_mode" label="¿Cómo se cotiza este servicio?" type="select"
                       :options="modalidades" required :error="service.errors.estimation_mode"
                       :hint="service.estimation_mode === 'manual' ? 'Un profesional revisa cada solicitud y prepara el presupuesto.' : 'Configurá la base, las reglas y los precios vigentes para calcular una referencia.'"/>
                </section>
                <section class="catalog-section" aria-labelledby="ficha-publicacion">
                    <h2 id="ficha-publicacion">Publicación</h2>
                    <label class="select-row"><input v-model="service.active" type="checkbox">Mostrar en el catálogo</label>
                    <p v-if="service.errors.active" role="alert">{{ service.errors.active }}</p>
                    <p v-if="service.estimation_mode === 'automatic'">Revisá la cobertura de precios antes de publicar un servicio automático.</p>
                </section>
                <div class="form-actions">
                    <B type="submit" :disabled="service.processing">{{ service.processing ? 'Guardando…' : 'Guardar servicio' }}</B>
                </div>
            </form>
        </v-card>
    </section>
</template>

<style scoped>
/* La ficha usa una columna amplia con grupos de campos que se apilan en móvil. */
.catalog-admin.catalog-editor { max-width: 1040px; margin-inline: auto; }
.service-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
.catalog-editor .form-card { width: 100%; max-width: none; padding: clamp(18px, 3vw, 28px); }
.catalog-section + .catalog-section { padding-top: 20px; margin-top: 24px; border-top: 1px solid #1f6f7f22; }
.catalog-section h2 { margin-bottom: 14px; }
.catalog-section p { margin: 8px 0 0; }
.catalog-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 16px 20px; }
.catalog-fields > :deep(.field), .catalog-section > :deep(.field) { margin-bottom: 0; }
.catalog-wide { grid-column: 1 / -1; }
.catalog-form .form-actions { justify-content: flex-end; padding-top: 18px; border-top: 1px solid #1f6f7f22; }
.catalog-admin a:not(.btn) { color: #1f6f7f; text-decoration: underline; }

@media (max-width: 680px) {
    .catalog-fields { grid-template-columns: minmax(0, 1fr); }
}
</style>
