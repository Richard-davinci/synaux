<script setup>
import {computed} from 'vue';
import {useForm} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import T from '../../Components/UI/PageTitle.vue';
import S from '../../Components/UI/StatusBadge.vue';
import RequestForm from '../../Components/Solicitudes/FormularioSolicitud.vue';
import {date} from '../../support/formatters';
import {useSubmit} from '../../support/useSubmit';

// El borrador reusa el formulario de solicitud; los pedidos enviados muestran su estado y sus respuestas.
const props = defineProps({data: {type: Object, required: true}});
const record = computed(() => props.data.selected ?? {});
const submit = useSubmit();
const stateForm = useForm({id: record.value.id, status: 'closed'});

function closeRequest() {
    submit(stateForm, 'estado-solicitud');
}
</script>

<template>
    <section class="page shell narrow">
        <template v-if="record.status === 'draft'">
            <T eyebrow="Solicitud en borrador" :title="record.title"
               description="Podés completar y revisar la información antes de enviarla."/>
            <RequestForm :data="data"/>
        </template>
        <template v-else>
            <B variant="ghost" href="/cliente/solicitudes">← Volver a solicitudes</B>
            <T :eyebrow="'SOL-' + record.id + ' · Solicitud'" :title="record.title">
                <S :status="record.status"/>
            </T>
            <div class="detail-layout">
                <v-card class="card">
                    <h2>Sobre tu solicitud</h2>
                    <p class="preserve-lines">{{ record.description }}</p>
                    <dl class="data-list">
                        <div>
                            <dt>Servicio</dt>
                            <dd>{{ record.service_name }}</dd>
                        </div>
                        <div>
                            <dt>Visibilidad</dt>
                            <dd>{{ record.visibility === 'public' ? 'Pública' : 'Privada' }}</dd>
                        </div>
                        <div>
                            <dt>Fecha de envío</dt>
                            <dd>{{ date(record.created_at) }}</dd>
                        </div>
                        <div>
                            <dt>Revisión</dt>
                            <dd>{{ record.reviewed_at ? date(record.reviewed_at) : 'Pendiente de revisión' }}</dd>
                        </div>
                    </dl>
                    <section v-if="record.contact">
                        <h3>Contacto del pedido</h3>
                        <p>{{ record.contact.name }} · {{ record.contact.email }}</p>
                        <p>{{ record.contact.company }} {{ record.contact.phone }} {{ record.contact.address }}</p>
                    </section>
                    <section v-if="record.additional_services?.length">
                        <h3>Servicios adicionales</h3>
                        <div v-for="item in record.additional_services" :key="item.service_id">
                            <strong>{{ item.name }} × {{ item.quantity }}</strong>
                            <p v-if="item.notes">{{ item.notes }}</p>
                            <dl v-if="item.answers?.length" class="data-list">
                                <div v-for="answer in item.answers" :key="answer.id">
                                    <dt>{{ answer.question }}</dt>
                                    <dd>{{ answer.label }}</dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                    <h3 v-if="record.answers?.length">Respuestas de la estimación</h3>
                    <dl class="data-list">
                        <div v-for="answer in record.answers" :key="answer.id">
                            <dt>{{ answer.question }}</dt>
                            <dd>{{ answer.label }}</dd>
                        </div>
                    </dl>
                </v-card>
                <aside>
                    <v-card class="card soft-panel">
                        <h3>¿Qué sigue?</h3>
                        <template v-if="record.visibility === 'private' || !$page.props.stageTwo">
                            <p>synaux revisará tu pedido y preparará un presupuesto con el alcance, el importe y los
                                plazos.</p>
                            <B href="/cliente/presupuestos" variant="secondary">Consultar presupuestos</B>
                        </template>
                        <template v-else>
                            <p>Podés consultar las propuestas recibidas e invitar profesionales para tu proyecto.</p>
                            <B href="/cliente/propuestas" variant="secondary">Ver propuestas</B>
                            <B href="/profesionales" variant="ghost">Buscar profesionales</B>
                        </template>
                    </v-card>
                    <v-card v-if="record.status === 'open'" class="card">
                        <h3>Gestionar solicitud</h3>
                        <p>Podés cerrarla si ya no necesitás continuar con el pedido.</p>
                        <B variant="secondary" :disabled="stateForm.processing" @click="closeRequest">Cerrar
                            solicitud</B>
                    </v-card>
                </aside>
            </div>
        </template>
    </section>
</template>
