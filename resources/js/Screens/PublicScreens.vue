<script setup>
import { computed, ref } from 'vue';
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
const submit = useSubmit();
const form = useForm({ name: '', email: '', topic: 'Consulta general', message: '' });
const categories = computed(() => ['Todos', ...new Set(props.data.services.map(service => service.category))]);

// El catálogo utiliza los mismos iconos que la landing y asigna una composición propia a UX/UI.
const categoryIcons = { 'Desarrollo web': 'web', Diseño: 'palette', Marketing: 'megaphone', 'UX/UI': 'layout' };
const categoryCounts = computed(() => {
    const counts = { Todos: props.data.services.length };
    for (const item of props.data.services) counts[item.category] = (counts[item.category] ?? 0) + 1;
    return counts;
});

// La búsqueda admite palabras en cualquier orden, con o sin tildes, y respeta el filtro activo.
const normalizeSearch = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
const searchWords = computed(() => normalizeSearch(query.value).trim().split(/\s+/).filter(Boolean));
const services = computed(() => props.data.services.filter(service => {
    if (category.value !== 'Todos' && service.category !== category.value) return false;

    const searchableText = normalizeSearch([service.name, service.category, service.summary].join(' '));
    return searchWords.value.every(word => searchableText.includes(word));
}));
const resultsLabel = computed(() => services.value.length === 1 ? '1 resultado' : `${services.value.length} resultados`);
const selected = computed(() => props.data.selected ?? {});
// El detalle reutiliza los importes publicados del catálogo, con todos los segmentos visibles.
const detailReference = computed(() => props.data.references?.[selected.value.id]);
const detailPrices = computed(() => detailReference.value?.segments?.length
    ? detailReference.value.segments
    : detailReference.value ? [{ label: 'Alcance base', min: detailReference.value.min, max: detailReference.value.max }] : []);
