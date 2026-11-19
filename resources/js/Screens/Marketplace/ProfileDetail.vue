<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import S from '../../Components/UI/StatusBadge.vue';
import SidePanel from '../../Components/UI/SidePanel.vue';
import { initials } from '../../support/formatters';
import { useSubmit } from '../../support/useSubmit';

const props = defineProps({ data: { type: Object, required: true } });
const selected = computed(() => props.data.selected ?? {});

// Solo las solicitudes abiertas de la cuenta actual se ofrecen en el formulario de invitación.
const requestOptions = computed(() => (props.data.requests ?? [])
    .filter(record => record.status === 'open')
    .map(record => ({ value: record.id, label: record.title })));
const invitationOpen = ref(false);
const invitationForm = useForm({ request_id: '', professional_id: selected.value.id });
const submit = useSubmit();

function invite() {
    submit(invitationForm, 'invitacion', { previewTarget: 'invitaciones-enviadas' });
}
</script>

<template>
    <section class="page shell">
        <B href="/profesionales" variant="ghost">← Volver al directorio</B>
        <div class="profile-hero">
            <div class="avatar large">{{ initials(selected.name) }}</div>
            <div>
                <p class="eyebrow">Perfil profesional</p>
                <h1>{{ selected.name }}</h1>
                <p class="lead">{{ selected.headline }}</p>
                <S :status="selected.availability_status" />
                <p>{{ selected.location }}</p>
            </div>
        </div>
        <div class="detail-layout">
            <div class="stack">
                <v-card class="card">
                    <h2>Acerca de mí</h2>
                    <p class="preserve-lines">{{ selected.bio }}</p>
                    <h3>Habilidades</h3>
                    <div class="tags">
                        <span v-for="tag in selected.skills" :key="tag">{{ tag }}</span>
                    </div>
                </v-card>
                <v-card class="card">
                    <h2>Trabajos destacados</h2>
                    <div class="portfolio-grid">
                        <a v-for="w in data.works" :key="w.id" :href="data.preview ? '/prototipo/trabajo' : `/trabajos/${w.id}`" class="work-card">
                            <img v-if="w.cover_path" :src="w.cover_path" :alt="w.title">
                            <h3>{{ w.title }}</h3>
                        </a>
                    </div>
                    <p v-if="!data.works?.length">Todavía no hay trabajos públicos.</p>
                </v-card>
            </div>
            <aside>
                <v-card class="card">
                    <h3>¿Trabajamos juntos?</h3>
                    <p>Invitá a este profesional a una de tus solicitudes abiertas.</p>
                    <template v-if="$page.props.auth || data.preview">
                        <B v-if="requestOptions.length" @click="invitationOpen = true">Invitar a una solicitud</B>
                        <B v-else href="/solicitudes/nueva">Crear solicitud</B>
                    </template>
                    <B v-else href="/ingresar">Ingresar para invitar</B>
                </v-card>
            </aside>
        </div>
        <SidePanel v-model="invitationOpen" title="Propuestas e invitaciones" :persistent="invitationForm.processing" max-width="520">
            <div class="synaux-app">
                <v-card class="card modal-card">
                    <h2>Invitar a {{ selected.name }}</h2>
                    <form @submit.prevent="invite">
                        <F v-model="invitationForm.request_id" type="select" label="Elegí una solicitud" required :options="requestOptions" />
                        <div class="form-actions">
                            <B variant="secondary" :disabled="invitationForm.processing" @click="invitationOpen = false">Cancelar</B>
                            <B type="submit" :disabled="invitationForm.processing">Enviar invitación</B>
                        </div>
                    </form>
                </v-card>
            </div>
        </SidePanel>
    </section>
</template>
