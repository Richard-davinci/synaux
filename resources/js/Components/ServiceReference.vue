<script setup>
import { money, date } from '../support/synaux';

// Muestra los datos del servicio seleccionado y la referencia inicial publicada por el servidor.
defineProps({ service: Object, reference: Object, saved: Boolean });
</script>
<template>
    <section v-if="service" class="service-reference" aria-label="Referencia inicial del servicio">
        <div class="reference-heading">
            <div><p class="eyebrow">{{ saved ? 'Estimación guardada' : 'Referencia inicial' }}</p><h3>{{ service.name }}</h3></div>
            <div v-if="reference" class="reference-price">
                <strong>{{ money(reference.min,reference.currency) }}<template v-if="reference.max !== reference.min"> – {{ money(reference.max,reference.currency) }}</template></strong>
                <span v-if="!saved">{{ reference.segment ? 'Base para ' + reference.segment : 'Base del servicio' }} · {{ reference.currency }}</span>
            </div>
            <strong v-else>Precio a definir al revisar el alcance</strong>
        </div>
        <p>{{ service.summary }}</p>
        <ul v-if="service.includes?.length"><li v-for="item in service.includes" :key="item">{{ item }}</li></ul>
        <p v-if="reference" class="reference-note">{{ saved ? 'Calculada el' : 'Referencia vigente desde' }} {{ date(reference.date) }}. {{ saved ? 'Conservamos el importe y las respuestas de tu estimación.' : 'Las respuestas pueden ajustar el importe.' }} El presupuesto formal se prepara al revisar tu solicitud.</p>
        <p v-else class="reference-note">Contanos qué necesitás para que Synaux prepare una propuesta.</p>
    </section>
</template>
<style scoped>
/* La ficha usa las superficies y los colores de Synaux y se adapta al ancho disponible. */
.service-reference { padding: 1.5rem; margin-block: 1.5rem; background: #f5f9fa; border: 1px solid rgb(31 111 127 / 18%); border-radius: .875rem; color: #153b64; }
.reference-heading { display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem; margin-bottom: .75rem; }
.reference-heading .eyebrow { margin: 0 0 .35rem; }
.reference-heading h3 { margin: 0; font-family: 'Space Grotesk', sans-serif; font-weight: 700; }
.reference-price { display: grid; gap: .25rem; }
.reference-price strong { font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; }
.reference-price span, .reference-note, small { color: #446075; }
.service-reference ul { padding-left: 1.25rem; margin-bottom: 1rem; }
.service-reference a { color: #1f6f7f; text-decoration: underline; }
.service-reference .reference-note { margin-bottom: .5rem; }
</style>
