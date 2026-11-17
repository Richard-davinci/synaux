<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';
import PublicScreens from '../Screens/PublicScreens.vue';
import '../../css/workspace.css';
import '../../css/workspace-overrides.css';
// El catálogo conserva su entrada independiente y consume los servicios publicados desde Laravel.
const page=usePage();
</script>
<template><Head title="Catálogo de servicios" /><PublicLayout><PublicScreens :key="page.url" :data="{...page.props,screen:'servicios'}" /></PublicLayout></template>
