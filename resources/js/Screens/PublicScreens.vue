<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import B from '../Components/SynauxButton.vue';
import F from '../Components/SynauxField.vue';
import T from '../Components/SynauxTitle.vue';
import I from '../Components/SynauxIcon.vue';
import Empty from '../Components/SynauxEmpty.vue';
import CatalogServicePrice from '../Components/CatalogServicePrice.vue';
import { useSubmit } from '../support/synaux';
// Pantallas públicas: los datos llegan de Laravel; filtros y estados de interacción pertenecen a Vue.
const props = defineProps({ data: Object });
const page = usePage();
const query = ref('');
const category = ref('Todos');
const submit = useSubmit();
const form = useForm({ name: '', email: '', topic: 'Consulta general', message: '' });
const categories = computed(() => ['Todos', ...new Set(props.data.services.map(service => service.category))]);

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
const sent = computed(() => page.url.includes('enviado=1') || props.data.referenceKey === 'contacto-enviado');
const clear = () => { query.value = ''; category.value = 'Todos'; };
</script>
<template>
    <!-- Los filtros se generan a partir de los servicios publicados y el contador anuncia cada cambio. -->
    <section v-if="data.screen === 'servicios'" class="page shell catalog-page">
        <T eyebrow="Catálogo de servicios" title="Encontrá por dónde empezar." description="Explorá las propuestas y conocé qué puede aportar cada servicio a tu próximo proyecto digital." />
        <div class="catalog-tools"><div class="catalog-tools-row"><F v-model="query" label="Buscar un servicio" placeholder="Ej. tienda online o auditoría SEO" /><div class="filter-row" role="group" aria-label="Filtrar servicios"><button v-for="item in categories" :key="item" :class="{active:category === item}" :aria-pressed="category === item" @click="category = item">{{ item }}</button></div></div><p class="catalog-count" aria-live="polite">{{ resultsLabel }}</p></div>
        <div v-if="services.length && data.referenceKey !== 'catalogo-vacio'" class="service-grid"><v-card v-for="(service, index) in services" :key="service.id" class="card service-card" tag="article"><span v-if="data.preview" class="service-number">0{{ index + 1 }}</span><p class="eyebrow">{{ service.category }}</p><h2>{{ service.name }}</h2><p>{{ service.summary }}</p><CatalogServicePrice :reference="data.references?.[service.id]" :service-name="service.name" /><div class="card-link"><B :href="`/servicios/${service.id}`" variant="ghost" :aria-label="`Ver alcance de ${service.name}`" arrow>Ver alcance</B></div></v-card></div>
        <Empty v-else title="No encontramos servicios con esos filtros." text="Probá otra búsqueda o volvé a ver todas las propuestas."><B @click="clear">Limpiar filtros</B></Empty>
        <div class="notice"><I name="info" /><p>Cada proyecto tiene su propio alcance. Los entregables, el precio y las condiciones se definen en una propuesta profesional.</p></div>
    </section>

    <!-- Cada detalle conserva el servicio elegido; sus entregables no se confunden con un contrato. -->
    <section v-else-if="data.screen === 'servicio'" class="page shell narrow">
        <B href="/plataforma" variant="ghost" class="back">← Volver a servicios</B>
        <div class="detail-hero"><div><p class="eyebrow">{{ selected.category }}</p><h1>{{ selected.name }}</h1><p class="lead">{{ selected.description }}</p><div class="button-row"><B :href="`/estimador?servicio=${selected.id}`" arrow>Estimar proyecto</B><B href="/contacto" variant="secondary">Hacer una consulta</B></div></div><v-card class="card summary-card"><p class="eyebrow">Alcance a medida</p><h3>Una propuesta para cada proyecto</h3><p>Los entregables, el precio y las condiciones se acuerdan después de entender tu necesidad.</p></v-card></div>
        <div class="content-grid"><article><h2>Posibles entregables</h2><ul class="check-list"><li v-for="item in selected.includes" :key="item"><I name="check" />{{ item }}</li></ul><h2>Cómo trabajamos</h2><p>Partimos de tus objetivos, definimos el alcance y convertimos esa definición en un presupuesto formal. La estimación es una referencia inicial: no reemplaza la propuesta que recibirás después de revisar tu solicitud.</p></article><aside class="soft-panel"><I name="info" /><h3>Sobre la estimación</h3><p>El rango se calcula con tus respuestas y sirve para orientarte. No constituye una oferta ni una tarifa oficial.</p></aside></div>
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

