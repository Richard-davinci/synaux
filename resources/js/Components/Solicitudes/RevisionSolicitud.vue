<script setup>
import B from '../UI/AppButton.vue';
import I from '../UI/AppIcon.vue';

defineProps({
    form: {type: Object, required: true},
    selectedService: Object,
    extraName: {type: Function, required: true},
    extraQuestions: {type: Function, required: true},
    extraAnswerText: {type: Function, required: true},
});
const emit = defineEmits(['go', 'publish']);
</script>

<template>
    <div class="review-section-heading"><h3>Tu proyecto</h3><B variant="ghost" @click="emit('go', 2)">Editar
        proyecto</B></div>
    <strong>{{ form.title || 'Tu proyecto' }}</strong>
    <p class="preserve-lines">{{ form.description }}</p>
    <div class="review-section-heading"><h3>Servicios y contacto</h3><B variant="ghost" @click="emit('go', 1)">Editar
        servicios</B><B variant="ghost" @click="emit('go', 3)">Editar contacto</B></div>
    <dl class="data-list">
        <div>
            <dt>Servicio principal</dt>
            <dd>{{ selectedService?.name }}</dd>
        </div>
        <div v-for="(item, index) in form.additional_services" :key="index">
            <dt>{{ extraName(item.service_id) }} × {{ item.quantity }}</dt>
            <dd>
                <p v-if="item.notes">{{ item.notes }}</p>
                <template v-for="question in extraQuestions(item.service_id)" :key="question.id">
                    <p v-if="extraAnswerText(item, question)"><strong>{{ question.question }}</strong>
                        {{ extraAnswerText(item, question) }}</p>
                </template>
            </dd>
        </div>
        <div>
            <dt>Contacto</dt>
            <dd>{{ form.contact.name }}<br>{{ form.contact.email }}</dd>
        </div>
        <div v-if="form.contact.company">
            <dt>Organización</dt>
            <dd>{{ form.contact.company }}</dd>
        </div>
        <div v-if="form.contact.phone">
            <dt>Teléfono</dt>
            <dd>{{ form.contact.phone }}</dd>
        </div>
        <div v-if="form.contact.address">
            <dt>Dirección</dt>
            <dd>{{ form.contact.address }}</dd>
        </div>
        <div v-if="form.skills">
            <dt>Habilidades deseadas</dt>
            <dd>{{ form.skills }}</dd>
        </div>
        <div>
            <dt>Plazo</dt>
            <dd>{{ form.desired_deadline || 'A coordinar' }}</dd>
        </div>
        <div>
            <dt>Presupuesto disponible</dt>
            <dd>{{ form.budget_reference || 'A definir' }}</dd>
        </div>
        <div>
            <dt>Visibilidad</dt>
            <dd>{{ form.visibility === 'public' ? 'Pública' : 'Privada' }}</dd>
        </div>
    </dl>
    <p class="request-origin"><I name="info"/> Al enviar, synaux revisará el alcance y preparará tu presupuesto. Esta
        solicitud no confirma una contratación.</p>
    <div class="form-actions"><B variant="secondary" :disabled="form.processing" @click="emit('go', 3)">Anterior</B><B
        :disabled="form.processing" arrow @click="emit('publish')">{{
            form.processing ? 'Enviando…' : form.visibility === 'public' ? 'Publicar solicitud' : 'Solicitar presupuesto'
        }}</B></div>
</template>

<style scoped>
.request-origin {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 14px;
    background: #f5f9fa;
    border-radius: 10px;
}

.request-origin .icon {
    flex: 0 0 22px;
    color: #1f6f7f;
}

.review-section-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
}

.review-section-heading h3 {
    flex: 1 1 auto;
    margin: 0;
}
</style>
