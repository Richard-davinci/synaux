<script setup>
import {nextTick, useId} from 'vue';
import B from '../UI/AppButton.vue';
import F from '../UI/FormField.vue';
import I from '../UI/AppIcon.vue';
import RequestQuestions from './PreguntasSolicitud.vue';
import {money} from '../../support/formatters';

// Se editan las mismas filas del formulario para conservar las respuestas y sus errores.
const props = defineProps({
    form: {type: Object, required: true},
    extras: {type: Array, required: true},
    additionalPrices: {type: Array, required: true},
    summaryCurrency: {type: String, required: true},
    extraName: {type: Function, required: true},
    extraQuestions: {type: Function, required: true},
    initialExtraAnswers: {type: Function, required: true},
});
const expandedExtra = defineModel('expanded', {type: Object});
const extrasId = useId();

// Si un campo plegado falla la validación nativa, se abre y recibe el foco para poder corregirlo.
async function revealExtra(event, item) {
    expandedExtra.value = item;
    await nextTick();
    event.target.focus();
    event.target.scrollIntoView({block: 'center'});
}

function extraOptions(index) {
    const used = props.form.additional_services.filter((_, position) => position !== index).map(item => Number(item.service_id));
    return props.extras.filter(item => !used.includes(item.database_id)).map(item => ({
        value: item.database_id,
        label: item.name
    }));
}

function changeExtra(item, serviceId) {
    if (item.service_id == serviceId) return;
    item.service_id = serviceId;
    item.answers = props.initialExtraAnswers(serviceId);
}

function addService() {
    const used = props.form.additional_services.map(item => Number(item.service_id));
    const next = props.extras.find(item => !used.includes(item.database_id));
    if (next) {
        props.form.additional_services.push({
            service_id: next.database_id,
            quantity: 1,
            notes: '',
            answers: props.initialExtraAnswers(next.database_id)
        });
        expandedExtra.value = props.form.additional_services.at(-1);
    }
}
</script>

<template>
    <h3>Servicios adicionales</h3>
    <p>Agregá otros servicios que quieras incluir en la consulta.</p>
    <div v-for="(item, index) in form.additional_services" :key="index" class="request-extra"
         :class="{ 'is-expanded': expandedExtra === item }">
        <!-- El encabezado permite editar o quitar sin desplegar todos los campos de la lista. -->
        <div class="request-extra-heading">
            <button type="button" class="request-extra-toggle" :aria-expanded="expandedExtra === item"
                    :aria-controls="extrasId + '-' + index"
                    @click="expandedExtra = expandedExtra === item ? null : item">
                <I name="arrow"/><span><strong>{{ extraName(item.service_id) }} × {{
                    item.quantity
                }}</strong><small>{{ expandedExtra === item ? 'Ocultar datos' : 'Editar datos' }} · <template
                v-if="additionalPrices[index].priced">{{ money(additionalPrices[index].min, summaryCurrency) }}<template
                v-if="additionalPrices[index].max !== additionalPrices[index].min"> – {{
                    money(additionalPrices[index].max, summaryCurrency)
                }}</template></template><template v-else>Pendiente de cotización</template></small></span>
            </button>
            <B variant="ghost" class="remove-extra" :aria-label="'Quitar servicio adicional ' + (index + 1)"
               @click="form.additional_services.splice(index, 1)"><I name="close"/>Quitar</B>
        </div>
        <div v-show="expandedExtra === item" :id="extrasId + '-' + index" class="request-extra-body"
             @invalid.capture="revealExtra($event, item)">
            <div class="request-extra-fields">
                <F :model-value="item.service_id" @update:model-value="changeExtra(item, $event)"
                   :label="'Servicio adicional ' + (index + 1)" type="select" :options="extraOptions(index)" required
                   :error="form.errors['additional_services.' + index + '.service_id']"/>
                <F v-model="item.quantity" label="Cantidad" type="number" min="1" max="1000" step="1" required
                   :error="form.errors['additional_services.' + index + '.quantity']"/>
            </div>
            <fieldset v-if="extraQuestions(item.service_id).length" class="request-extra-questions">
                <legend>Preguntas de {{ extraName(item.service_id) }}</legend>
                <RequestQuestions v-model="item.answers" :questions="extraQuestions(item.service_id)"
                                  :errors="form.errors" :prefix="'additional_services.' + index + '.answers.'"/>
            </fieldset>
            <F v-model="item.notes" label="Qué necesitás de este servicio (opcional)" type="textarea" maxlength="2000"
               :error="form.errors['additional_services.' + index + '.notes']"/>
        </div>
    </div>
    <!-- Agregar es una acción de la lista; queda separada del avance al siguiente paso. -->
    <div class="request-add-row"><B variant="secondary"
                                    :disabled="form.additional_services.length >= Math.min(extras.length, 20)"
                                    @click="addService"><I name="plus"/>Agregar servicio</B></div>
</template>

<style scoped>
/* El adicional agrupa sus campos y su acción local; agregar ocupa una fila independiente antes de continuar. */
.request-extra {
    padding: 12px 16px;
    margin-top: 12px;
    border: 1px solid #1f6f7f33;
    border-radius: 12px;
    background: #f5f9fa;
}

.request-extra-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.request-extra-toggle {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 0;
    text-align: left;
    color: #153b64;
    background: transparent;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
}

.request-extra-toggle strong {
    display: block;
    font-size: .95rem;
    overflow-wrap: anywhere;
}

.request-extra-toggle small {
    display: block;
    color: #446075;
    margin-top: 3px;
}

.request-extra-toggle:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.request-extra-toggle > .icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.is-expanded .request-extra-toggle > .icon {
    transform: rotate(90deg);
}

.request-extra-body {
    padding-top: 16px;
}

.request-extra-fields {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(80px, 1fr);
    gap: 16px;
}

.request-extra-body :deep(textarea) {
    min-height: 88px;
}

.request-extra-heading :deep(.remove-extra) {
    min-height: 40px;
    padding: 8px 10px;
    box-shadow: none;
}

.request-extra-heading :deep(.remove-extra .icon), .request-add-row :deep(.icon) {
    width: 18px;
    height: 18px;
    margin-right: 8px;
    flex: 0 0 auto;
}

.request-add-row {
    margin: 20px 0 28px;
}

.request-add-row :deep(.btn) {
    width: 100%;
    border-style: dashed;
    box-shadow: none;
}

.request-extra :deep(.field:last-child) {
    margin-bottom: 0;
}

@media (max-width: 600px) {
    .request-extra {
        padding: 12px;
    }

    .request-extra-fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 0;
    }
}

/* Las preguntas de cada adicional quedan agrupadas con el servicio correspondiente. */
.request-extra-questions {
    min-width: 0;
    border: 0;
    margin: 4px 0 14px;
    padding: 0;
}

.request-extra-questions > legend {
    margin-bottom: 10px;
    font-weight: 700;
}
</style>
