<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import I from '../../Components/UI/AppIcon.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import { date, money, useSubmit } from '../../support/synaux';

// Cada edición prepara una versión nueva y permite tomar los importes de una versión anterior.
const props = defineProps({ data: Object });
const tariffs = computed(() => props.data.administration.tariffs);
const latestImport = computed(() => tariffs.value.find(item => item.source_url));
const panelOpen = ref(false);
const tariff = useForm({ name: '', currency_code: 'ARS', active: false, services: [], components: [] });
const importForm = useForm({});
const submit = useSubmit();

function prepareVersion(source = {}) {
    const { services, components } = props.data.administration;
    tariff.name = '';
    tariff.currency_code = source.currency_code ?? 'ARS';
    tariff.active = false;
    tariff.services = services.filter(service => service.active && service.estimation_mode === 'automatic').map(service => {
        const price = source.services?.find(item => item.service_id === service.id);
        return { id: service.id, name: service.name, min: price?.price_min ?? '', max: price?.price_max ?? '' };
    });
    tariff.components = components.filter(component => component.active).map(component => {
        const price = source.components?.find(item => item.service_component_id === component.id);
        return { id: component.id, name: component.name, min: price?.price_min ?? '', max: price?.price_max ?? '' };
    });
    panelOpen.value = true;
}
function saveTariff() {
    submit(tariff, '/admin/guardar/tarifario', { onSuccess: () => { panelOpen.value = false; } });
}
</script>

<template>
    <section class="page shell workspace-view">
        <T eyebrow="Administración" title="Tarifarios"
            description="Consultá y publicá versiones de precios. Las preguntas y reglas se administran dentro de cada servicio del catálogo.">
            <B v-if="data.auth?.is_admin" compact href="/admin/etapa-2" variant="secondary">Revisar etapa 2</B>
        </T>
        <div class="workspace-tariffs">
            <div class="section-head">
                <div><h2>Versiones del tarifario</h2><p>Los cambios se aplican a los próximos cálculos.</p></div>
                <B @click="prepareVersion()">＋ Nueva versión</B>
            </div>
            <div class="card workspace-import">
                <h3><I name="calculator" /> Referencia de Sitio institucional</h3>
                <p>Actualizá los tres segmentos del paquete de hasta siete secciones con Tarifario.ar y la cotización CCL de venta.</p>
                <B :disabled="importForm.processing" @click="importForm.post('/admin/tarifario/importar', { preserveScroll: true })">
                    {{ importForm.processing ? 'Consultando precios…' : 'Actualizar desde Tarifario.ar' }}
                </B>
                <p>Contenido adaptado de <a href="https://tarifario.ar/categoria/dev-web-app#service-pd-3" target="_blank" rel="noopener">Tarifario Digital</a>, bajo <a href="https://tarifario.ar/licencia" target="_blank" rel="noopener">CC BY-NC 4.0</a>. Los importes se convierten a ARS para este proyecto académico.</p>
                <p v-if="latestImport">Última importación: {{ date(latestImport.source_checked_at) }} · Base PyME: USD {{ latestImport.source_usd }} · CCL venta: {{ money(latestImport.source_rate, 'ARS') }}</p>
            </div>
            <div class="table-scroll workspace-table">
                <table>
                    <thead><tr><th>Nombre</th><th>Configuración</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        <tr v-for="version in tariffs" :key="version.id">
                            <td>{{ version.name }}</td>
                            <td>{{ version.currency_code }}</td>
                            <td><S :status="version.active ? 'enabled' : 'disabled'" /></td>
                            <td><B variant="ghost" @click="prepareVersion(version)">Crear desde esta versión</B></td>
                        </tr>
                        <tr v-if="!tariffs.length"><td colspan="4">Todavía no hay registros. Agregá el primero.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="notice"><div><h3>Precios y cálculos históricos</h3><p>Cada tarifario se guarda como una versión nueva. Las estimaciones y los presupuestos anteriores conservan sus valores de origen.</p></div></div>
        </div>

        <SidePanel title="Administración" v-model="panelOpen" max-width="600" :persistent="tariff.processing">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <h2>Nueva versión del tarifario</h2>
                    <form @submit.prevent="saveTariff">
                        <F v-model="tariff.name" label="Nombre de la versión" required />
                        <F v-model="tariff.currency_code" label="Moneda" type="select" :options="['ARS', 'USD', 'EUR']" />
                        <template v-for="[key, label] in [['services', 'Bases de servicios'], ['components', 'Componentes']]" :key="key">
                            <h3>{{ label }}</h3>
                            <div v-for="price in tariff[key]" :key="price.id" class="tariff-row">
                                <strong>{{ price.name }}</strong>
                                <F v-model="price.min" label="Mínimo" type="number" min="0" step="0.01" required />
                                <F v-model="price.max" label="Máximo" type="number" min="0" step="0.01" required />
                            </div>
                        </template>
                        <label class="select-row"><input v-model="tariff.active" type="checkbox">Activar para nuevas estimaciones</label>
                        <p>Completá las bases y todos los componentes de los servicios automáticos para activar la versión.</p>
                        <div class="form-actions">
                            <B variant="secondary" :disabled="tariff.processing" @click="panelOpen = false">Cancelar</B>
                            <B type="submit" :disabled="tariff.processing">Guardar</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>
