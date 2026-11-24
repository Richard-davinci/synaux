<script setup>
import NewBadge from '../Notifications/NewBadge.vue';
import B from '../UI/AppButton.vue';
import I from '../UI/AppIcon.vue';
import S from '../UI/StatusBadge.vue';
import {date, money} from '../../support/formatters';

defineProps({
    item: {type: Object, required: true},
    section: {type: String, required: true},
    icon: {type: String, required: true},
    canRevise: Boolean,
});
const emit = defineEmits(['update-project']);
</script>

<template>
    <v-card :id="section === 'contactos' ? 'consulta-' + item.id : undefined" class="card management-record">
        <I :name="icon" class="workspace-record-icon"/>
        <div class="management-record-copy">
            <div class="management-record-meta">
                <small>{{ date(item.created_at) }}</small>
                <S v-if="item.status" :status="item.display_status ?? item.status"/>
                <S v-else-if="section === 'portfolio'" :status="item.is_public ? 'published' : 'unpublished'"/>
            </div>
            <div class="record-heading">
                <h3>{{ item.title ?? item.topic }}</h3>
                <NewBadge :type="{ presupuestos: 'presupuesto', proyectos: 'proyecto', contactos: 'consulta' }[section]"
                          :id="item.id"/>
            </div>
            <template v-if="section === 'contactos'">
                <p>{{ item.name }} · {{ item.email }}</p>
                <p class="preserve-lines">{{ item.message }}</p>
            </template>
            <p v-if="section === 'presupuestos'">Versión {{ item.version_number }} ·
                {{ money(item.total, item.currency_code) }}</p>
            <p v-if="section === 'portfolio'">{{ item.category }}</p>
            <template v-if="section === 'proyectos'">
                <p>PRY-{{ item.id }} · Avance informado {{ item.progress }}%</p>
                <progress max="100" :value="item.progress" :aria-label="'Avance de ' + item.title"/>
            </template>
        </div>
        <div v-if="section !== 'contactos'" class="management-record-actions">
            <template v-if="section === 'presupuestos'">
                <B compact :href="'/presupuestos/' + item.id" variant="secondary">Ver versión</B>
                <B v-if="canRevise" compact :href="'/editor/presupuesto/' + item.id">{{
                        item.status === 'draft' ? 'Continuar borrador' : 'Preparar nueva versión'
                    }}</B>
            </template>
            <template v-else-if="section === 'proyectos'">
                <B compact :href="'/proyectos/' + item.id" variant="secondary">Ver seguimiento</B>
                <B compact @click="emit('update-project', item)">Actualizar avance</B>
            </template>
            <B v-else-if="section === 'portfolio'" compact :href="'/editor/trabajo/' + item.id" variant="secondary">Editar
                trabajo</B>
        </div>
    </v-card>
</template>
