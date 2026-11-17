// Preparación común: cliente HTTP, estilos de Vuetify y tipografías/ajustes de synaux.
import './bootstrap';
import 'vuetify/styles';
import '../css/app.css';

// Herramientas que conectan las respuestas de Laravel con las páginas Vue y el tema Vuetify.
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import vuetify from './plugins/vuetify';

// Inicia una única aplicación Vue; Inertia selecciona la página solicitada a Laravel.
createInertiaApp({
    // Agrega la marca en minúsculas al título recibido de cada página.
    title: (title) => (title ? `${title} | synaux` : 'synaux'),
    // Busca el componente dentro de Pages a partir del nombre devuelto por Inertia::render.
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    // Registra Inertia y Vuetify antes de montar Vue en el elemento del documento Blade.
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(vuetify)
            .mount(el);
    },
    // La barra que acompaña la navegación utiliza el azul verdoso de la marca.
    progress: { color: '#1F6F7F' },
});
