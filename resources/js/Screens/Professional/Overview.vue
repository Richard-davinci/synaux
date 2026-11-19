<script setup>
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import { useMarketplaceRecords } from '../../support/useMarketplaceRecords';

const props = defineProps({ data: { type: Object, required: true } });
const { proposals, invitations } = useMarketplaceRecords(props);
</script>

<template>
    <section class="page shell">
        <T eyebrow="Área profesional" :title="`Hola, ${data.profile?.brand_name ?? $page.props.auth?.name ?? 'profesional'}`" description="Encontrá oportunidades y seguí las conversaciones que pueden convertirse en proyectos.">
        <B href="/oportunidades" arrow>Explorar oportunidades</B></T>
        <div class="stats-grid">
            <v-card v-for="[label,value] in [['Propuestas enviadas',proposals.length],['Invitaciones',invitations.filter(i=>i.status==='pending').length],['Presupuestos',data.quotes?.length ?? 0],['Proyectos',data.projects?.length ?? 0]]" :key="label" class="card stat">
                <span>{{ label }}</span>
                <strong>{{ value }}</strong>
            </v-card>
        </div>
        <div class="detail-layout">
            <v-card class="card">
                <h2>Tus últimas propuestas</h2>
                <div v-for="p in proposals.slice(0,3)" :key="p.id" class="list-row">
                    <div>
                        <h3>{{ p.title }}</h3>
                        <S :status="p.status" />
                    </div>
                    <B :href="`/oportunidades/${p.project_request_id}`" variant="secondary">Ver solicitud</B>
                </div>
                <E v-if="!proposals.length" title="Tu próximo proyecto empieza acá" text="Explorá el muro y presentá una propuesta acorde a tu experiencia." />
            </v-card>
            <aside>
                <v-card class="card soft-panel">
                    <h3>Tu perfil profesional</h3>
                    <p>Mantené actualizadas tus habilidades y disponibilidad.</p>
                    <B href="/profesional/perfil" variant="secondary">Editar mi perfil</B>
                    <B href="/profesional/portfolio" variant="ghost">Administrar portfolio</B>
                </v-card>
            </aside>
        </div>
    </section>
</template>