const segmentDescriptions = {
    Particular: 'Personas, organizaciones sin fines de lucro y ONG',
    PyME: 'PyMEs e instituciones',
    Empresa: 'Grandes empresas',
};
function detailPrice(min, max) {
    const currency = detailReference.value.currency;
    return min === max ? money(min, currency) : money(min, currency) + ' – ' + money(max, currency);
}
const sent = computed(() => page.url.includes('enviado=1') || props.data.referenceKey === 'contacto-enviado');
const clear = () => { query.value = ''; category.value = 'Todos'; };
</script>
<template>
    <!-- Los filtros se generan a partir de los servicios publicados y el contador anuncia cada cambio. -->
    <section v-if="data.screen === 'servicios'" class="page shell catalog-page">
        <T eyebrow="Catálogo de servicios" title="Encontrá por dónde empezar." description="Explorá las propuestas y conocé qué puede aportar cada servicio a tu próximo proyecto digital." />
        <div class="catalog-tools">
            <div class="catalog-tools-row">
                <F v-model="query" type="search" label="Buscar un servicio" placeholder="Ej. tienda online o auditoría SEO" />
                <div class="filter-row" role="group" aria-label="Filtrar servicios">
                    <button v-for="item in categories" :key="item" type="button" :class="{active:category === item}" :aria-pressed="category === item" @click="category = item">
                        {{ item }} <span class="category-count">{{ categoryCounts[item] }}</span>
                    </button>
                </div>
            </div>
            <div class="catalog-results-bar">
                <p class="catalog-count" aria-live="polite">{{ resultsLabel }}{{ category !== 'Todos' ? ' en ' + category : '' }}</p>
                <button v-if="query || category !== 'Todos'" type="button" class="clear-catalog" @click="clear">Limpiar filtros</button>
            </div>
        </div>
        <div v-if="services.length && data.referenceKey !== 'catalogo-vacio'" class="service-grid">
            <v-card v-for="service in services" :key="service.id" class="card service-card" tag="article">
                <div class="service-category">
                    <I :name="categoryIcons[service.category] ?? 'folder'" class="catalog-service-icon" />
                    <p class="eyebrow">{{ service.category }}</p>
                </div>
                <h2>{{ service.name }}</h2>
                <p class="service-description">{{ service.summary }}</p>
                <ul v-if="service.includes?.length" class="service-deliverables" aria-label="Incluye">
                    <li v-for="item in service.includes.slice(0, 3)" :key="item"><I name="check" /><span>{{ item }}</span></li>
                </ul>
                <CatalogServicePrice :reference="data.references?.[service.id]" :service-name="service.name" />
                <div class="card-link"><B :href="'/servicios/' + service.id" :aria-label="'Ver detalles de ' + service.name" arrow>Ver detalles</B></div>
            </v-card>
        </div>        <Empty v-else title="No encontramos servicios con esos filtros." text="Probá otra búsqueda o volvé a ver todas las propuestas."><B @click="clear">Limpiar filtros</B></Empty>
        <div class="notice"><I name="info" /><p>Cada proyecto tiene su propio alcance. Los entregables, el precio y las condiciones se definen en una propuesta profesional.</p></div>
    </section>

    <!-- Cada detalle conserva el servicio elegido; sus entregables no se confunden con un contrato. -->
    <section v-else-if="data.screen === 'servicio'" class="page shell service-detail">
        <B href="/plataforma" variant="ghost" class="back">← Volver a servicios</B>
        <header class="service-intro">
            <p class="eyebrow">{{ selected.category }}</p>
            <h1>{{ selected.name }}</h1>
            <p class="lead">{{ selected.description }}</p>
        </header>

        <!-- Los tres precios permiten comparar el alcance sin salir de esta página. -->
        <v-card class="card detail-pricing">
            <div class="section-head">
                <h2>Precios estimativos por cliente</h2>
                <span v-if="detailReference">Moneda: {{ detailReference.currency }}</span>
            </div>
            <div v-if="detailPrices.length" class="detail-price-grid">
                <article v-for="price in detailPrices" :key="price.label" class="detail-price-option">
                    <h3>{{ price.label }}</h3>
                    <strong>{{ detailPrice(price.min, price.max) }}</strong>
                    <p v-if="segmentDescriptions[price.label]">{{ segmentDescriptions[price.label] }}</p>
                </article>
            </div>
            <div v-else class="soft-panel"><h3>Presupuesto a medida</h3><p>Contanos qué necesitás para definir los entregables y el importe de tu proyecto.</p></div>
            <p v-if="detailReference" class="detail-price-date">Valores vigentes desde {{ date(detailReference.date) }}.</p>
            <div class="detail-price-info">
                <I name="info" />
                <p>Los precios son orientativos. El importe final puede variar según la complejidad, los entregables, los plazos y las revisiones acordadas. Synaux confirma el alcance y las condiciones en el presupuesto formal.</p>
            </div>
            <div class="button-row">
                <B :href="'/estimador?servicio=' + selected.id" arrow>Estimar mi proyecto</B>
                <B href="/contacto" variant="secondary">Hacer una consulta</B>
            </div>
        </v-card>

        <div class="content-grid">
            <article><h2>Qué incluye</h2><ul class="check-list"><li v-for="item in selected.includes" :key="item"><I name="check" />{{ item }}</li></ul></article>
            <aside class="soft-panel"><h2>Cómo seguimos</h2><p>Completá la estimación y enviá tu solicitud privada. Synaux revisará tus necesidades y preparará una propuesta con precio, plazo y condiciones.</p></aside>
        </div>
    </section>
    <!-- Las imágenes se sirven localmente y cada tarjeta abre el trabajo correspondiente. -->
    <section v-else-if="data.screen === 'portfolio'" class="page shell">
        <T eyebrow="Portfolio" title="Trabajo real, decisiones con intención" description="Una selección de proyectos en los que estrategia, diseño y tecnología avanzaron juntos." />
        <div v-if="data.works?.length" class="portfolio-grid"><a v-for="(work, index) in data.works" :key="work.id" :href="data.preview ? '/prototipo/trabajo' : `/trabajos/${work.id}`" :class="['work-card', `work-${index % 3}`]"><img v-if="work.cover_path" :src="work.cover_path" :alt="work.title"><span><small>{{ work.category }}</small><strong>{{ work.title }}</strong><i><I name="arrow" /></i></span></a></div>
        <Empty v-else title="Estamos preparando nuestro portfolio." text="Próximamente vas a poder conocer los trabajos publicados por Synaux." href="/contacto" action="Contanos tu proyecto" />
        <p v-if="data.preview" class="photo-credit">Fotografías de Airlangga Jati, Creatvise y Branislav Rodman en Unsplash.</p>
    </section>
    <section v-else-if="data.screen === 'trabajo'" class="page shell">
        <B href="/portfolio" variant="ghost" class="back">← Volver al portfolio</B><T :eyebrow="selected.category" :title="selected.title" :description="selected.description" />
        <div v-if="selected.cover_path" class="work-cover"><img :src="selected.cover_path" :alt="selected.title"></div>
        <div class="story"><aside><strong>Servicio</strong><p>{{ selected.category }}</p><strong>Año</strong><p>{{ selected.year ?? 'A definir' }}</p><B v-if="selected.live_url" :href="selected.live_url" variant="secondary">Visitar proyecto</B></aside><article><h2>El desafío</h2><p class="text-block">{{ selected.challenge || selected.description }}</p><h2>La solución</h2><p class="text-block">{{ selected.result || 'Las decisiones y los entregables de este trabajo se documentan en su presentación.' }}</p><div class="gallery"><img v-for="image in selected.images ?? []" :key="image.id" :src="image.image_path" :alt="image.alt_text"></div><B href="/contacto" arrow>Hablemos de tu proyecto</B></article></div>
    </section>

    <!-- La presentación de Synaux utiliza la identidad actual y las especialidades del prototipo. -->
    <section v-else-if="data.screen === 'perfil'" class="page shell narrow">
        <div class="profile-hero"><div class="profile-mark">{{ data.preview ? 'RS' : 'S' }}</div><div><p class="eyebrow">Quién está detrás de Synaux</p><h1>{{ data.preview ? 'Ricardo Salvatierra' : 'Synaux' }}</h1><p class="lead">Profesional digital enfocado en conectar estrategia, diseño y tecnología para crear soluciones que se entienden.</p></div></div>
        <div class="content-grid"><article><h2>Trabajo cercano, proceso profesional</h2><p>Synaux nace para ofrecer una forma más transparente de abordar proyectos digitales. Cada decisión se vincula con un objetivo, cada propuesta explicita su alcance y cada proyecto conserva la historia de lo acordado.</p><h2>Especialidades</h2><div class="tag-list"><span>Estrategia digital</span><span>UX/UI</span><span>Desarrollo web</span><span>Sistemas de diseño</span></div></article><v-card class="card summary-card"><h3>¿Hablamos de tu proyecto?</h3><p>Podemos empezar con una estimación o con una conversación si tu necesidad requiere una mirada particular.</p><B href="/contacto">Contactar</B></v-card></div>
    </section>

    <!-- El formulario guarda una consulta real; el estado enviado se muestra solo tras una respuesta válida. -->
    <section v-else-if="data.screen === 'contacto'" class="page shell narrow">
        <T eyebrow="Contacto" title="Contanos qué querés lograr" description="Si todavía no sabés qué servicio necesitás, describí el objetivo. Te orientamos sobre el próximo paso." />
        <div class="contact-grid"><v-card class="card form-card"><div v-if="sent" class="success-state"><I name="check" /><h2>Recibimos tu consulta</h2><p>Tu mensaje quedó registrado para que podamos revisar tu necesidad.</p><B href="/contacto" variant="secondary">Enviar otra consulta</B></div><form v-else @submit.prevent="submit(form, '/contacto', {previewTarget:'contacto-enviado'})"><div class="field-row"><F v-model="form.name" label="Nombre" required autocomplete="name" :error="form.errors.name" /><F v-model="form.email" label="Correo" type="email" required autocomplete="email" :error="form.errors.email" /></div><F v-model="form.topic" label="Tema" type="select" :options="['Consulta general', ...data.services.map(s => s.name)]" required :error="form.errors.topic" /><F v-model="form.message" label="¿En qué podemos ayudarte?" type="textarea" required hint="No incluyas información sensible." placeholder="Contanos brevemente sobre tu proyecto, objetivos y tiempos." :error="form.errors.message" /><B type="submit" :disabled="form.processing">Enviar consulta</B></form></v-card><aside class="contact-aside"><h3>Hablemos de tu proyecto</h3><p>Contanos el objetivo, el contexto y los tiempos que tenés en mente.</p><hr><p>Si ya identificaste el servicio que necesitás, podés comenzar con el estimador.</p><B href="/estimador" variant="secondary">Estimar mi proyecto</B></aside></div>
    </section>
