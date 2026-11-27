<script setup>
import {computed, ref} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import I from '../../Components/UI/AppIcon.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import AccionIcono from '../../Components/UI/AccionIcono.vue';
import {date, money} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// Cada edición prepara una versión nueva y permite tomar los importes de una versión anterior.
const props = defineProps({data: Object});
const tariffs = computed(() => props.data.administration.tariffs);
const latestImport = computed(() => tariffs.value.find(item => item.source_url?.includes('tarifario.org')));
const panelOpen = ref(false);
const tariff = useForm({name: '', currency_code: 'ARS', active: false, services: [], components: [], segments: []});
const importForm = useForm({});
const submit = useSubmit();

function prepareVersion(source = {}) {
    const {services, components} = props.data.administration;
    tariff.name = '';
    tariff.currency_code = source.currency_code ?? 'ARS';
    tariff.active = false;
    tariff.services = services.filter(service => service.active && service.estimation_mode === 'automatic').map(service => {
        const price = source.services?.find(item => item.service_id === service.id);
        return {id: service.id, name: service.name, min: price?.price_min ?? '', max: price?.price_max ?? ''};
    });
    tariff.components = components.filter(component => component.active).map(component => {
        const price = source.components?.find(item => item.service_component_id === component.id);
        return {id: component.id, name: component.name, min: price?.price_min ?? '', max: price?.price_max ?? ''};
    });
    // Si se parte de una versión importada, mantiene sus importes A/B/C en la siguiente publicación.
    tariff.segments = (source.segments ?? []).map(price => ({
        service_id: price.service_id, segment: price.segment, min: price.price_min, max: price.price_max,
    }));
    panelOpen.value = true;
}

function saveTariff() {
    submit(tariff, '/admin/guardar/tarifario', {
        onSuccess: () => {
            panelOpen.value = false;
        }
    });
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
                <div><h2>Versiones del tarifario</h2>
                    <p>Los cambios se aplican a los próximos cálculos.</p></div>
                <B @click="prepareVersion()">＋ Nueva versión</B>
            </div>
            <div class="card workspace-import">
                <h3><I name="calculator"/> Actualización directa desde Tarifario.org</h3>
                <p>Consulta la tarifa de «Desarrollo de Web Site Institucional» y guarda una versión nueva para el
                    servicio institucional. Los valores A, B y C se asignan a Empresa, PyME y Particular.</p>
                <p>La referencia publicada incluye home y hasta 5 secciones, sin programación. Los demás servicios de
                    prueba quedan sin cambios; revisá el alcance antes de usar estos importes en trabajos reales.</p>
                <B :disabled="importForm.processing"
                   @click="importForm.post('/admin/tarifario/importar', { preserveScroll: true })">
                    {{ importForm.processing ? 'Consultando Tarifario.org…' : 'Consultar y actualizar precios' }}
                </B>
                <p>Fuente: <a href="https://tarifario.org/diseno-y-maquetacion-c43" target="_blank" rel="noopener">Tarifario.org · Diseño y maquetación</a>.
                    Importes orientativos publicados en ARS; la página atribuye la obra a CC BY-SA 4.0.</p>
                <p v-if="latestImport">Consultado: {{ date(latestImport.source_checked_at) }} · Versión fuente:
                    {{ latestImport.name.replace('Tarifario.org · Sitio institucional · ', '') }}</p>
                <p v-if="latestImport?.segments?.length">Importes guardados: Empresa {{ money(latestImport.segments.find(item => item.segment === 'Empresa')?.price_min, 'ARS') }} ·
                    PyME {{ money(latestImport.segments.find(item => item.segment === 'PyME')?.price_min, 'ARS') }} ·
                    Particular {{ money(latestImport.segments.find(item => item.segment === 'Particular')?.price_min, 'ARS') }}</p>
            </div>
            <div class="table-scroll workspace-table">
                <table>
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Configuración</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="version in tariffs" :key="version.id">
                        <td>{{ version.name }}</td>
                        <td>{{ version.currency_code }}</td>
                        <td><S :status="version.active ? 'enabled' : 'disabled'"/></td>
                        <td>
                            <!-- Copiar toma los importes como punto de partida para una versión nueva. -->
                            <AccionIcono icono="copy" texto="Crear desde esta versión"
                                         :etiqueta="'Crear versión a partir de ' + version.name"
                                         @click="prepareVersion(version)"/>
                        </td>
                    </tr>
                    <tr v-if="!tariffs.length">
                        <td colspan="4">Todavía no hay registros. Agregá el primero.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="notice">
                <div><h3>Precios y cálculos históricos</h3>
                    <p>Cada tarifario se guarda como una versión nueva. Las estimaciones y los presupuestos anteriores
                        conservan sus valores de origen.</p></div>
            </div>
        </div>

        <SidePanel title="Administración" v-model="panelOpen" max-width="600" :persistent="tariff.processing">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <h2>Nueva versión del tarifario</h2>
                    <form @submit.prevent="saveTariff">
                        <F v-model="tariff.name" label="Nombre de la versión" required/>
                        <F v-model="tariff.currency_code" label="Moneda" type="select"
                           :options="['ARS', 'USD', 'EUR']"/>
                        <template
                            v-for="[key, label] in [['services', 'Bases de servicios'], ['components', 'Componentes']]"
                            :key="key">
                            <h3>{{ label }}</h3>
                            <div v-for="price in tariff[key]" :key="price.id" class="tariff-row">
                                <strong>{{ price.name }}</strong>
                                <F v-model="price.min" label="Mínimo" type="number" min="0" step="0.01" required/>
                                <F v-model="price.max" label="Máximo" type="number" min="0" step="0.01" required/>
                            </div>
                        </template>
                        <template v-if="tariff.segments.length">
                            <h3>Precios por tipo de cliente</h3>
                            <div v-for="price in tariff.segments" :key="price.service_id + '-' + price.segment" class="tariff-row">
                                <strong>{{ price.segment }}</strong>
                                <F v-model="price.min" label="Importe" type="number" min="0" step="0.01" required/>
                                <F v-model="price.max" label="Importe máximo" type="number" min="0" step="0.01" required/>
                            </div>
                        </template>
                        <label class="select-row"><input v-model="tariff.active" type="checkbox">Activar para nuevas
                            estimaciones</label>
                        <p>Completá las bases y todos los componentes de los servicios automáticos para activar la
                            versión.</p>
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
