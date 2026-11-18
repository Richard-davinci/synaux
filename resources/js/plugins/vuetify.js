// Constructor de Vuetify, componentes utilizados, iconos SVG y traducciones al español.
import { createVuetify } from 'vuetify';
import { VApp, VBtn, VCard, VCol, VContainer, VDialog, VMain, VRow, VTextField } from 'vuetify/components';
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg';
import { es } from 'vuetify/locale';

// Esta instancia se registra en app.js y comparte su configuración con todas las páginas.
export default createVuetify({
    // Registra explícitamente los componentes que aparecen en las pantallas actuales.
    components: { VApp, VBtn, VCard, VCol, VContainer, VDialog, VMain, VRow, VTextField },
    // Traduce los textos internos de los controles y define el español como idioma de respaldo.
    locale: { locale: 'es', fallback: 'es', messages: { es } },
    // Usa iconos SVG para evitar depender de una fuente de iconos externa.
    icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
    // El tema vincula los nombres primary, secondary y surface con la paleta aprobada.
    theme: {
        defaultTheme: 'synaux',
        themes: {
            synaux: {
                dark: false,
                // Paleta de la presentación final, coherente con los SVG oficiales.
                colors: {
                    primary: '#153B64',
                    secondary: '#1F6F7F',
                    accent: '#2DB3A3',
                    background: '#F5F9FA',
                    surface: '#FFFFFF',
                    'on-background': '#153B64',
                    'on-surface': '#153B64',
                    'text-muted': '#446075',
                    'on-primary': '#FFFFFF',
                    'on-secondary': '#FFFFFF',
                },
            },
        },
    },
    // Aspecto inicial de botones y tarjetas; cada pantalla puede ajustar estas opciones si lo necesita.
    defaults: {
        // Los controles de formulario usan una densidad consistente en todas las vistas.
        VTextField: { density: 'compact', variant: 'outlined' },
        VSelect: { density: 'compact', variant: 'outlined' },
        VTextarea: { density: 'compact', variant: 'outlined' },
        VAutocomplete: { density: 'compact', variant: 'outlined' },
        VBtn: { rounded: 'lg', style: 'text-transform: none; letter-spacing: normal;' },
        // La profundidad se define con sombras de la marca en CSS; elevation: 0 las anulaba.
        VCard: { rounded: 'xl' },
    },
});
