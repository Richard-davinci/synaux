<script setup>
import './public-screen.css';
import {computed, ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';
import {date, money} from '../../support/formatters';
import {categoryIcons} from './serviceCategoryIcons';

const props = defineProps({data: Object});
// La ficha usa ambos textos de Administración y muestra una sola vez las descripciones iguales.
const selected = computed(() => props.data.selected ?? {});
const detailDescription = computed(() => selected.value.description?.trim() || '');
const detailIntro = computed(() => {
    const summary = selected.value.summary?.trim();
    return summary !== detailDescription.value ? summary : undefined;
});
// El detalle reutiliza los importes publicados del catálogo, con todos los segmentos visibles.
const detailReference = computed(() => props.data.references?.[selected.value.id]);
const detailPrices = computed(() => detailReference.value?.segments?.length
    ? detailReference.value.segments
    : detailReference.value ? [{
        label: 'Alcance base',
        min: detailReference.value.min,
        max: detailReference.value.max
    }] : []);
// El tipo de cliente elegido en el detalle acompaña el acceso al estimador.
const detailSegment = ref(props.data.initialClientType ?? 'PyME');
const displayCurrency = ref('ARS');
const chosenPrice = computed(() => detailPrices.value.find(item => item.label === detailSegment.value) ?? detailPrices.value[0]);
const estimateHref = computed(() => {
    const query = new URLSearchParams({servicio: selected.value.id, paso: '2'});
    if (['Particular', 'PyME', 'Empresa'].includes(chosenPrice.value?.label)) query.set('cliente', chosenPrice.value.label);
    return '/estimador?' + query.toString();
});
// El detalle reutiliza las escenas de trabajo digital que ya forman parte de los recursos de synaux.
const detailImage = computed(() => ['Desarrollo web', 'UX/UI'].includes(selected.value.category)
    ? {src: '/images/landing/planificacion.webp', alt: 'Planificación de una interfaz con bocetos y una computadora.'}
    : {src: '/images/landing/colaboracion.webp', alt: 'Dos personas revisando una propuesta digital.'});
const segmentDescriptions = {
    Particular: 'Personas y entidades sin fines de lucro',
    PyME: 'PyMEs e instituciones',
    Empresa: 'Grandes empresas',
};

function convertPrice(min, max, currency) {
    const source = detailReference.value.currency;
    const ccl = Number(detailReference.value.ccl_rate);
    if (source === currency || !ccl) return {min, max, currency: source};
    const convert = amount => source === 'USD' ? Number(amount) * ccl : Number(amount) / ccl;
    return {min: convert(min), max: convert(max), currency};
}

function detailPrice(min, max, currency = displayCurrency.value) {
    const amount = convertPrice(min, max, currency);
    return Number(amount.min) === Number(amount.max)
        ? money(amount.min, amount.currency)
        : money(amount.min, amount.currency) + ' – ' + money(amount.max, amount.currency);
}

function detailEquivalent(min, max) {
    if (!detailReference.value?.ccl_rate) return null;
    return convertPrice(min, max, displayCurrency.value === 'ARS' ? 'USD' : 'ARS');
}

function detailEquivalentText(min, max) {
    const amount = detailEquivalent(min, max);
    if (!amount) return '';
    const range = Number(amount.min) === Number(amount.max) ? '' : ' – ' + money(amount.max, amount.currency);
    return 'Equivale a ' + money(amount.min, amount.currency) + range;
}
</script>

<template>
    <!-- La ficha reúne el alcance y la modalidad de presupuesto de cada servicio. -->
    <section data-public-screen class="page shell service-detail public-editorial public-typography">
        <B href="/plataforma" variant="ghost" compact class="project-back">← Volver al catálogo</B>
        <header class="service-intro">
            <div>
                <I :name="categoryIcons[selected.category] ?? 'folder'" class="editorial-icon"/>
                <T class="public-page-heading" :eyebrow="selected.category" :title="selected.name"
                   :description="detailIntro"/>
            </div>
            <img class="service-image" :src="detailImage.src" :alt="detailImage.alt" width="1200" height="800"
                 fetchpriority="high">
        </header>

        <!-- El presupuesto se muestra junto al alcance en escritorio y al inicio de la ficha en pantallas angostas. -->
        <div class="service-detail-grid">
            <section class="editorial-panel detail-pricing" aria-labelledby="service-pricing-title">
                <h2 id="service-pricing-title">{{
                        detailReference ? 'Precio orientativo' : 'Presupuesto a medida'
                    }}</h2>
                <template v-if="detailReference">
                    <p v-if="detailReference.segments?.length">Elegí el tipo de cliente para ver el importe en
                        {{ displayCurrency }}.</p>
                    <div v-if="detailReference.ccl_rate" class="detail-currency" role="group" aria-label="Moneda principal del precio">
                        <span>Mostrar en</span>
                        <button v-for="currency in ['ARS', 'USD']" :key="currency" type="button"
                                :aria-pressed="displayCurrency === currency" @click="displayCurrency = currency">{{ currency }}</button>
                        <span class="detail-ccl-reference">CCL aplicado: <strong>{{ money(detailReference.ccl_rate, 'ARS') }}</strong> por USD</span>
                    </div>
                    <div v-if="detailReference.segments?.length" class="detail-price-grid" role="group"
                         aria-label="Precio por tipo de cliente">
                        <button v-for="price in detailPrices" :key="price.label" type="button"
                                class="detail-price-option"
                                :aria-pressed="price.label === chosenPrice?.label" @click="detailSegment = price.label">
                            <span class="detail-price-label">{{ price.label }} <I
                                v-if="price.label === chosenPrice?.label" name="check"/></span>
                            <strong>{{ detailPrice(price.min, price.max) }}</strong>
                            <span v-if="detailEquivalent(price.min, price.max)" class="detail-price-equivalent">{{ detailEquivalentText(price.min, price.max) }}</span>
                            <span v-if="segmentDescriptions[price.label]"
                                  class="detail-price-description">{{ segmentDescriptions[price.label] }}</span>
                        </button>
                    </div>
                    <p v-else-if="chosenPrice" class="detail-base-price">
                        <strong>{{ detailPrice(chosenPrice.min, chosenPrice.max) }}</strong> {{
                            detailReference.currency
                        }}</p>
                    <p class="detail-price-date">Valores vigentes desde {{ date(detailReference.date) }}.</p>
                    <div class="detail-price-info">
                        <I name="info"/>
                        <p>El importe final depende del alcance, los entregables y los plazos acordados. Se confirma en
                            el presupuesto formal.</p>
                    </div>
                </template>
                <p v-else>Contanos qué necesitás. Revisamos tu objetivo y preparamos una propuesta con entregables,
                    precio, plazo y condiciones.</p>
                <div class="editorial-actions detail-actions">
                    <B v-if="!$page.props.auth?.es_cuenta_interna || data.preview"
                       :href="detailReference ? estimateHref : '/solicitudes/nueva?servicio=' + encodeURIComponent(selected.id)"
                       arrow>{{ detailReference ? 'Iniciar estimación' : 'Solicitar presupuesto' }}</B>
                    <B href="/contacto" variant="secondary">Hacer una consulta</B>
                </div>
            </section>

            <section v-if="detailDescription" class="service-description-section"
                     aria-labelledby="service-description-title">
                <h2 id="service-description-title">Descripción del servicio</h2>
                <p class="service-description text-block">{{ detailDescription }}</p>
            </section>

            <article v-if="selected.includes?.length" class="editorial-panel service-includes"
                     aria-labelledby="service-includes-title">
                <h2 id="service-includes-title">Qué incluye</h2>
                <ul class="service-deliverables">
                    <li v-for="item in selected.includes" :key="item"><I name="check"/>{{ item }}</li>
                </ul>
            </article>
        </div>
    </section>
</template>

<style scoped>
/* Los entregables conservan la misma presentación que en el catálogo. */
.service-deliverables {
    display: grid;
    gap: .3rem;
    margin: .25rem 0 .5rem;
    padding: 0;
    list-style: none;
}

.service-deliverables li {
    display: flex;
    align-items: start;
    gap: .5rem;
    font-size: .875rem;
    color: #446075;
}

.service-deliverables .icon {
    width: 1.1rem;
    height: 1.1rem;
    margin-top: .15rem;
    color: #1f6f7f;
    flex-shrink: 0;
}

/* La ficha usa la escala pública y une los entregables con el siguiente paso. */
.service-intro {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    align-items: center;
    gap: clamp(20px, 4vw, 48px);
    margin-bottom: 22px;
}

.service-detail .service-intro :deep(.public-page-heading) {
    margin-bottom: 0;
}

.service-image {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: 2 / 1;
    object-fit: cover;
    border: 1px solid #1f6f7f26;
    border-radius: 16px;
    box-shadow: 0 6px 20px #153b6412;
}

/* Descripción y entregables comparten una columna amplia; el presupuesto conserva espacio para los segmentos. */
.service-detail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    align-items: start;
    gap: clamp(18px, 3vw, 32px);
}

