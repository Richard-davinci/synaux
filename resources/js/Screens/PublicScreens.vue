<script setup>
import { computed, ref, useId } from 'vue';
import { VAutocomplete } from 'vuetify/components';
import { useForm, usePage } from '@inertiajs/vue3';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import T from '../Components/SynauxTitle.vue';
import I from '../Components/SynauxIcon.vue';
import Empty from '../Components/SynauxEmpty.vue';
import CatalogServicePrice from '../Components/CatalogServicePrice.vue';
import { money, date, useSubmit } from '../support/synaux';
// Pantallas públicas: los datos llegan de Laravel; filtros y estados de interacción pertenecen a Vue.
const props = defineProps({ data: Object });
const page = usePage();
const query = ref('');
const category = ref('Todos');
const categorySearch = ref('');
const categoryInputId = useId();
const submit = useSubmit();
const form = useForm({ name: '', email: '', topic: 'Consulta general', message: '' });
// El selector mantiene altura fija aunque crezca el catálogo; las categorías publicadas se ordenan por nombre.
const categories = computed(() => [{ value: 'Todos', label: 'Todas las categorías' },
    ...[...new Set(props.data.services.map(service => service.category))].sort((a, b) => a.localeCompare(b, 'es'))
        .map(name => ({ value: name, label: name })),
]);

// El catálogo utiliza los mismos iconos que la landing y asigna una composición propia a UX/UI.
const categoryIcons = { 'Desarrollo web': 'web', Diseño: 'palette', Marketing: 'megaphone', 'UX/UI': 'layout' };
// La búsqueda admite palabras en cualquier orden, con o sin tildes, y respeta el filtro activo.
const normalizeSearch = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
const filterCategory = (value, query) => normalizeSearch(query).trim().split(/\s+/).every(word => normalizeSearch(String(value)).includes(word));
const searchWords = computed(() => normalizeSearch(query.value).trim().split(/\s+/).filter(Boolean));
const services = computed(() => props.data.services.filter(service => {
    if (category.value !== 'Todos' && service.category !== category.value) return false;

    const searchableText = normalizeSearch([service.name, service.category, service.summary].join(' '));
    return searchWords.value.every(word => searchableText.includes(word));
}));
// El enlace del catálogo conserva el segmento elegido y solo transmite su etiqueta, nunca un importe.
const catalogSegments = ref({});
function serviceHref(service) {
    const segment = catalogSegments.value[service.id] ?? 'PyME';
    const available = props.data.references?.[service.id]?.segments ?? [];
    return '/servicios/' + service.id + (available.some(item => item.label === segment) ? '?cliente=' + encodeURIComponent(segment) : '');
}
// El texto inicial destaca el beneficio; los límites del paquete quedan en los entregables y el detalle.
function catalogBenefit(service) {
    if (service.id === 'sitio-institucional' && service.summary === 'Sitio institucional en WordPress de hasta 7 secciones.') {
        return 'Presentá tu organización y facilitá el contacto con tus clientes.';
    }
    return service.summary;
}
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
    : detailReference.value ? [{ label: 'Alcance base', min: detailReference.value.min, max: detailReference.value.max }] : []);
// El tipo de cliente elegido en el detalle acompaña el acceso al estimador.
const detailSegment = ref(props.data.initialClientType ?? 'PyME');
const chosenPrice = computed(() => detailPrices.value.find(item => item.label === detailSegment.value) ?? detailPrices.value[0]);
const estimateHref = computed(() => {
    const query = new URLSearchParams({ servicio: selected.value.id, paso: '2' });
    if (['Particular', 'PyME', 'Empresa'].includes(chosenPrice.value?.label)) query.set('cliente', chosenPrice.value.label);
    return '/estimador?' + query.toString();
});
// El detalle reutiliza las escenas de trabajo digital que ya forman parte de los recursos de synaux.
const detailImage = computed(() => ['Desarrollo web', 'UX/UI'].includes(selected.value.category)
    ? { src: '/images/landing/planificacion.webp', alt: 'Planificación de una interfaz con bocetos y una computadora.' }
    : { src: '/images/landing/colaboracion.webp', alt: 'Dos personas revisando una propuesta digital.' });
