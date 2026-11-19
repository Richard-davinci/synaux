<script setup>
import { computed } from 'vue';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import { date } from '../../support/formatters';

const props = defineProps({ data: { type: Object, required: true } });

// Las publicaciones muestran las solicitudes públicas que Laravel devolvió para esta cuenta.
const requests = computed(() => (props.data.requests ?? []).filter(record => record.visibility === 'public'));
</script>

<template>
    <section class="page shell">
        <T eyebrow="Área cliente" title="Mis publicaciones" description="Administrá tus solicitudes públicas y las propuestas recibidas.">
        <B href="/solicitudes/nueva" arrow>Publicar solicitud</B></T>
        <div class="stack">
            <v-card v-for="r in requests" :key="r.id" class="card list-row">
                <div>
                    <small>SOL-{{ r.id }} · {{ date(r.created_at) }}</small>
                    <h3>{{ r.title }}</h3>
                    <S :status="r.status" />
                    <p>{{ r.proposal_count }} propuestas recibidas</p>
                </div>
                <div class="button-row">
                    <B :href="`/solicitudes/${r.id}`" variant="secondary">Ver detalle</B>
                    <B href="/cliente/propuestas">Ver propuestas</B>
                </div>
            </v-card>
        </div>
        <E v-if="!requests.length" title="Todavía no publicaste solicitudes" text="Publicá una necesidad para recibir propuestas de profesionales." href="/solicitudes/nueva" action="Crear publicación" />
    </section>
</template>
