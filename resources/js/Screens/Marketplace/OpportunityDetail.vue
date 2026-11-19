<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import S from '../../Components/UI/StatusBadge.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import T from '../../Components/UI/PageTitle.vue';
import { date, useSubmit } from '../../support/synaux';

const props = defineProps({ data: { type: Object, required: true } });
const selected = computed(() => props.data.selected ?? {});

// La propuesta se prepara para la oportunidad seleccionada y conserva el borrador al cerrar el panel.
const proposalOpen = ref(props.data.referenceKey === 'propuesta-formulario');
const proposalForm = useForm({
    request_id: selected.value.id, message: '', estimated_delivery_days: 30, amount: '', currency_code: 'ARS',
});
const submit = useSubmit();

function propose() {
    submit(proposalForm, 'propuesta', { previewTarget: 'mis-propuestas' });
}
</script>

<template>
    <section class="page shell">
        <B href="/oportunidades" variant="ghost">← Volver al muro</B>
        <T :eyebrow="`${selected.category} · SOL-${selected.id}`" :title="selected.title">
        <S :status="selected.status" /></T>
        <div class="detail-layout">
            <v-card class="card">
                <h2>Sobre el proyecto</h2>
                <p class="preserve-lines">{{ selected.description }}</p>
                <h3>Habilidades buscadas</h3>
                <div class="tags">
                    <span v-for="tag in selected.skills" :key="tag">{{ tag }}</span>
                </div>
                <dl class="data-list">
                    <div>
                        <dt>Servicio</dt>
                        <dd>{{ selected.service_name }}</dd>
                    </div>
                    <div>
                        <dt>Publicado</dt>
                        <dd>{{ date(selected.published_at) }}</dd>
                    </div>
                </dl>
            </v-card>
            <aside>
                <v-card class="card">
                    <p class="eyebrow">Información de la solicitud</p>
                    <dl class="data-list">
                        <div>
                            <dt>Presupuesto de referencia</dt>
                            <dd>{{ selected.budget_reference || 'A definir' }}</dd>
                        </div>
                        <div>
                            <dt>Plazo deseado</dt>
                            <dd>{{ selected.desired_deadline || 'A coordinar' }}</dd>
                        </div>
                    </dl>
                    <template v-if="$page.props.auth || data.preview">
                        <B v-if="selected.is_owner" :href="`/solicitudes/${selected.id}`">Administrar solicitud</B>
                        <B v-else-if="data.existingProposal" href="/profesional/propuestas" variant="secondary">Ver mi propuesta</B>
                        <B v-else-if="data.profile?.active || data.preview" :disabled="selected.status !== 'open'" @click="proposalOpen = true">Presentar propuesta</B>
                        <B v-else href="/profesional/perfil">Completar perfil profesional</B>
                    </template>
                    <B v-else href="/ingresar">Ingresar para postularme</B>
                    <p class="reference-note">La selección abre la preparación de un presupuesto. No implica contratación automática.</p>
                </v-card>
            </aside>
        </div>
        <SidePanel v-model="proposalOpen" title="Propuestas e invitaciones" :persistent="proposalForm.processing" max-width="520">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <p class="eyebrow">{{ selected.title }}</p>
                    <h2>Presentar propuesta</h2>
                    <p>Contá cómo abordarías el proyecto. Los importes son una referencia inicial.</p>
                    <form @submit.prevent="propose">
                        <F v-model="proposalForm.message" label="Tu enfoque y experiencia" type="textarea" required />
                        <div class="form-grid">
                            <F v-model="proposalForm.estimated_delivery_days" label="Plazo estimado (días)" type="number" min="1" required />
                            <F v-model="proposalForm.amount" label="Importe de referencia (opcional)" type="number" min="0" step="0.01" />
                        </div>
                        <F v-model="proposalForm.currency_code" label="Moneda" type="select" :options="['ARS','USD','EUR']" />
                        <div class="form-actions">
                            <B variant="secondary" :disabled="proposalForm.processing" @click="proposalOpen = false">Cancelar</B>
                            <B type="submit" :disabled="proposalForm.processing">Enviar propuesta</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>