const segmentDescriptions = {
    Particular: 'Personas y entidades sin fines de lucro',
    PyME: 'PyMEs e instituciones',
    Empresa: 'Grandes empresas',
};
function detailPrice(min, max) {
    const currency = detailReference.value.currency;
    return min === max ? money(min, currency) : money(min, currency) + ' – ' + money(max, currency);
}
const sent = computed(() => page.url.includes('enviado=1') || props.data.referenceKey === 'contacto-enviado');
const clear = () => { query.value = ''; category.value = 'Todos'; categorySearch.value = ''; };
</script>
<template>
    <!-- Los filtros se generan a partir de los servicios publicados y no alteran sus precios. -->
    <section v-if="data.screen === 'servicios'" class="page shell catalog-page public-typography">
        <!-- La introducción orienta sin desplazar los servicios por debajo del primer pantallazo. -->
        <header class="catalog-intro">
            <T class="public-page-heading" eyebrow="Catálogo de servicios" title="Dale forma a tu próximo proyecto." description="Encontrá el servicio que necesitás, explorá su alcance y prepará tu solicitud de presupuesto." />
        </header>
        <div class="catalog-tools">
            <div class="catalog-tools-row">
                <F v-model="query" type="search" label="Buscar un servicio" placeholder="Ej. tienda online o auditoría SEO" />
                <!-- La lista buscable evita filas de chips cuando hay muchas categorías. -->
                <div class="category-picker"><label :for="categoryInputId">Categoría</label>
                    <VAutocomplete :id="categoryInputId" :model-value="category" v-model:search="categorySearch" :items="categories" item-title="label" item-value="value" aria-label="Categoría"
                        variant="outlined" density="compact" color="primary" hide-details clearable :custom-filter="filterCategory" :menu-props="{ maxHeight: 260 }"
                        no-data-text="No encontramos esa categoría." @update:model-value="category = $event ?? 'Todos'" />
                </div>
                <button type="button" class="clear-catalog" :disabled="!query && category === 'Todos'" @click="clear">Limpiar filtros</button>
            </div>
        </div>
        <div v-if="services.length && data.referenceKey !== 'catalogo-vacio'" class="service-grid">
            <v-card v-for="service in services" :key="service.id" class="card service-card" tag="article">
                <div class="service-category">
                    <I :name="categoryIcons[service.category] ?? 'folder'" class="catalog-service-icon" />
                    <p class="eyebrow">{{ service.category }}</p>
                </div>
                <h2>{{ service.name }}</h2>
                <p class="service-description">{{ catalogBenefit(service) }}</p>
                <ul v-if="service.includes?.length" class="service-deliverables" aria-label="Incluye">
                    <li v-for="item in service.includes.slice(0, 3)" :key="item"><I name="check" /><span>{{ item }}</span></li>
                </ul>
                <CatalogServicePrice v-model="catalogSegments[service.id]" :reference="data.references?.[service.id]" :service-name="service.name" />
                <div class="card-link"><B compact :href="serviceHref(service)" :aria-label="'Ver detalles de ' + service.name" arrow>Ver detalles</B></div>
            </v-card>
        </div>        <Empty v-else title="No encontramos servicios con esos filtros." text="Probá otra búsqueda o volvé a ver todas las propuestas."><B @click="clear">Limpiar filtros</B></Empty>
        <div class="notice"><I name="info" /><p>Cada proyecto tiene su propio alcance. Los entregables, el precio y las condiciones se definen en una propuesta profesional.</p></div>
    </section>

    <!-- La ficha reúne el alcance y la modalidad de presupuesto de cada servicio. -->
    <section v-else-if="data.screen === 'servicio'" class="page shell service-detail public-editorial public-typography">
        <B href="/plataforma" variant="ghost" compact class="project-back">← Volver al catálogo</B>
        <header class="service-intro">
            <div>
                <I :name="categoryIcons[selected.category] ?? 'folder'" class="editorial-icon" />
                <T class="public-page-heading" :eyebrow="selected.category" :title="selected.name" :description="detailIntro" />
            </div>
            <img class="service-image" :src="detailImage.src" :alt="detailImage.alt" width="1200" height="800" fetchpriority="high">
        </header>

        <!-- La descripción completa conserva los saltos de línea ingresados en Administración. -->
        <section v-if="detailDescription" class="service-description-section" aria-labelledby="service-description-title">
            <h2 id="service-description-title">Descripción del servicio</h2>
            <p class="service-description text-block">{{ detailDescription }}</p>
        </section>

        <!-- Los entregables tienen su propia tarjeta y el precio distingue referencias de cotizaciones manuales. -->
        <div class="service-detail-grid">
            <article v-if="selected.includes?.length" class="editorial-panel service-includes">
                <h2>Qué incluye</h2>
                <ul class="service-deliverables">
                    <li v-for="item in selected.includes" :key="item"><I name="check" />{{ item }}</li>
                </ul>
            </article>

            <section class="editorial-panel detail-pricing" aria-labelledby="service-pricing-title">
                <h2 id="service-pricing-title">{{ detailReference ? 'Precio orientativo' : 'Presupuesto a medida' }}</h2>
                <template v-if="detailReference">
                    <p v-if="detailReference.segments?.length">Elegí el tipo de cliente para ver el importe en {{ detailReference.currency }}.</p>
                    <div v-if="detailReference.segments?.length" class="detail-price-grid" role="group" aria-label="Precio por tipo de cliente">
                        <button v-for="price in detailPrices" :key="price.label" type="button" class="detail-price-option"
                            :aria-pressed="price.label === chosenPrice?.label" @click="detailSegment = price.label">
                            <span class="detail-price-label">{{ price.label }} <I v-if="price.label === chosenPrice?.label" name="check" /></span>
                            <strong>{{ detailPrice(price.min, price.max) }}</strong>
                            <span v-if="segmentDescriptions[price.label]" class="detail-price-description">{{ segmentDescriptions[price.label] }}</span>
                        </button>
                    </div>
                    <p v-else-if="chosenPrice" class="detail-base-price"><strong>{{ detailPrice(chosenPrice.min, chosenPrice.max) }}</strong> {{ detailReference.currency }}</p>
                    <p class="detail-price-date">Valores vigentes desde {{ date(detailReference.date) }}.</p>
                    <div class="detail-price-info">
                        <I name="info" />
                        <p>El importe final depende del alcance, los entregables y los plazos acordados. Se confirma en el presupuesto formal.</p>
                    </div>
                </template>
                <p v-else>Contanos qué necesitás. Revisamos tu objetivo y preparamos una propuesta con entregables, precio, plazo y condiciones.</p>
                <div class="editorial-actions detail-actions">
                    <B v-if="!$page.props.auth?.is_admin || data.preview" :href="detailReference ? estimateHref : '/solicitudes/nueva?servicio=' + encodeURIComponent(selected.id)" arrow>{{ detailReference ? 'Iniciar estimación' : 'Solicitar presupuesto' }}</B>
                    <B href="/contacto" variant="secondary">Hacer una consulta</B>
                </div>
            </section>
        </div>
    </section>
    <!-- Las imágenes se sirven localmente y cada tarjeta abre el trabajo correspondiente. -->
    <!-- La grilla se adapta a los trabajos publicados y cada ficha mantiene su propia portada. -->
    <section v-else-if="data.screen === 'portfolio'" class="page shell public-editorial public-typography">
        <T class="public-page-heading" eyebrow="Portfolio" title="Proyectos de synaux" description="Conocé el desafío, las decisiones y el resultado de cada trabajo." />
        <div v-if="data.works?.length" class="project-grid">
            <a v-for="(work, index) in data.works" :key="work.id" class="project-card" :href="data.preview ? '/prototipo/trabajo' : `/trabajos/${work.id}`">
                <div class="project-image">
                    <img v-if="work.cover_path" :src="work.cover_path" alt="" :loading="index < 3 ? 'eager' : 'lazy'">
                    <I v-else name="folder" />
                </div>
                <div class="project-copy">
                    <p class="eyebrow">{{ work.category }}</p>
                    <h2>{{ work.title }}</h2>
                    <p v-if="work.description" class="project-description">{{ work.description }}</p>
                    <span class="project-action">Ver proyecto <I name="arrow" /></span>
                </div>
            </a>
        </div>
        <div v-else class="editorial-panel portfolio-empty">
            <I name="folder" class="editorial-icon" />
            <h2>Todavía no hay proyectos publicados</h2>
            <p>Mientras tanto, podés explorar los servicios o contarnos qué necesitás.</p>
            <div class="editorial-actions"><B href="/plataforma" arrow>Explorar servicios</B><B href="/contacto" variant="secondary">Contanos tu proyecto</B></div>
        </div>
        <p v-if="data.preview" class="photo-credit">Fotografías de Airlangga Jati, Creatvise y Branislav Rodman en Unsplash.</p>
    </section>

    <!-- El caso de portfolio separa los datos del proyecto de su historia. -->
    <section v-else-if="data.screen === 'trabajo'" class="page shell public-editorial public-typography">
        <B href="/portfolio" variant="ghost" compact class="project-back">← Volver al portfolio</B>
        <T class="public-page-heading" :eyebrow="selected.category" :title="selected.title" :description="selected.description" />
        <div v-if="selected.cover_path" class="case-cover"><img :src="selected.cover_path" :alt="selected.title"></div>
        <div class="editorial-split">
            <article class="case-story">
                <template v-if="selected.challenge && selected.challenge !== selected.description"><h2>El desafío</h2><p class="text-block">{{ selected.challenge }}</p></template>
                <template v-if="selected.result"><h2>La solución</h2><p class="text-block">{{ selected.result }}</p></template>
                <div v-if="selected.images?.length" class="case-gallery"><img v-for="image in selected.images" :key="image.id" :src="image.image_path" :alt="image.alt_text ?? ''" loading="lazy"></div>
                <B href="/contacto" arrow>Hablemos de tu proyecto</B>
            </article>
            <aside class="editorial-panel case-facts">
                <h2>Ficha del proyecto</h2>
                <dl><div><dt>Área</dt><dd>{{ selected.category }}</dd></div><div v-if="selected.year"><dt>Año</dt><dd>{{ selected.year }}</dd></div></dl>
                <B v-if="selected.live_url" :href="selected.live_url" variant="secondary">Visitar proyecto</B>
            </aside>
        </div>
    </section>

    <!-- La presentación explica cómo trabaja synaux con los recursos visuales de la landing. -->
    <section v-else-if="data.screen === 'perfil'" class="page shell public-editorial public-typography">
        <div class="about-hero">
            <div>
                <T class="public-page-heading" eyebrow="Nosotros" title="De la estrategia a la entrega" description="En synaux conectamos estrategia, diseño y desarrollo para que puedas avanzar con un objetivo y un alcance claros." />
                <div class="editorial-actions"><B href="/contacto" arrow>Hablemos de tu proyecto</B><B href="/portfolio" variant="secondary">Ver portfolio</B></div>
            </div>
            <figure class="about-image"><img src="/images/landing/planificacion.webp" alt="Planificación de un sitio web con bocetos y una computadora."></figure>
        </div>
        <div class="about-principles">
            <h2>Cómo trabajamos</h2>
            <div class="principles-grid">
                <article class="editorial-panel"><I name="user" class="editorial-icon" /><h3>Partimos de tu objetivo</h3><p>Revisamos qué querés resolver, qué recursos ya tenés y qué resultado esperás.</p></article>
                <article class="editorial-panel"><I name="file" class="editorial-icon" /><h3>Acordamos el alcance</h3><p>La propuesta reúne los entregables, el precio y las condiciones para que puedas revisarlos antes de decidir.</p></article>
                <article class="editorial-panel"><I name="check" class="editorial-icon" /><h3>Dejamos registro del avance</h3><p>Desde tu cuenta podés consultar el presupuesto y seguir los próximos pasos del proyecto.</p></article>
            </div>
        </div>
    </section>

    <!-- Contacto separa la consulta general del recorrido para pedir un presupuesto. -->
    <section v-else-if="data.screen === 'contacto'" class="page shell public-editorial public-typography">
        <T class="public-page-heading" eyebrow="Contacto" title="Hablemos de tu proyecto" description="Si todavía estás definiendo qué necesitás, contanos tu idea y te orientamos sobre cómo empezar." />
        <div class="editorial-split">
            <div class="editorial-panel contact-form-panel">
                <div v-if="sent" class="contact-success" role="status">
                    <I name="check" class="editorial-icon" />
                    <h2>Recibimos tu consulta</h2>
                    <p>Tu mensaje quedó registrado para su revisión.</p>
                    <B href="/contacto" variant="secondary">Enviar otra consulta</B>
                </div>
                <form v-else aria-labelledby="contact-form-title" @submit.prevent="submit(form, '/contacto', {previewTarget:'contacto-enviado'})">
                    <h2 id="contact-form-title">Dejanos tu consulta</h2>
                    <div class="field-row">
                        <F v-model="form.name" label="Nombre" required autocomplete="name" :error="form.errors.name" />
                        <F v-model="form.email" label="Correo" type="email" required autocomplete="email" :error="form.errors.email" />
                    </div>
                    <F v-model="form.topic" label="Tema" type="select" :options="['Consulta general', ...data.services.map(s => s.name)]" required :error="form.errors.topic" />
                    <F v-model="form.message" label="Mensaje" type="textarea" required minlength="10" maxlength="10000" hint="No incluyas información sensible." placeholder="¿Qué querés lograr? Si tenés una fecha en mente, contanos cuál." :error="form.errors.message" />
                    <B type="submit" :disabled="form.processing" arrow>{{ form.processing ? 'Enviando…' : 'Enviar consulta' }}</B>
                </form>
            </div>
            <aside class="editorial-panel contact-next">
                <I name="file" class="editorial-icon" />
                <h2>¿Ya sabés qué servicio necesitás?</h2>
                <p>Elegí un servicio y prepará una solicitud para que podamos cotizar tu proyecto.</p>
                <B v-if="!$page.props.auth?.is_admin || data.preview" href="/solicitudes/nueva" variant="secondary" arrow>Preparar mi solicitud</B>
            </aside>
        </div>
    </section>
