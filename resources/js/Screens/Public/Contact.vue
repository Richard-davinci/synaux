<script setup>
import './public-screen.css';
import {computed} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import I from '../../Components/UI/AppIcon.vue';
import {useSubmit} from '../../support/useSubmit';

const props = defineProps({data: Object});
const page = usePage();
const submit = useSubmit();
const form = useForm({name: '', email: '', topic: 'Consulta general', message: ''});

// La confirmación representa el envío registrado o el estado de ejemplo del prototipo.
const sent = computed(() => page.url.includes('enviado=1') || props.data.referenceKey === 'contacto-enviado');
</script>

<template>
    <!-- Contacto separa la consulta general del recorrido para pedir un presupuesto. -->
    <section data-public-screen class="page shell public-editorial public-typography">
        <T class="public-page-heading" eyebrow="Contacto" title="Hablemos de tu proyecto"
           description="Si todavía estás definiendo qué necesitás, contanos tu idea y te orientamos sobre cómo empezar."/>
        <div class="editorial-split">
            <div class="editorial-panel contact-form-panel">
                <div v-if="sent" class="contact-success" role="status">
                    <I name="check" class="editorial-icon"/>
                    <h2>Recibimos tu consulta</h2>
                    <p>Tu mensaje quedó registrado para su revisión.</p>
                    <B href="/contacto" variant="secondary">Enviar otra consulta</B>
                </div>
                <form v-else aria-labelledby="contact-form-title"
                      @submit.prevent="submit(form, '/contacto', {previewTarget:'contacto-enviado'})">
                    <h2 id="contact-form-title">Dejanos tu consulta</h2>
                    <div class="field-row">
                        <F v-model="form.name" label="Nombre" required autocomplete="name" :error="form.errors.name"/>
                        <F v-model="form.email" label="Correo" type="email" required autocomplete="email"
                           :error="form.errors.email"/>
                    </div>
                    <F v-model="form.topic" label="Tema" type="select"
                       :options="['Consulta general', ...data.services.map(s => s.name)]" required
                       :error="form.errors.topic"/>
                    <F v-model="form.message" label="Mensaje" type="textarea" required minlength="10" maxlength="10000"
                       hint="No incluyas información sensible."
                       placeholder="¿Qué querés lograr? Si tenés una fecha en mente, contanos cuál."
                       :error="form.errors.message"/>
                    <B type="submit" :disabled="form.processing"
                       arrow>{{ form.processing ? 'Enviando…' : 'Enviar consulta' }}</B>
                </form>
            </div>
            <aside class="editorial-panel contact-next">
                <I name="file" class="editorial-icon"/>
                <h2>¿Ya sabés qué servicio necesitás?</h2>
                <p>Elegí un servicio y prepará una solicitud para que podamos cotizar tu proyecto.</p>
                <B v-if="!$page.props.auth?.es_cuenta_interna || data.preview" href="/solicitudes/nueva"
                   variant="secondary"
                   arrow>Preparar mi solicitud</B>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.contact-form-panel {
    border-top: 3px solid #2db3a3;
}

.contact-form-panel h2 {
    margin-bottom: 20px;
}

.contact-next {
    background: #f5f9fa;
}
</style>
