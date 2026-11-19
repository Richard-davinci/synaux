<script setup>
import { usePage } from '@inertiajs/vue3';
import BrandLogo from '../Brand/BrandLogo.vue';
import { referenceHref } from '../../support/synaux';
import { publicNav } from '../../support/useNavigationContext';

defineProps({ year: Number, landing: Boolean, hasWorkspace: Boolean });

const page = usePage();
</script>

<template>
    <!-- El pie comparte la trama transparente y el logo vertical con eslogan. -->
    <footer class="site-footer" :class="{ 'landing-footer': landing, 'workspace-footer': hasWorkspace }">
        <div class="synaux-brand-pattern site-footer-pattern" aria-hidden="true"></div>
        <div class="shell footer-grid">
            <a :href="referenceHref('/', page.props)" aria-label="synaux: presentación"><BrandLogo /></a>
            <div class="footer-copy">
                <strong>Conexión, transparencia y crecimiento digital.</strong>
                <p>© {{ year }} synaux.</p>
            </div>
            <nav aria-label="Navegación del pie">
                <span class="footer-nav-title">Explorá</span>
                <div class="footer-nav-links">
                    <a v-for="[label, href] in publicNav" :key="href" :href="referenceHref(href, page.props)">{{ label }}</a>
                </div>
            </nav>
        </div>
    </footer>
</template>

<style scoped>
/* El pie ocupa el mismo ancho del contenido que acompaña al panel fijo. */
.workspace-footer { margin-left: 240px; }
@media (max-width: 1100px) { .workspace-footer { margin-left: 0; } }
</style>