.service-description-section, .service-includes {
    grid-column: 1;
}

.service-description-section {
    max-width: 75ch;
}

.service-description-section p {
    margin: 0;
}

.service-includes .service-deliverables {
    margin: 0;
    gap: 12px;
}

.service-detail .service-includes li {
    font-size: 1rem;
    text-wrap: pretty;
}

.detail-pricing {
    grid-column: 2;
    grid-row: 1 / span 2;
    border-top: 3px solid #2db3a3;
    container-type: inline-size;
}

.detail-pricing:only-child {
    grid-column: 1 / -1;
    grid-row: auto;
}

.detail-price-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-block: 16px;
}

/* Permite priorizar pesos o dólares sin ocultar el equivalente convertido con el CCL de la tarifa. */
.detail-currency { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 6px; margin-block: 8px; color: #446075; font-size: .8rem; }
.detail-currency button { min-width: 52px; min-height: 30px; border: 1px solid #1f6f7f44; border-radius: 7px; background: #fff; color: #153b64; font: inherit; font-weight: 700; cursor: pointer; }
.detail-currency button[aria-pressed='true'] { color: #fff; background: #1f6f7f; border-color: #1f6f7f; }
.detail-ccl-reference { margin-left: 6px; padding: 5px 8px; border: 1px solid #1f6f7f26; border-radius: 6px; background: #f5f9fa; color: #446075; font-size: .75rem; }
.detail-ccl-reference strong { color: #153b64; }
.detail-price-equivalent { color: #446075; font-size: .75rem; line-height: 1.35; }

.detail-price-option {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    text-align: left;
    font: inherit;
    color: #153b64;
    padding: 12px;
    border: 1px solid #1f6f7f33;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
}

.detail-price-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    width: 100%;
    font-weight: 700;
}

.detail-price-label .icon {
    width: 16px;
    height: 16px;
    color: #1f6f7f;
}

.detail-price-option strong, .detail-base-price strong {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.2rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.detail-price-description {
    color: #446075;
    font-size: .875rem;
    text-wrap: pretty;
}

.detail-price-option:hover, .detail-price-option[aria-pressed='true'] {
    border-color: #1f6f7f;
}

.detail-price-option[aria-pressed='true'] {
    background: #2db3a31a;
}

.detail-price-option:focus-visible {
    outline: 2px solid #153b64;
    outline-offset: 2px;
}

.service-detail .detail-pricing .detail-price-date {
    font-size: .875rem;
    margin-bottom: 12px;
}

.detail-price-info {
    display: flex;
    align-items: start;
    gap: 10px;
    padding: 12px;
    background: #f5f9fa;
    border-radius: 10px;
}

.detail-price-info .icon {
    flex-shrink: 0;
    color: #1f6f7f;
}

.detail-price-info p {
    margin: 0;
}

.detail-actions {
    margin-top: 18px;
}

/* Al apilar la ficha, la acción queda visible antes del contenido extenso. */
@media (max-width: 900px) {
    .service-detail-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .detail-pricing {
        grid-column: 1;
        grid-row: auto;
    }
}

@media (max-width: 620px) {
    .service-intro {
        grid-template-columns: minmax(0, 1fr);
    }
}

@container (max-width: 380px) {
    .detail-price-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
