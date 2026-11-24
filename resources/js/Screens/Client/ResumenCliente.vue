<script setup>
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';
import S from '../../Components/UI/StatusBadge.vue';
import EmptyState from '../../Components/UI/EmptyState.vue';
import NewBadge from '../../Components/Notifications/NewBadge.vue';
import {date} from '../../support/formatters';

// Los conteos y la actividad salen de las colecciones autorizadas que devuelve Laravel.
defineProps({data: {type: Object, required: true}});
</script>

<template>
    <section class="page shell">
        <T eyebrow="Área cliente" :title="'Hola, ' + ($page.props.auth?.name?.split(' ')[0] ?? 'Martina')"
           description="Revisá tus solicitudes, presupuestos y proyectos en un solo lugar.">
            <B href="/solicitudes/nueva" arrow>Nueva solicitud</B>
        </T>
        <div class="stats-grid">
            <v-card v-for="[label, value, link] in [
                ['Estimaciones', data.estimates?.length ?? 0, 'estimaciones'],
                ['Solicitudes', data.requests?.length ?? 0, 'solicitudes'],
                ['Presupuestos', data.quotes?.length ?? 0, 'presupuestos'],
                ['Proyectos', data.projects?.length ?? 0, 'proyectos'],
            ]" :key="link" class="card stat">
                <span>{{ label }}</span>
                <strong>{{ value }}</strong>
                <B variant="ghost" :href="'/cliente/' + link">Ver {{ label.toLowerCase() }} →</B>
            </v-card>
        </div>

        <div class="detail-layout">
            <v-card class="card">
                <div class="section-head">
                    <h2>Actividad reciente</h2>
                    <B variant="ghost" href="/cliente/solicitudes">Ver todas</B>
                </div>
                <div v-for="request in (data.requests ?? []).slice(0, 3)" :key="request.id" class="list-row">
                    <div>
                        <small>SOL-{{ request.id }} · {{ date(request.created_at) }}</small>
                        <div class="record-heading">
                            <h3>{{ request.title }}</h3>
                            <NewBadge type="solicitud" :id="request.id"/>
                        </div>
                        <S :status="request.status"/>
                    </div>
                    <B :href="'/solicitudes/' + request.id" variant="secondary">Ver detalle</B>
                </div>
                <EmptyState v-if="!data.requests?.length" title="Todavía no tenés solicitudes"
                            text="Empezá contando qué necesitás resolver."/>
            </v-card>
            <aside>
                <v-card class="card soft-panel">
                    <p class="eyebrow">Tu próximo paso</p>
                    <h3>De la idea al proyecto</h3>
                    <p>Estimá el alcance, creá una solicitud y revisá la propuesta formal antes de confirmar.</p>
                    <B href="/estimador" arrow>Estimar un proyecto</B>
                </v-card>
            </aside>
        </div>
    </section>
</template>
