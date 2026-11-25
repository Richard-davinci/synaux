<script setup>
import {nextTick, ref, watch} from 'vue';
import AppButton from '../UI/AppButton.vue';
import AppIcon from '../UI/AppIcon.vue';

// La tarjeta muestra los datos guardados y abre un formulario local bajo demanda.
const props = defineProps({
    id: {type: String, required: true},
    titulo: {type: String, required: true},
    icono: {type: String, required: true},
    datos: {type: Array, default: () => []},
    vacio: String,
    accionEditar: String,
    accionGuardar: {type: String, required: true},
    editando: Boolean,
    guardando: Boolean,
    modificado: Boolean,
    guardadoDeshabilitado: Boolean,
});
const emit = defineEmits(['editar', 'cancelar', 'guardar']);
const acciones = ref(null);
const formulario = ref(null);

// El foco sigue al formulario y vuelve a la acción de la tarjeta cuando se cierra.
watch(() => props.editando, async (editando) => {
    await nextTick();
    const destino = editando
        ? formulario.value?.querySelector('input:not([disabled]):not([type="hidden"])')
        : acciones.value?.querySelector('button');
    destino?.focus();
});
</script>

<template>
    <v-card :id="id" tag="section" class="card tarjeta-perfil" :aria-labelledby="'titulo-' + id">
        <header class="tarjeta-perfil-encabezado">
            <h2 :id="'titulo-' + id"><AppIcon :name="icono"/>{{ titulo }}</h2>
            <div v-if="!editando" ref="acciones" class="tarjeta-perfil-editar">
                <AppButton variant="secondary" compact @click="emit('editar')">
                    {{ accionEditar || (datos.length ? 'Editar' : 'Agregar') }}
                </AppButton>
            </div>
        </header>
        <form v-if="editando" ref="formulario" @submit.prevent="emit('guardar')">
            <slot/>
            <div class="form-actions tarjeta-perfil-acciones">
                <AppButton variant="secondary" :disabled="guardando" @click="emit('cancelar')">Cancelar</AppButton>
                <AppButton type="submit" :disabled="guardadoDeshabilitado || guardando || !modificado">
                    {{ guardando ? 'Guardando…' : accionGuardar }}
                </AppButton>
            </div>
        </form>
        <dl v-else-if="datos.length" class="tarjeta-perfil-datos">
            <div v-for="[etiqueta, valor] in datos" :key="etiqueta">
                <dt>{{ etiqueta }}</dt>
                <dd>{{ valor }}</dd>
            </div>
        </dl>
        <p v-else-if="vacio" class="tarjeta-perfil-vacia">{{ vacio }}</p>
    </v-card>
</template>

<style scoped>
/* La consulta y la edición comparten el encabezado y mantienen visible su acción correspondiente. */
.tarjeta-perfil-encabezado {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-bottom: 16px;
}

.tarjeta-perfil-encabezado:only-child {
    margin-bottom: 0;
}

.tarjeta-perfil-encabezado h2 {
    display: flex;
    flex: 1 1 0;
    align-items: center;
    gap: 8px;
    min-width: 0;
    margin: 0;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.tarjeta-perfil-encabezado .icon {
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    color: #1f6f7f;
}

.tarjeta-perfil-editar {
    flex-shrink: 0;
    margin-left: auto;
}

.tarjeta-perfil-datos {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 16px;
    margin: 0;
}

.tarjeta-perfil-datos dt {
    color: #446075;
    font-size: .85rem;
}

.tarjeta-perfil-datos dd {
    margin: 3px 0 0;
    color: #153b64;
    font-weight: 700;
    text-align: start;
    overflow-wrap: anywhere;
}

.tarjeta-perfil .tarjeta-perfil-vacia {
    margin: 0;
    color: #446075;
}

.tarjeta-perfil-acciones {
    justify-content: flex-end;
    margin-top: 16px;
}

@media (max-width: 600px) {
    .tarjeta-perfil-datos {
        grid-template-columns: minmax(0, 1fr);
    }

    .tarjeta-perfil-acciones {
        flex-wrap: wrap;
    }

    .tarjeta-perfil-acciones .btn {
        width: 100%;
    }
}
</style>
