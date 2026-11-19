<script setup>
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import { date } from '../../support/formatters';
import { useSubmit } from '../../support/useSubmit';
import { useMarketplaceRecords } from '../../support/useMarketplaceRecords';

const props = defineProps({ data: { type: Object, required: true } });
const { professional, invitations } = useMarketplaceRecords(props);
const submit = useSubmit();

function respond(id, status) {
    submit(useForm({ id, status }), 'responder-invitacion');
}
</script>

<template>
    <section class="page shell">
        <T :eyebrow="professional ? 'Área profesional' : 'Área cliente'" :title="professional ? 'Invitaciones recibidas' : 'Invitaciones enviadas'" description="Seguí el estado de las invitaciones a colaborar en un proyecto." />
        <div class="stack">
            <v-card v-for="i in invitations" :key="i.id" class="card list-row">
                <div>
                    <small>{{ date(i.created_at) }}</small>
                    <h3>{{ i.title }}</h3>
                    <p>{{ professional ? 'Te invitaron a presentar una propuesta' : i.name }}</p>
                    <S :status="i.status" />
                </div>
                <div class="button-row">
                    <template v-if="professional && i.status === 'pending' && i.request_status === 'open'">
                        <B variant="secondary" @click="respond(i.id, 'declined')">Declinar</B>
                        <B @click="respond(i.id, 'accepted')">Aceptar invitación</B>
                    </template>
                    <B v-else-if="professional && i.status === 'accepted'" :href="`/oportunidades/${i.project_request_id}`" variant="secondary">Ver solicitud</B>
                    <B v-else-if="!professional" :href="`/profesionales/${i.professional_profile_id}`" variant="secondary">Ver profesional</B>
                </div>
            </v-card>
        </div>
        <E v-if="!invitations.length" title="Todavía no hay invitaciones" text="Cuando haya invitaciones, vas a poder seguir su estado desde acá." />
    </section>
</template>
