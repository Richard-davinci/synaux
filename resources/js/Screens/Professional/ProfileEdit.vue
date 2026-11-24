<script setup>
import {useForm} from '@inertiajs/vue3';
import B from '../../Components/UI/AppButton.vue';
import F from '../../Components/UI/FormField.vue';
import T from '../../Components/UI/PageTitle.vue';
import {useSubmit} from '../../support/useSubmit';

const props = defineProps({data: {type: Object, required: true}});

// El formulario parte del perfil propio y actualiza la información visible en el directorio.
const profile = props.data.profile;
const profileForm = useForm({
    brand_name: profile?.brand_name ?? '',
    headline: profile?.headline ?? '',
    bio: profile?.bio ?? '',
    location: profile?.location ?? '',
    skills: profile?.skills?.join(', ') ?? '',
    availability_status: profile?.availability_status ?? 'available',
    active: profile?.active ?? true,
});
const submit = useSubmit();
</script>

<template>
    <section class="page shell narrow">
        <T eyebrow="Área profesional" title="Mi perfil profesional"
           description="Esta información se muestra en el directorio cuando tu perfil está visible."/>
        <v-card class="card form-card">
            <form @submit.prevent="submit(profileForm, 'perfil')">
                <div class="form-grid">
                    <F v-model="profileForm.brand_name" label="Nombre profesional o marca" required/>
                    <F v-model="profileForm.headline" label="Especialidad / titular" required/>
                </div>
                <F v-model="profileForm.bio" type="textarea" label="Acerca de mí" required/>
                <F v-model="profileForm.skills" label="Habilidades (separadas por comas)"/>
                <div class="form-grid">
                    <F v-model="profileForm.location" label="Ubicación"/>
                    <F v-model="profileForm.availability_status" type="select" label="Disponibilidad"
                       :options="[{value:'available',label:'Disponible'},{value:'busy',label:'Con agenda'},{value:'unavailable',label:'No disponible'}]"/>
                </div>
                <label class="select-row">
                    <input v-model="profileForm.active" type="checkbox">
                    Mostrar mi perfil en el directorio
                </label>
                <div class="form-actions">
                    <B href="/profesional" variant="secondary">Volver</B>
                    <B type="submit" :disabled="profileForm.processing">Guardar perfil</B>
                </div>
            </form>
        </v-card>
    </section>
</template>
