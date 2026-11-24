<script setup>
import {computed, ref} from 'vue';
import B from '../../Components/UI/AppButton.vue';
import E from '../../Components/UI/EmptyState.vue';
import F from '../../Components/UI/FormField.vue';
import S from '../../Components/UI/StatusBadge.vue';
import T from '../../Components/UI/PageTitle.vue';
import {initials} from '../../support/formatters';

const props = defineProps({data: {type: Object, required: true}});
const query = ref('');
const skill = ref('');

// El directorio filtra los perfiles publicados por nombre, especialidad y habilidades.
const skills = computed(() => ['Todas', ...new Set((props.data.professionals ?? []).flatMap(profile => profile.skills))]);
const professionals = computed(() => {
    const search = query.value.toLowerCase();
    return (props.data.professionals ?? []).filter(profile =>
        (!skill.value || skill.value === 'Todas' || profile.skills.includes(skill.value))
        && `${profile.name} ${profile.headline} ${profile.skills.join(' ')}`.toLowerCase().includes(search));
});
</script>

<template>
    <section class="page shell">
        <T eyebrow="Directorio profesional" title="Encontrá el perfil indicado"
           description="Explorá especialidades y trabajos. También podés invitar a una solicitud sin compartir datos privados."/>
        <div class="filters">
            <F v-model="query" label="Buscar profesional o habilidad" placeholder="Ej. Vue, diseño, identidad"/>
            <F v-model="skill" label="Habilidad" type="select" :options="skills"/>
        </div>
        <div class="professional-grid">
            <v-card v-for="p in professionals" :key="p.id" class="card professional-card">
                <div class="avatar">{{ initials(p.name) }}</div>
                <h2>{{ p.name }}</h2>
                <p class="professional-headline">{{ p.headline }}</p>
                <S :status="p.availability_status"/>
                <p>{{ p.bio }}</p>
                <div class="tags">
                    <span v-for="tag in p.skills" :key="tag">{{ tag }}</span>
                </div>
                <B :href="`/profesionales/${p.id}`" variant="secondary" arrow>Ver perfil</B>
            </v-card>
        </div>
        <E v-if="!professionals.length" title="No encontramos profesionales"
           text="Los perfiles publicados aparecerán en este directorio."/>
    </section>
</template>
