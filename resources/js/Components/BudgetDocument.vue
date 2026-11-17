<script setup>
import BrandLogo from './BrandLogo.vue';
import { money, date } from '../support/synaux';

// La vista previa y el documento recibido usan la misma estructura y los datos de la versión.
defineProps({ budget: Object, client: Object, issuer: Object });
</script>
<template>
    <article class="budget-document">
        <header class="document-heading">
            <BrandLogo :with-slogan="false" />
            <div><p class="eyebrow">Presupuesto formal</p><h2>{{ budget.title || 'Tu proyecto' }}</h2><p v-if="budget.budget_id">PRE-{{ budget.budget_id }} · Versión {{ budget.version_number }}</p><p v-else>Vista previa</p></div>
        </header>
        <div class="document-dates"><span>Fecha: {{ date(budget.sent_at ?? budget.created_at ?? new Date().toISOString()) }}</span><span>Vigente hasta {{ date(budget.valid_until) }}</span></div>
        <div class="document-parties">
            <section><h3>Cliente</h3><strong>{{ client?.company || client?.name || 'Cliente de la solicitud' }}</strong><p v-if="client?.company">{{ client.name }}</p><p v-for="field in ['email','phone','address']" :key="field">{{ client?.[field] }}</p></section>
            <section><h3>Emisor</h3><strong>{{ issuer?.name || 'Synaux' }}</strong><p>{{ issuer?.contact }}</p><p>{{ issuer?.email }}</p><p v-if="issuer?.phone">{{ issuer.phone }}</p><p v-if="issuer?.address">{{ issuer.address }}</p></section>
        </div>
        <h3>Detalle de servicios</h3>
        <div class="table-scroll"><table><thead><tr><th>Concepto y alcance</th><th>Cant.</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody><tr v-for="(item,index) in budget.items" :key="index"><td class="preserve-lines">{{ item.description }}</td><td>{{ item.quantity }}</td><td>{{ money(item.unit_price,budget.currency_code) }}</td><td>{{ money(item.quantity * item.unit_price,budget.currency_code) }}</td></tr></tbody></table></div>
        <dl class="data-list document-totals"><div><dt>Subtotal</dt><dd>{{ money(budget.subtotal,budget.currency_code) }}</dd></div><div><dt>Impuestos ({{ budget.tax_rate }}%)</dt><dd>{{ money(budget.total - budget.subtotal,budget.currency_code) }}</dd></div><div><dt><strong>Total · {{ budget.currency_code }}</strong></dt><dd><strong>{{ money(budget.total,budget.currency_code) }}</strong></dd></div></dl>
        <h3>Plazos y condiciones</h3><p>Entrega estimada: {{ budget.estimated_delivery_days }} días desde el inicio acordado.</p><p class="preserve-lines">{{ budget.terms }}</p>
    </article>
</template>
<style scoped>
/* El documento conserva superficies claras, marca proporcional y columnas legibles en móvil. */
.budget-document { background: #fff; color: #153b64; padding: clamp(1rem, 3vw, 2rem); border: 1px solid rgb(31 111 127 / 18%); border-radius: .875rem; }
.document-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1.5rem; }
.document-heading :deep(.brand-logo) { width: 100px; height: auto; }
.document-heading h2 { margin-bottom: .5rem; font-size: 1.65rem; overflow-wrap: anywhere; }
.document-dates { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; border-block: 1px solid rgb(31 111 127 / 18%); padding-block: .75rem; margin-block: 1.5rem; }
.document-parties { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.document-parties section { background: #f5f9fa; border-radius: .5rem; padding: 1rem; overflow-wrap: anywhere; }
.document-parties p { margin: .25rem 0 0; }
.document-parties p:empty { display: none; }
.document-totals { margin: 1rem 0 1.5rem auto; max-width: 340px; }
@media (max-width: 650px) { .document-parties { grid-template-columns: 1fr; } }
</style>
