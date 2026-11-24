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
const path = computed(() => page.url.split('?')[0]);
const configurationTab = ref(new URLSearchParams(page.url.split('?')[1] ?? '').get('config') ?? 'informacion');
const tabs = [
    ['informacion', 'Información y alcance'],
    ['preguntas', 'Preguntas'],
    ['estimacion', 'Estimación'],
    ['precios', 'Precios vigentes'],
];
const serviceId = computed(() => Number(props.data.preview
    ? props.data.catalogServiceId
    : path.value.match(/\/servicios\/(\d+)\/editar$/)?.[1]));
const selected = computed(() => catalog.value.services.find(service => service.id === serviceId.value));
const categoryOptions = computed(() => catalog.value.categories.map(category => ({
    value: category.id,
    label: category.name + (category.active ? '' : ' (inactiva)')
})));
const categoryName = id => catalog.value.categories.find(category => category.id === Number(id))?.name ?? 'Sin categoría';

// El asistente conserva todos los pasos en un único formulario y vuelve al campo rechazado por Laravel.
const steps = ['Información', 'Alcance', 'Modalidad', 'Publicación'];
const step = ref(0);
const slugEdited = ref(false);
const service = useForm({
    id: null,
    name: '',
    slug: '',
    service_category_id: '',
    summary: '',
    description: '',
    deliverables: '',
    estimation_mode: 'manual',
    active: false
});
watch(() => service.name, name => {
    if (!slugEdited.value) service.slug = slugify(name);
});
watch(() => page.url, () => {
    const row = selected.value;
    configurationTab.value = new URLSearchParams(page.url.split('?')[1] ?? '').get('config') ?? 'informacion';
    slugEdited.value = Boolean(row);
    step.value = 0;
    service.defaults({
        id: row?.id ?? null,
        name: row?.name ?? '',
        slug: row?.slug ?? '',
        service_category_id: row?.service_category_id ?? '',
        summary: row?.summary ?? '',
        description: row?.description ?? '',
        deliverables: Array.isArray(row?.deliverables) ? row.deliverables.join('\n') : '',
        estimation_mode: row?.estimation_mode ?? 'manual',
        active: Boolean(row?.active)
    });
    service.reset();
    service.clearErrors();
}, {immediate: true});

function advance() {
    if (step.value < steps.length - 1) {
        step.value++;
        return;
    }
    service.transform(values => ({...values, configure_after_save: true}));
    submit(service, '/admin/guardar/servicios', {
        onError: errors => {
            const fields = [['name', 'slug', 'service_category_id', 'summary'], ['description', 'deliverables'], ['estimation_mode'], ['active']];
            const invalid = fields.findIndex(group => group.some(field => errors[field]));
            if (invalid >= 0) step.value = invalid;
        },
    });
}
</script>

