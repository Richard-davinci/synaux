<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import { date, money, useSubmit } from '../../support/synaux';
import { useMarketplaceRecords } from '../../support/useMarketplaceRecords';

const props = defineProps({ data: { type: Object, required: true } });
const { professional, proposals } = useMarketplaceRecords(props);
const compare = ref(props.data.referenceKey === 'comparar-propuestas');
const submit = useSubmit();

// El servidor valida que la cuenta sea emisora o titular antes de aplicar la acción.
function changeProposal(action, id) {
    submit(useForm({ id }), action);
}
</script>

<template>
    <section class="page shell">
        <T :eyebrow="professional ? 'Área profesional' : 'Área cliente'" :title="professional ? 'Mis propuestas' : compare ? 'Comparar propuestas' : 'Propuestas recibidas'" description="La propuesta presenta un enfoque inicial. La contratación se confirma con un presupuesto formal.">
        <B v-if="!professional && proposals.length" variant="secondary" @click="compare = !compare">{{ compare ? 'Ver listado' : 'Comparar propuestas' }}</B>
        <B v-if="professional" href="/oportunidades" arrow>Ver oportunidades</B></T>
        <div :class="compare ? 'compare-grid' : 'stack'">
            <v-card v-for="p in proposals" :key="p.id" class="card">
                <div class="section-head">
                    <div>
                        <p class="eyebrow">{{ p.title }}</p>
                        <h2>{{ professional ? 'Tu propuesta' : p.name }}</h2>
                    </div>
                    <S :status="p.status" />
                </div>
                <p>{{ p.headline }}</p>
                <p class="preserve-lines">{{ p.message }}</p>
                <div class="tags">
                    <span v-for="tag in p.skills" :key="tag">{{ tag }}</span>
                </div>
                <dl class="data-list">
                    <div>
                        <dt>Referencia económica</dt>
                        <dd>{{ money(p.amount,p.currency_code) }}</dd>
                    </div>
                    <div>
                        <dt>Plazo estimado</dt>
                        <dd>{{ p.estimated_delivery_days }} días</dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <dd>{{ date(p.created_at) }}</dd>
                    </div>
                </dl>
                <div class="button-row">
                    <B :href="`/profesionales/${p.professional_profile_id}`" variant="secondary">Ver perfil</B>
                    <B v-if="professional && p.status === 'submitted'" variant="ghost" @click="changeProposal('retirar-propuesta', p.id)">Retirar propuesta</B>
                    <B v-else-if="!professional && p.status === 'submitted'" @click="changeProposal('seleccionar-propuesta', p.id)">Elegir para presupuestar</B>
                    <B v-if="professional && p.status === 'accepted'" :href="`/editor/presupuesto?solicitud=${p.project_request_id}`">Preparar presupuesto</B>
                </div>
            </v-card>
        </div>
        <E v-if="!proposals.length" :title="professional ? 'Todavía no enviaste propuestas' : 'Todavía no recibiste propuestas'" text="Las propuestas van a aparecer acá con sus estados." :href="professional ? '/oportunidades' : '/cliente/publicaciones'" action="Ver oportunidades" />
    </section>
</template>
