<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onMounted, ref} from 'vue';
import BrandLogo from '../Components/Brand/BrandLogo.vue';
import AppButton from '../Components/UI/AppButton.vue';

const props = defineProps({
    status: {type: Number, required: true},
    accountArea: {type: String, default: null},
});

// Cada estado ofrece una salida segura, con el panel que corresponde a la sesión si ya hay una.
const messages = {
    403: {
        title: 'Tu cuenta no tiene acceso a esta página',
        description: 'Volvé a tu panel para ver las opciones disponibles para tu cuenta.',
    },
    404: {
        title: 'Página no encontrada',
        description: 'Puede que el enlace haya cambiado o que la dirección esté incompleta.',
        action: ['Ir al Inicio', '/inicio'],
    },
};
const accountActions = {
    management: ['Ir a Gestión', '/gestion'],
    client: ['Ir a mi cuenta', '/cliente'],
};
const current = computed(() => messages[props.status]);
const action = computed(() => current.value.action ?? accountActions[props.accountArea] ?? ['Ir al Inicio', '/inicio']);

// Al abrir la pantalla, el lector de pantalla y el teclado parten del título del error.
const heading = ref(null);
onMounted(() => heading.value?.focus({preventScroll: true}));
</script>

<template>
    <Head :title="current.title">
        <meta head-key="robots" name="robots" content="noindex">
    </Head>
    <v-app>
        <main class="access-error">
            <section class="access-error-card" aria-labelledby="access-error-title">
                <BrandLogo :with-slogan="false" class="access-error-brand"/>
                <p class="access-error-status" :data-status="status">Error {{ status }}</p>
                <h1 id="access-error-title" ref="heading" tabindex="-1">{{ current.title }}</h1>
                <p class="access-error-description">{{ current.description }}</p>
                <div class="access-error-actions">
                    <AppButton :href="action[1]" arrow>{{ action[0] }}</AppButton>
                    <AppButton v-if="action[1] !== '/inicio'" href="/inicio" variant="ghost">Ir al Inicio</AppButton>
                </div>
            </section>
        </main>
    </v-app>
</template>

<style scoped>
/* El fondo y la tarjeta mantienen la identidad del sitio y se adaptan a pantallas estrechas. */
.access-error {
    display: grid;
    min-height: 100vh;
    min-height: 100dvh;
    place-items: center;
    padding: clamp(20px, 5vw, 56px);
    background: #f5f9fa;
}

.access-error-card {
    width: min(100%, 570px);
    padding: clamp(24px, 6vw, 44px) clamp(20px, 6vw, 48px);
    text-align: center;
    background: #fff;
    border: 1px solid #44607533;
    border-top: 4px solid #2db3a3;
    border-radius: 24px;
    box-shadow: 0 12px 32px #153b6410;
}

.access-error-brand {
    display: block;
    width: 112px;
    height: auto;
    margin: 0 auto 24px;
}

/* El título y las acciones conservan su jerarquía al ampliarse el texto. */
.access-error-status {
    margin-bottom: 4px;
    color: #1f6f7f;
    font-size: 1rem;
    font-weight: 700;
}

/* El 404 usa un código de 20 px con el tamaño de fuente base. */
.access-error-status[data-status="404"] {
    font-size: 1.25rem;
}

.access-error h1 {
    color: #153b64;
    font: 700 clamp(1.75rem, 4vw, 2.5rem)/1.14 'Space Grotesk', sans-serif;
}

/* El foco programático anuncia el título; los enlaces conservan su indicador de teclado. */
.access-error h1:focus {
    outline: none;
}

.access-error-description {
    max-width: 38ch;
    margin: 12px auto 0;
    color: #446075;
    line-height: 1.6;
}

.access-error-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px 12px;
    margin-top: 24px;
}

/* La salida principal tiene mayor superficie y se apila con el enlace secundario en pantallas angostas. */
.access-error-actions :deep(.v-btn) {
    min-height: 48px;
    font-size: 1rem;
}

.access-error-actions :deep(.btn-primary) {
    min-width: 152px;
    padding-inline: 24px;
}

@media (max-width: 420px) {
    .access-error-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
