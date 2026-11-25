<script setup>
import {computed} from 'vue';
import FormField from '../UI/FormField.vue';

// La ubicación se edita tanto para el perfil como para el domicilio, con códigos de autocompletado separados.
const props = defineProps({
    errores: {type: Object, required: true},
    prefijo: {type: String, required: true},
    facturacion: Boolean,
});
const ubicacion = defineModel({type: Object, required: true});
const provincias = [
    'Buenos Aires', 'Ciudad Autónoma de Buenos Aires', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba',
    'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones',
    'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe',
    'Santiago del Estero', 'Tierra del Fuego', 'Tucumán',
];
const esArgentina = pais => pais?.trim().toLowerCase() === 'argentina';
const mostrarProvincias = computed(() => esArgentina(ubicacion.value.country));
const autocompletar = computed(() => props.facturacion ? 'section-facturacion billing' : 'section-ubicacion');
const error = campo => props.errores[props.prefijo + '.' + campo];

function cambiarPais(pais) {
    const eraArgentina = mostrarProvincias.value;
    ubicacion.value.country = pais;
    if ((eraArgentina && !mostrarProvincias.value)
        || (mostrarProvincias.value && !provincias.includes(ubicacion.value.region))) {
        ubicacion.value.region = '';
    }
}
</script>

<template>
    <!-- Argentina ofrece la lista de provincias. El resto admite la región que corresponda al domicilio. -->
    <FormField :model-value="ubicacion.country" label="País" :autocomplete="autocompletar + ' country-name'"
               placeholder="Ej.: Argentina" maxlength="80" :error="error('country')" @update:model-value="cambiarPais"/>
    <FormField v-if="mostrarProvincias" v-model="ubicacion.region" label="Provincia" type="select"
               :options="provincias" placeholder="Seleccioná una provincia" :error="error('region')"/>
    <FormField v-else v-model="ubicacion.region" label="Provincia, estado o región"
               :autocomplete="autocompletar + ' address-level1'" maxlength="80" :error="error('region')"/>
    <FormField v-model="ubicacion.city" label="Localidad o ciudad" :autocomplete="autocompletar + ' address-level2'"
               maxlength="80" :error="error('city')"/>
</template>