</template>


<style scoped>
/* La búsqueda, las categorías y la limpieza comparten un panel con estados de foco visibles. */
.catalog-page .catalog-tools { background: #f5f9fa; padding: 1.5rem; }
.catalog-page .catalog-tools-row { grid-template-columns: minmax(220px,.8fr) minmax(0,1.6fr); align-items: center; gap: 1.5rem; }
.catalog-page .filter-row button { display: inline-flex; align-items: center; gap: .5rem; font-family: 'Nunito',sans-serif; font-size: .875rem; min-height: 44px; padding: .55rem .85rem; font-weight: 600; border-color: #446075; }
.category-count { font-size: .8rem; opacity: .8; }
.catalog-results-bar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-top: 1rem; }
.catalog-page .catalog-count { margin: 0; color: #446075; }
.clear-catalog { min-height: 44px; padding: .5rem .85rem; border: 1px solid #1f6f7f; border-radius: .5rem; background: #fff; color: #1f6f7f; font: inherit; font-size: .875rem; font-weight: 700; cursor: pointer; }
.clear-catalog:hover { background: #f5f9fa; }
.catalog-page .filter-row button:hover:not(.active) { background: #fff; border-color: #153b64; color: #153b64; }
.catalog-page .filter-row button.active { background: #153b64; border-color: #153b64; color: #fff; }
.catalog-page button:focus-visible { outline: 2px solid #153b64; outline-offset: 3px; }

/* Cada tarjeta mantiene categoría, alcance y precio en un orden común para facilitar la comparación. */
.catalog-page .service-card { padding: 1.5rem; border-top: 3px solid #2db3a3; }
.service-category { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; }
.catalog-page .service-category .eyebrow { margin: 0; color: #1f6f7f; font-size: .75rem; letter-spacing: .08em; }
.catalog-service-icon { width: 2rem; height: 2rem; color: #1f6f7f; flex-shrink: 0; }
.catalog-page .service-card h2 { font-weight: 700; line-height: 1.2; margin-bottom: .75rem; }
.service-description { color: #446075; }
.service-deliverables { display: grid; gap: .5rem; margin: .25rem 0 1rem; padding: 0; list-style: none; }
.service-deliverables li { display: flex; align-items: start; gap: .5rem; font-size: .875rem; color: #446075; }
.service-deliverables .icon { width: 1.1rem; height: 1.1rem; margin-top: .15rem; color: #1f6f7f; flex-shrink: 0; }
.catalog-page .service-card .card-link { margin-top: 1rem; }
.catalog-page .service-card .card-link .btn { width: 100%; padding-inline: .9rem; }
@media (max-width: 1000px) { .catalog-page .catalog-tools-row { grid-template-columns: minmax(0,1fr); } }
@media (max-width: 600px) { .catalog-page .catalog-tools, .catalog-page .service-card { padding: 1.15rem; } }

/* La vista independiente agrupa alcance, precios e información con la identidad de Synaux. */
.service-intro { max-width: 850px; margin-block: 1rem 2rem; }
.service-intro h1 { margin-bottom: 1rem; }
.detail-pricing { margin-bottom: 2rem; }
.detail-pricing h2 { margin: 0; }
.detail-price-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-block: 1.5rem; }
.detail-price-option { padding: 1.5rem; border: 1px solid rgb(31 111 127 / 20%); border-radius: .875rem; background: #f5f9fa; }
.detail-price-option h3 { font-size: 1.1rem; margin-bottom: .75rem; }
.detail-price-option strong { display: block; color: #153b64; font-family: 'Space Grotesk', sans-serif; font-size: clamp(1.25rem, 2vw, 1.75rem); overflow-wrap: anywhere; }
.detail-price-option p { margin: .75rem 0 0; color: #446075; font-size: .875rem; }
.detail-price-date { font-size: .875rem; color: #446075; }
.detail-price-info { display: flex; align-items: start; gap: .75rem; margin-block: 1.25rem; padding: 1rem; background: #f5f9fa; border-radius: .75rem; }
.detail-price-info p { margin: 0; }
.service-detail .content-grid { align-items: start; }
.service-detail .content-grid article h2 { margin-top: 0; }
@media (max-width: 700px) {
    .detail-price-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>