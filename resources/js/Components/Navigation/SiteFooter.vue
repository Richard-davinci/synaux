<script setup>
import {usePage} from '@inertiajs/vue3';
import navbarLogo from '../../../images/brand/synaux-horizontal-sin-slogan-color.svg';
import {referenceHref} from '../../support/prototype';
import {publicNav} from '../../support/useNavigationContext';

defineProps({year: Number, landing: Boolean, hasWorkspace: Boolean});

const page = usePage();
</script>

<template>
    <!-- El pie usa la misma marca horizontal que el encabezado y conserva la trama. -->
    <footer class="site-footer" :class="{ 'landing-footer': landing, 'workspace-footer': hasWorkspace }">
        <div class="synaux-brand-pattern site-footer-pattern" aria-hidden="true"></div>
        <div class="shell footer-grid">
            <a :href="referenceHref('/inicio', page.props)" aria-label="synaux: inicio">
                <img :src="navbarLogo" class="brand-logo" width="405" height="140" alt="synaux">
            </a>
            <div class="footer-copy">
                <!-- El eslogan de la landing ya aparece en la marca de la presentación. -->
                <strong v-if="!landing">Conexión, transparencia y crecimiento digital.</strong>
                <p>© {{ year }} synaux.</p>
            </div>
            <nav aria-label="Navegación del pie">
                <span class="footer-nav-title">Explorá</span>
                <div class="footer-nav-links">
                    <a v-for="[label, href] in publicNav" :key="href" :href="referenceHref(href, page.props)">{{
                            label
                        }}</a>
                </div>
            </nav>
        </div>
    </footer>
</template>

<style scoped>
/* El pie ocupa el mismo ancho del contenido que acompaña al panel fijo. */
.workspace-footer {
    margin-left: 240px;
}

@media (max-width: 1100px) {
    .workspace-footer {
        margin-left: 0;
    }
}
</style>