<template>
    <section class="page shell catalog-admin workspace-view catalog-editor">
        <B href="/admin/servicios" variant="ghost">← Volver a servicios</B>
        <T eyebrow="Catálogo" :title="selected ? selected.name : 'Nuevo servicio'"
           :description="selected ? 'Editá la ficha pública, las preguntas y los precios de este servicio.' : 'Completá los datos del servicio. Después podés configurar sus preguntas, estimación y precios.'"/>
        <!-- Las pestañas conservan el contexto del servicio sin repetir pantallas administrativas. -->
        <nav v-if="selected" class="service-tabs" aria-label="Configuración del servicio"><B v-for="[key,label] in tabs"
                                                                                             :key="key" compact
                                                                                             :variant="configurationTab === key ? 'primary' : 'secondary'"
                                                                                             :aria-current="configurationTab === key ? 'page' : undefined"
                                                                                             @click="configurationTab = key">{{
                label
            }}</B></nav>
        <v-card v-if="selected && configurationTab !== 'informacion'" class="card">
            <ServiceConfiguration :key="selected.id" :service="selected" :administration="catalog"
                                  :section="configurationTab"/>
        </v-card>
        <template v-else>
            <ol class="catalog-steps" aria-label="Pasos del servicio">
                <li v-for="(label, index) in steps" :key="label" :aria-current="step === index ? 'step' : undefined">
                    <span>{{ index + 1 }}</span>{{ label }}
                </li>
            </ol>
            <v-card class="card form-card">
                <form class="catalog-form" @submit.prevent="advance">
                    <template v-if="step === 0">
                        <h2>Información general</h2>
                        <p>Identificá el servicio y cómo aparecerá en el catálogo.</p>
                        <F class="catalog-half" v-model="service.name" label="Nombre del servicio" required
                           :error="service.errors.name" maxlength="255"/>
                        <F class="catalog-half" v-model="service.service_category_id" label="Categoría" type="select"
                           :options="categoryOptions" required :error="service.errors.service_category_id"/>
                        <p v-if="!categoryOptions.length">Primero creá una categoría desde <a href="/admin/categorias">Catálogo
                            → Categorías</a>.</p>
                        <F v-model="service.summary" label="Descripción breve" type="textarea" required maxlength="1000"
                           :error="service.errors.summary"/>
                        <F :model-value="service.slug" @update:model-value="service.slug = $event; slugEdited = true"
                           label="Identificador en la dirección (slug)" required maxlength="150"
                           hint="Se genera desde el nombre. Podés ajustarlo usando letras, números y guiones."
                           :error="service.errors.slug"/>
                    </template>
                    <template v-else-if="step === 1">
                        <h2>Alcance del servicio</h2>
                        <p>Explicá qué ofrecés y qué recibirá el cliente.</p>
                        <F class="catalog-half catalog-long-field" v-model="service.description"
                           label="Descripción completa" type="textarea"
                           hint="Explicá el objetivo y los límites del servicio." required maxlength="10000"
                           :error="service.errors.description"/>
                        <F class="catalog-half catalog-long-field" v-model="service.deliverables"
                           label="Entregables (uno por línea)" type="textarea"
                           hint="Listá lo que recibe el cliente en «Qué incluye»." required maxlength="10000"
                           :error="service.errors.deliverables"/>
                    </template>
                    <template v-else-if="step === 2">
                        <h2>Modalidad de estimación</h2>
                        <F v-model="service.estimation_mode" label="¿Cómo se cotiza este servicio?" type="select"
                           :options="[{ value: 'manual', label: 'Cotización profesional' }, { value: 'automatic', label: 'Estimación automática' }]"
                           required :error="service.errors.estimation_mode"/>
                        <p v-if="service.estimation_mode === 'manual'">Un profesional revisará el alcance y preparará un
                            presupuesto formal. Este servicio no necesita un precio automático.</p>
                        <div v-else class="notice"><p>La estimación es orientativa. Cargá el precio base en el
                            tarifario. Si hay extras, agregá sus conceptos de precio y las reglas que correspondan.
                            Después de guardar, completalos en las pestañas de este servicio.</p></div>
                        <p>Los precios se administran en versiones del tarifario para conservar los valores de
                            estimaciones anteriores.</p>
                    </template>
                    <template v-else>
                        <h2>Revisión y publicación</h2>
                        <dl class="catalog-summary">
                            <dt>Servicio</dt>
                            <dd>{{ service.name }}</dd>
                            <dt>Categoría</dt>
                            <dd>{{ categoryName(service.service_category_id) }}</dd>
                            <dt>Descripción breve</dt>
                            <dd>{{ service.summary }}</dd>
                            <dt>Modalidad</dt>
                            <dd>{{
                                    service.estimation_mode === 'automatic' ? 'Estimación automática' : 'Cotización profesional'
                                }}
                            </dd>
                        </dl>
                        <label class="select-row"><input v-model="service.active" type="checkbox">Mostrar este servicio
                            en el catálogo</label>
                        <p v-if="service.errors.active" role="alert">{{ service.errors.active }}</p>
                        <p>Dejalo desactivado mientras completás su configuración.</p>
                        <p v-if="service.estimation_mode === 'automatic'">Antes de activarlo, revisá que tenga cobertura
                            en el tarifario vigente. Guardarlo no genera precios ni reglas automáticamente.</p>
                    </template>
                    <div class="form-actions"><B v-if="step > 0" variant="secondary" :disabled="service.processing"
                                                 @click="step--">Anterior</B><B type="submit"
                                                                                :disabled="service.processing">{{
                            service.processing ? 'Guardando…' : step === 3 ? 'Guardar servicio' : 'Siguiente'
                        }}</B></div>
                </form>
            </v-card>
        </template>
    </section>
