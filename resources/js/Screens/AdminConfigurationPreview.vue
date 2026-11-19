<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../Components/UI/AppButton.vue';
import F from '../Components/UI/FormField.vue';
import T from '../Components/UI/PageTitle.vue';
import S from '../Components/UI/StatusBadge.vue';
import SidePanel from '../Components/UI/SidePanel.vue';
import { useSubmit } from '../support/synaux';

// La galería conserva las pantallas históricas sin exponerlas en Administración.
const props = defineProps({ data: Object });
const section = computed(() => props.data.section);
const admin = computed(() => props.data.administration);
const sections = {
    preguntas: { key: 'questions', title: 'Preguntas del estimador', column: 'Pregunta' },
    componentes: { key: 'components', title: 'Componentes por servicio', column: 'Nombre' },
    reglas: { key: 'rules', title: 'Reglas de cálculo', column: 'Opción' },
};
const listing = computed(() => sections[section.value]);
const rows = computed(() => admin.value[listing.value.key] ?? []);
const panelOpen = ref(false);
const form = useForm({});
const submit = useSubmit();
const options = items => items.map(item => ({ value: item.id, label: item.name }));
const questionTypes = [
    { value: 'single_choice', label: 'Una opción' },
    { value: 'multiple_choice', label: 'Varias opciones' },
    { value: 'number', label: 'Cantidad' },
    { value: 'text', label: 'Texto' },
];
const answerOptions = computed(() => admin.value.questions.flatMap(question => question.options
    .filter(option => option.active)
    .map(option => ({ value: option.id, label: question.question + ': ' + option.label }))));

function configure(row = {}) {
    const serviceId = admin.value.services[0]?.id;
    const defaults = {
        preguntas: { question: '', service_id: serviceId, input_type: 'single_choice', sort_order: 0,
            options_text: row.options?.filter(option => option.active).map(option => option.label).join('\n') ?? '', quantity_component_id: null },
        componentes: { name: '', slug: '', description: '', service_id: serviceId, unit_code: 'unidad', is_default: Boolean(row.is_default), default_quantity: 1 },
        reglas: { estimator_option_id: null, service_component_id: null, quantity: 1 },
    }[section.value];
    const values = { id: row.id ?? null, active: row.active == null || Boolean(row.active), ...defaults };
    for (const key of Object.keys(defaults)) {
        if (key !== 'is_default' && key in row) values[key] = row[key];
    }
    form.defaults(values);
    form.reset();
    form.clearErrors();
    panelOpen.value = true;
}
function saveConfiguration() {
    submit(form, '/admin/guardar/' + section.value, { onSuccess: () => { panelOpen.value = false; } });
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Configuración de referencia"
            description="Consultá y publicá versiones de precios. Las preguntas y reglas se administran dentro de cada servicio del catálogo.">
            <B v-if="data.auth?.is_admin" compact href="/admin/etapa-2" variant="secondary">Revisar etapa 2</B>
        </T>
        <div class="workspace-tariffs">
            <div class="section-head">
                <div><h2>{{ listing.title }}</h2><p>Los cambios se aplican a los próximos cálculos.</p></div>
                <B @click="configure()">＋ Agregar</B>
            </div>
            <div class="table-scroll workspace-table">
                <table>
                    <thead><tr><th>{{ listing.column }}</th><th>Configuración</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td>{{ row.name ?? row.question ?? row.label }}</td>
                            <td>{{ row.input_type ?? (row.quantity != null ? row.name + ' × ' + row.quantity : row.unit_code) }}</td>
                            <td><S :status="row.active ? 'enabled' : 'disabled'" /></td>
                            <td><B variant="ghost" @click="configure(row)">Editar</B></td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="4">Todavía no hay registros. Agregá el primero.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="notice"><div><h3>Precios y cálculos históricos</h3><p>Cada tarifario se guarda como una versión nueva. Las estimaciones y los presupuestos anteriores conservan sus valores de origen.</p></div></div>
        </div>
        <SidePanel title="Administración" v-model="panelOpen" max-width="600" :persistent="form.processing">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <h2>Configurar {{ section }}</h2>
                    <form @submit.prevent="saveConfiguration">
                        <F v-if="section !== 'reglas'" v-model="form.service_id" type="select" label="Servicio"
                            :options="options(admin.services)" :required="section === 'componentes'" />
                        <template v-if="section === 'preguntas'">
                            <F v-model="form.question" label="Pregunta" required />
                            <F v-model="form.input_type" label="Tipo de respuesta" type="select" :options="questionTypes" required />
                            <F v-model="form.sort_order" label="Orden" type="number" min="0" required />
                            <F v-if="form.input_type.includes('choice')" v-model="form.options_text" type="textarea" label="Opciones (una por línea)" required />
                            <F v-if="form.input_type === 'number'" v-model="form.quantity_component_id" type="select" label="Componente cuya cantidad define"
                                :options="options(admin.components.filter(component => component.service_id == form.service_id))" />
                        </template>
                        <template v-else-if="section === 'componentes'">
                            <F v-model="form.name" label="Nombre" required />
                            <F v-model="form.slug" label="Identificador (sin espacios)" required />
                            <F v-model="form.description" type="textarea" label="Descripción" required />
                            <F v-model="form.unit_code" label="Unidad" required />
                            <F v-model="form.default_quantity" label="Cantidad inicial" type="number" min="0.001" step="0.001" required />
                            <label class="select-row"><input v-model="form.is_default" type="checkbox">Incluir por defecto</label>
                        </template>
                        <template v-else>
                            <F v-model="form.estimator_option_id" type="select" label="Opción de respuesta" :options="answerOptions" required />
                            <F v-model="form.service_component_id" type="select" label="Componente" :options="options(admin.components)" required />
                            <F v-model="form.quantity" type="number" label="Cantidad que incorpora" min="0" step="0.001" required />
                        </template>
                        <label class="select-row"><input v-model="form.active" type="checkbox">Activo</label>
                        <div class="form-actions">
                            <B variant="secondary" :disabled="form.processing" @click="panelOpen = false">Cancelar</B>
                            <B type="submit" :disabled="form.processing">Guardar</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>
