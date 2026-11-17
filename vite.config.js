// Vite compila los recursos; sus complementos integran Laravel y los componentes .vue.
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Configuración utilizada tanto al desarrollar como al generar la compilación final.
export default defineConfig({
    plugins: [
        // app.js es la entrada; refresh recarga el navegador ante cambios en archivos de Laravel.
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        // Compila Vue y permite que Vite resuelva las referencias locales a imágenes.
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // Evita que los archivos desktop.ini de Drive disparen recargas del servidor de desarrollo.
    server: {
        watch: {
            ignored: ['**/desktop.ini'],
        },
    },
});