</template>

<style scoped>
/* Los pasos y el resumen se adaptan al ancho disponible usando la paleta existente de synaux. */
.catalog-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 24px;
    list-style: none;
    padding: 0;
    margin: 0 0 28px;
    color: #446075;
}

.catalog-steps li {
    display: flex;
    align-items: center;
    gap: 8px;
}

.catalog-steps li span {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 1px solid #1f6f7f33;
    border-radius: 50%;
}

.catalog-steps li[aria-current] {
    color: #153b64;
    font-weight: 700;
}

.catalog-steps li[aria-current] span {
    background: #153b64;
    color: #fff;
}

.catalog-summary {
    display: grid;
    grid-template-columns: minmax(100px, 1fr) 2fr;
    gap: 12px 20px;
    margin-bottom: 24px;
}

.catalog-summary dt {
    font-weight: 700;
}

.catalog-summary dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.service-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 24px;
}

/* El asistente ocupa una columna centrada amplia; los campos breves y de alcance usan dos columnas. */
.catalog-admin.catalog-editor {
    max-width: 1040px;
    margin-inline: auto;
    padding-top: 32px;
}

.catalog-editor :deep(.page-title) {
    margin-bottom: 24px;
}

.catalog-editor :deep(.page-title h1) {
    font-family: 'Space Grotesk', sans-serif;
    font-size: clamp(1.75rem, 3vw, 2rem);
    font-weight: 700;
    line-height: 1.2;
    margin: 0 0 10px;
}

.catalog-editor :deep(.page-title p:not(.eyebrow)) {
    max-width: 68ch;
    margin: 0;
    font-size: 1rem;
    line-height: 1.6;
    text-wrap: pretty;
}

.catalog-editor .form-card {
    width: 100%;
    max-width: none;
    padding: 28px;
}

.catalog-form {
    display: grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap: 16px 24px;
}

.catalog-form > * {
    grid-column: 1 / -1;
    min-width: 0;
}

.catalog-form > :deep(.catalog-half) {
    grid-column: auto;
}

.catalog-form > :deep(.field) {
    margin: 0;
}

.catalog-form > h2 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.3;
    margin: 0;
}

.catalog-form > p {
    margin: 0;
    color: #446075;
    text-wrap: pretty;
}

.catalog-form :deep(textarea) {
    min-height: 104px;
}

.catalog-form :deep(.catalog-long-field textarea) {
    min-height: 200px;
}

.catalog-form .form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin: 8px 0 0;
    padding-top: 20px;
    border-top: 1px solid #1f6f7f22;
}

.catalog-form .form-actions > :first-child:not(:last-child) {
    margin-right: auto;
}

/* Los cuatro pasos aprovechan el ancho del formulario sin agrandar sus títulos. */
.catalog-editor .catalog-steps {
    display: grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 20px;
    font-size: .875rem;
}

.catalog-editor .catalog-steps li {
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #1f6f7f22;
    border-radius: 8px;
    background: #f5f9fa;
}

.catalog-editor .catalog-steps li[aria-current] {
    background: #153b640a;
    border-color: #153b64;
}

.catalog-editor .catalog-steps li span {
    width: 26px;
    height: 26px;
    flex-shrink: 0;
}

@media (max-width: 680px) {
    .catalog-admin.catalog-editor {
        padding-top: 24px;
    }

    .catalog-editor .form-card {
        padding: 20px;
    }

    .catalog-form {
        grid-template-columns:minmax(0, 1fr);
        gap: 16px;
    }

    .catalog-form > :deep(.catalog-half) {
        grid-column: 1;
    }

    .catalog-editor .catalog-steps {
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .catalog-form :deep(.catalog-long-field textarea) {
        min-height: 140px;
    }
}

.catalog-admin a:not(.btn) {
    color: #1f6f7f;
    text-decoration: underline;
}
</style>
