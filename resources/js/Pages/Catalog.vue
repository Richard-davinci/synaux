<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import ServiceCatalog from '../Screens/Public/ServiceCatalog.vue';
import '../../css/panel.css';
// El catálogo conserva su entrada independiente y consume los servicios publicados desde Laravel.
const page = usePage();
</script>
<template>
    <Head title="Catálogo de servicios"/>
    <PublicLayout>
        <ServiceCatalog :key="page.url" :data="page.props"/>
    </PublicLayout>
</template>