</template>


<style scoped>
/* La búsqueda, las categorías y la limpieza comparten un panel con estados de foco visibles. */
/* El encabezado queda liviano: la profundidad visual se reserva para las tarjetas de servicio. */
.catalog-page { padding-top: 32px; }
.catalog-intro { padding: 0; margin-bottom: 20px; }
.catalog-intro :deep(.page-title) { display: block; margin-bottom: 0; }
.catalog-intro :deep(.page-title > div) { width: 100%; }
.catalog-page .catalog-tools { background: #f5f9fa; padding: 14px 18px; margin-bottom: 24px; border-color: #1f6f7f22; box-shadow: 0 2px 8px #153b640a; }
.catalog-page .catalog-tools-row { grid-template-columns: repeat(2, minmax(0,1fr)) auto; align-items: end; gap: 1rem; }
/* El buscador y el autocompletado comparten altura y etiquetas alineadas. */
.category-picker { display: grid; gap: 5px; min-width: 0; }
.category-picker > label { font-size: .8rem; font-weight: 600; color: #153b64; }
.category-picker :deep(.v-field) { border-radius: 8px; background: white; }
.category-picker :deep(.v-field input) { min-height: 0; border: 0; border-radius: 0; background: transparent; padding: 0; padding-inline: inherit; outline: none; }
.catalog-page .clear-catalog { min-height: 40px; align-self: end; white-space: nowrap; }
/* El filtro ocupa menos alto; el buscador y las categorías conservan sus etiquetas y foco visible. */
.catalog-page .catalog-tools :deep(.field) { gap: 5px; font-size: .8rem; }
.catalog-page .catalog-tools :deep(input[type='search']) { min-height: 40px; padding: 7px 10px; font-size: .875rem; }
@media (pointer: coarse) { .catalog-page .clear-catalog, .catalog-page .catalog-tools :deep(input[type='search']) { min-height: 44px; } }
.clear-catalog { min-height: 32px; padding: .3rem .65rem; border: 1px solid #1f6f7f; border-radius: .5rem; background: #fff; color: #1f6f7f; font: inherit; font-size: .875rem; font-weight: 700; cursor: pointer; }
/* La acción mantiene su lugar para evitar desplazamientos al aplicar o quitar filtros. */
.clear-catalog:hover:not(:disabled) { background: #f5f9fa; }
.clear-catalog:disabled { opacity: .5; cursor: default; }
.catalog-page button:focus-visible { outline: 2px solid #153b64; outline-offset: 3px; }

/* Cada tarjeta mantiene categoría, alcance y precio en un orden común para facilitar la comparación. */
.catalog-page .service-card { min-height: 0; padding: 1.25rem; border: 1px solid #1f6f7f26; border-top: 4px solid #2db3a3; box-shadow: 0 2px 5px #153b640a, 0 8px 18px #153b6412; border-radius: 18px; }
.catalog-page .service-card:focus-within { border-color: #1f6f7f; box-shadow: 0 4px 14px #153b641a; }
@media (hover: hover) { .catalog-page .service-card:hover { box-shadow: 0 4px 14px #153b641a; border-color: #1f6f7f66; border-top-color: #2db3a3; } }
.catalog-page .service-category { padding-bottom: 12px; border-bottom: 1px solid #1f6f7f1f; margin-bottom: 12px; }
.catalog-page .catalog-service-icon { box-sizing: content-box; width: 22px; height: 22px; padding: 9px; border-radius: 10px; color: #153b64; background: #2db3a326; }
.service-category { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; }
.catalog-page .service-category .eyebrow { margin: 0; color: #1f6f7f; font-size: .75rem; letter-spacing: .08em; }
.catalog-service-icon { width: 2rem; height: 2rem; color: #1f6f7f; flex-shrink: 0; }
.service-description { color: #446075; }
.service-deliverables { display: grid; gap: .3rem; margin: .25rem 0 .5rem; padding: 0; list-style: none; }
.service-deliverables li { display: flex; align-items: start; gap: .5rem; font-size: .875rem; color: #446075; }
.service-deliverables .icon { width: 1.1rem; height: 1.1rem; margin-top: .15rem; color: #1f6f7f; flex-shrink: 0; }
.catalog-page .service-card .card-link { margin-top: .75rem; }
.catalog-page .service-card .card-link .btn { width: 100%; padding-inline: .9rem; }
@media (max-width: 1000px) { .catalog-page .catalog-tools-row { grid-template-columns: minmax(0,1fr); gap: 10px; } .catalog-page .clear-catalog { justify-self: end; } }
@media (max-width: 600px) { .catalog-page .catalog-tools { padding: 14px; } .catalog-page .service-card { padding: 1.15rem; } }

/* La ficha usa la escala pública y une los entregables con el siguiente paso. */
.service-intro { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); align-items: center; gap: clamp(20px, 4vw, 48px); margin-bottom: 22px; }
.service-detail .service-intro :deep(.public-page-heading) { margin-bottom: 0; }
.service-image { display: block; width: 100%; height: auto; aspect-ratio: 2 / 1; object-fit: cover; border: 1px solid #1f6f7f26; border-radius: 16px; box-shadow: 0 6px 20px #153b6412; }
/* Un ancho de lectura cómodo permite publicar descripciones largas sin recortarlas. */
.service-description-section { max-width: 75ch; margin-bottom: clamp(22px, 4vw, 36px); }
.service-description-section p { margin: 0; }
.service-detail-grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 8fr); align-items: start; gap: clamp(18px, 3vw, 32px); }
.detail-pricing:only-child { grid-column: 1 / -1; }
.service-includes .service-deliverables { margin: 0; gap: 12px; }
.service-detail .service-includes li { font-size: 1rem; text-wrap: pretty; }
.detail-pricing { border-top: 3px solid #2db3a3; }
.detail-price-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-block: 16px; }
.detail-price-option { display: flex; flex-direction: column; gap: 6px; min-width: 0; text-align: left; font: inherit; color: #153b64; padding: 12px; border: 1px solid #1f6f7f33; border-radius: 10px; background: #fff; cursor: pointer; }
.detail-price-label { display: flex; align-items: center; justify-content: space-between; gap: 6px; width: 100%; font-weight: 700; }
.detail-price-label .icon { width: 16px; height: 16px; color: #1f6f7f; }
.detail-price-option strong, .detail-base-price strong { font-family: 'Space Grotesk', sans-serif; font-size: 1.2rem; line-height: 1.25; overflow-wrap: anywhere; }
.detail-price-description { color: #446075; font-size: .875rem; text-wrap: pretty; }
.detail-price-option:hover, .detail-price-option[aria-pressed='true'] { border-color: #1f6f7f; }
.detail-price-option[aria-pressed='true'] { background: #2db3a31a; }
.detail-price-option:focus-visible { outline: 2px solid #153b64; outline-offset: 2px; }
.service-detail .detail-pricing .detail-price-date { font-size: .875rem; margin-bottom: 12px; }
.detail-price-info { display: flex; align-items: start; gap: 10px; padding: 12px; background: #f5f9fa; border-radius: 10px; }
.detail-price-info .icon { flex-shrink: 0; color: #1f6f7f; }
.detail-price-info p { margin: 0; }
.detail-actions { margin-top: 18px; }
@media (max-width: 900px) {
    .service-detail-grid { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 620px) {
    .service-intro, .detail-price-grid { grid-template-columns: minmax(0, 1fr); }
}

/* Las páginas públicas comparten la escala del catálogo y las tipografías de marca. */
.public-typography { font-family: 'Nunito', sans-serif; }
.public-typography :deep(.public-page-heading .eyebrow) {
    margin: 0 0 8px;
    color: #1f6f7f;
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .1em;
}
.public-typography :deep(.public-page-heading h1) {
    max-width: none;
    margin: 0 0 10px;
    font-family: 'Space Grotesk', sans-serif;
    font-size: clamp(1.75rem, 2.8vw, 2.25rem);
    font-weight: 700;
    line-height: 1.15;
    text-wrap: balance;
}
.public-typography :deep(.public-page-heading p:not(.eyebrow)),
.public-typography :is(.service-description, .project-description, .editorial-panel p, .case-story p) {
    color: #446075;
    font-size: 1rem;
    line-height: 1.55;
    text-wrap: pretty;
}
.public-typography :is(h2, h3) {
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    line-height: 1.25;
    text-wrap: balance;
}
.public-typography h2 { font-size: 1.5rem; margin: 0 0 12px; }
.public-typography :is(.service-card h2, .project-card h2, .principles-grid h3) {
    font-size: 1.25rem;
    margin: 0 0 8px;
}
.catalog-page .service-description { margin-bottom: 12px; }

/* La composición conserva márgenes propios según el contenido de cada página. */
.public-editorial { padding-block: clamp(32px, 5vw, 64px); }
.public-editorial :deep(.public-page-heading) { margin-bottom: 28px; }
.editorial-panel, .project-card { border: 1px solid #1f6f7f26; border-radius: 16px; background: #fff; box-shadow: 0 4px 16px #153b640d; }
.editorial-panel { padding: clamp(20px, 3vw, 32px); min-width: 0; }
.editorial-panel p:last-child { margin-bottom: 0; }
.editorial-icon { box-sizing: content-box; width: 20px; height: 20px; padding: 10px; border-radius: 10px; background: #2db3a326; color: #1f6f7f; margin-bottom: 14px; }
.editorial-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.editorial-split { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items: start; gap: clamp(18px, 3vw, 32px); }

/* Las fichas mantienen un tamaño útil con pocos o muchos proyectos. */
.project-grid, .principles-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 270px), 1fr)); gap: 20px; }
.project-grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); }
.project-card { display: flex; flex-direction: column; overflow: hidden; text-decoration: none; color: #153b64; }
.project-card:hover, .project-card:focus-visible { border-color: #1f6f7f; box-shadow: 0 8px 22px #153b641a; }
.project-image { display: grid; place-items: center; aspect-ratio: 16 / 10; overflow: hidden; background: #f5f9fa; color: #1f6f7f; }
.project-image img, .case-cover img, .about-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
.project-image > .icon { width: 32px; height: 32px; }
.project-copy { display: flex; flex-direction: column; flex: 1; padding: 20px; overflow-wrap: anywhere; }
.project-copy .eyebrow { margin-bottom: 6px; }
.project-description { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }
.project-action { display: inline-flex; align-items: center; gap: 8px; margin-top: auto; font-weight: 700; color: #1f6f7f; }
.portfolio-empty { border-top: 3px solid #2db3a3; }
.project-back { margin-bottom: 16px; }
.case-cover, .about-image { overflow: hidden; border-radius: 16px; border: 1px solid #1f6f7f26; box-shadow: 0 6px 20px #153b6412; }
.case-cover { max-height: 520px; margin-bottom: 28px; }
.case-cover img { max-height: 520px; object-fit: contain; background: #f5f9fa; }
.case-story { min-width: 0; overflow-wrap: anywhere; }
.case-facts dl { margin: 0 0 16px; }
.case-facts dl > div { padding-block: 8px; border-bottom: 1px solid #1f6f7f22; }
.case-facts dt { font-size: .875rem; color: #446075; }
.case-facts dd { margin: 0; font-weight: 700; }
.case-gallery { display: grid; gap: 16px; margin-bottom: 24px; }
.case-gallery img { width: 100%; border-radius: 12px; }

/* La fotografía acompaña la presentación y la tarjeta de contacto conserva la acción principal. */
.about-hero { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: center; gap: clamp(24px, 5vw, 56px); }
.about-image { aspect-ratio: 3 / 2; margin: 0; }
.about-principles { margin-top: clamp(28px, 5vw, 60px); }
.principles-grid .editorial-panel { border-top: 3px solid #2db3a3; }
.contact-form-panel { border-top: 3px solid #2db3a3; }
.contact-form-panel h2 { margin-bottom: 20px; }
.contact-next { background: #f5f9fa; }
@media (max-width: 820px) {
    .editorial-split, .about-hero { grid-template-columns: minmax(0, 1fr); }
}
</style>
