<script setup>
import {computed} from 'vue';
import BrandLogo from '../Brand/BrandLogo.vue';
import I from '../UI/AppIcon.vue';
import {date, money} from '../../support/formatters';

// El editor y el documento recibido comparten los datos de una versión, sin consultar perfiles actuales.
const props = defineProps({budget: Object, client: Object, issuer: Object, compact: Boolean, example: Boolean, estimacion: Object});
const pending = computed(() => (props.budget.items ?? []).some(item => item.unit_price === '' || item.unit_price == null));
const tax = computed(() => Math.round((Number(props.budget.total ?? 0) - Number(props.budget.subtotal ?? 0)) * 100) / 100);
const scope = description => String(description ?? '').split('\n').slice(1).join('\n');
const concept = description => String(description ?? '').split('\n')[0] || 'Servicio por definir';
</script>
<template>
    <article class="budget-document" :class="{ 'budget-document-compact': compact, 'documento-estimacion': estimacion }">
        <header class="document-heading">
            <BrandLogo :with-slogan="false" variant="white"/>
            <div>
                <p class="document-label">{{ estimacion ? 'Estimación' : 'Presupuesto' }}</p>
                <!-- La estimación en curso y la muestra pública se identifican sin numeración oficial. -->
                <p v-if="estimacion" class="document-number">Vista previa en vivo</p>
                <p v-else-if="example" class="document-number">Propuesta de ejemplo</p>
                <p v-else class="document-number">{{ budget.budget_id ? 'PRE-' + budget.budget_id : 'Borrador' }} · Versión
                    {{ budget.version_number ?? 1 }}</p>
            </div>
        </header>
        <div v-if="estimacion" class="document-dates">Importes orientativos</div>
        <div v-else class="document-dates"><span><I name="clock"/>Fecha: {{
                date(budget.sent_at ?? budget.created_at ?? new Date().toISOString())
            }}</span><span>Válido hasta: {{ date(budget.valid_until) }}</span></div>
        <div class="document-content">
            <component :is="estimacion ? 'h3' : 'h2'" class="document-title">{{ budget.title || 'Tu proyecto' }}</component>
            <details v-if="estimacion?.alcance" class="documento-alcance">
                <summary>Descripción del proyecto</summary>
                <p class="preserve-lines">{{ estimacion.alcance }}</p>
            </details>
            <div class="document-parties">
                <section><h3><I name="user"/>Cliente</h3>
                    <div class="party-content"><strong>{{
                            client?.company || client?.name || (estimacion ? 'Datos por completar' : 'Cliente por definir')
                        }}</strong>
                        <p v-if="client?.company && client?.name">{{ client.name }}</p>
                        <p v-if="client?.cuit">CUIT {{ client.cuit }}</p>
                        <p v-for="field in ['email','phone','address']" :key="field">{{ client?.[field] }}</p></div>
                </section>
                <section><h3><I name="folder"/>Proveedor</h3>
                    <div class="party-content"><strong>{{ issuer?.name || 'synaux' }}</strong>
                        <!-- El CUIT pertenece a la identidad guardada en el presupuesto formal. -->
                        <p v-if="!estimacion && issuer?.cuit">CUIT {{ issuer.cuit }}</p>
                        <p>{{ issuer?.contact }}</p>
                        <p>{{ issuer?.email }}</p>
                        <p v-if="issuer?.phone">{{ issuer.phone }}</p>
                        <p v-if="issuer?.address">{{ issuer.address }}</p></div>
                </section>
            </div>
            <h3 class="document-section-title"><I name="file"/>Detalle de servicios</h3>
            <div class="table-scroll document-items">
                <table>
                    <thead>
                    <tr>
                        <th>Concepto y alcance</th>
                        <th v-if="!compact">Cant.</th>
                        <th v-if="!compact">Precio unit.</th>
                        <th>{{ estimacion ? 'Estimado' : compact ? 'Importe' : 'Subtotal' }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item,index) in budget.items" :key="index">
                        <td><strong>{{ concept(item.description) }}</strong>
                            <p v-if="(!compact || example || estimacion) && scope(item.description)" class="preserve-lines">
                                {{ scope(item.description) }}</p></td>
                        <td v-if="!compact">{{ item.quantity }}</td>
                        <td v-if="!compact">{{
                                item.unit_price === '' || item.unit_price == null ? 'A cotizar' : money(item.unit_price, budget.currency_code)
                            }}
                        </td>
                        <td v-if="estimacion">{{ item.importe }}</td>
                        <td v-else>{{
                                item.unit_price === '' || item.unit_price == null ? 'A cotizar' : money(Math.round(item.quantity * item.unit_price * 100) / 100, budget.currency_code)
                            }}
                        </td>
                    </tr>
                    <tr v-if="!budget.items?.length">
                        <td :colspan="compact ? 2 : 4">{{ estimacion ? 'Elegí un servicio para ver su referencia.' : 'Agregá los servicios para preparar el presupuesto.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <!-- La estimación puede contener rangos o conceptos pendientes; el documento emitido muestra subtotal, IVA y total. -->
            <div v-if="estimacion && budget.items?.length" class="totales-estimacion" role="status" aria-atomic="true">
                <dl class="document-totals">
                    <div class="document-grand-total">
                        <dt>{{ estimacion.etiqueta }}</dt>
                        <dd>{{ estimacion.importe }}</dd>
                    </div>
                </dl>
                <p v-if="estimacion.pendientes" class="document-warning">{{ estimacion.pendientes }}</p>
                <p v-if="estimacion.recalculo" class="document-warning">{{ estimacion.recalculo }}</p>
            </div>
            <dl v-else-if="!estimacion" class="document-totals" aria-live="polite">
                <div>
                    <dt>Subtotal{{ pending ? ' cotizado' : '' }}</dt>
                    <dd>{{ money(budget.subtotal, budget.currency_code) }}</dd>
                </div>
                <div>
                    <dt>{{ Number(budget.tax_rate) > 0 ? 'IVA (' + budget.tax_rate + '%)' : 'IVA no incluido' }}</dt>
                    <dd>{{ money(tax, budget.currency_code) }}</dd>
                </div>
                <div class="document-grand-total">
                    <dt>{{ pending ? 'Total parcial' : 'Total' }} · {{ budget.currency_code }}</dt>
                    <dd>{{ money(budget.total, budget.currency_code) }}</dd>
                </div>
            </dl>
            <p v-if="!estimacion && pending" class="document-warning">Hay servicios pendientes de cotizar. El total se completará al
                definir sus precios.</p>
        </div>
        <!-- La landing resume las condiciones; las vistas de trabajo conservan el documento completo. -->
        <section v-if="estimacion" class="document-conditions document-conditions-summary">
            <p v-if="estimacion.plazo"><strong>Plazo deseado</strong> · {{ estimacion.plazo }}</p>
            <p>synaux confirmará el precio, los plazos y las condiciones en el presupuesto formal.</p>
        </section>
        <section v-else-if="compact" class="document-conditions document-conditions-summary">
            <p v-if="example">Entrega estimada: {{ budget.estimated_delivery_days }} días desde el inicio acordado.</p>
            <p v-else>Entrega estimada: {{ budget.estimated_delivery_days }} días. Alcance y condiciones a acordar en la propuesta formal.</p>
            <p v-if="example && budget.terms">{{ budget.terms }}</p>
        </section>
        <section v-else class="document-conditions"><h3 class="document-section-title"><I name="check"/>Plazos y
            condiciones</h3>
            <div><p>Entrega estimada: {{ budget.estimated_delivery_days }} días desde el inicio acordado.</p>
                <p class="preserve-lines">
                    {{ budget.terms || 'Las condiciones se completarán antes de emitir el presupuesto.' }}</p></div>
        </section>
        <!-- Las cuotas pertenecen a la versión que consulta el cliente. -->
        <section v-if="!compact && !estimacion && budget.plan_pago?.cuotas?.length" class="document-conditions">
            <h3 class="document-section-title"><I name="receipt"/>Plan de pago</h3>
            <div>
                <p v-for="cuota in budget.plan_pago.cuotas" :key="cuota.concepto">
                    <strong>{{ cuota.titulo }}<template v-if="cuota.porcentaje"> · {{ cuota.porcentaje }}%</template>
                        · {{ money(cuota.importe_centavos / 100, budget.currency_code) }}</strong>
                    · {{ cuota.momento }}
                </p>
                <p v-if="budget.plan_pago.instrucciones" class="preserve-lines">{{ budget.plan_pago.instrucciones }}</p>
            </div>
        </section>
        <footer v-if="!compact" class="document-footer">{{ issuer?.name || 'synaux' }}
            <template v-if="issuer?.email"> · {{ issuer.email }}</template>
            <template v-if="issuer?.phone"> · {{ issuer.phone }}</template>
        </footer>
    </article>
</template>
<style scoped>
/* El documento organiza marca, partes, conceptos y totales con la paleta de Synaux. */
.budget-document {
    container-type: inline-size;
    background: #fff;
    color: #153b64;
    border: 1px solid #1f6f7f33;
    border-radius: 12px;
    overflow: hidden;
}

.document-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 24px;
    background: #153b64;
    color: #fff;
}

.document-heading :deep(.brand-logo) {
    width: 100px;
    height: auto;
    flex-shrink: 0;
}

/* El encabezado mantiene contraste incluso dentro de páginas con estilos de párrafo propios. */
.budget-document .document-heading p {
    color: #fff;
}

.document-heading > div {
    text-align: right;
    min-width: 0;
}

.document-label {
    margin: 0 0 8px;
    text-transform: uppercase;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.45rem;
    font-weight: 700;
}

.document-number {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    background: #ffffff1f;
    margin: 0;
    font-size: .8rem;
}

.document-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 20px;
    justify-content: space-between;
    padding: 12px 24px;
    background: #1f6f7f;
    color: white;
    font-size: .8rem;
}

.document-dates span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.document-dates .icon {
    width: 16px;
    height: 16px;
}

.document-content {
    padding: 24px;
}

.document-title {
    margin: 0 0 20px;
    font-size: 1.25rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.document-parties {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.document-parties section {
    background: #f5f9fa;
    border: 1px solid #1f6f7f1f;
    border-radius: 8px;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.document-parties h3 {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 10px 12px;
    border-bottom: 1px solid #1f6f7f1f;
    font-size: .75rem;
    text-transform: uppercase;
}

.document-parties .icon {
    width: 14px;
    height: 14px;
}

.party-content {
    padding: 12px;
    font-size: .85rem;
}

.party-content p {
    margin: 4px 0 0;
    color: #446075;
}

.party-content p:empty {
    display: none;
}

.document-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    font-size: .85rem;
    color: #1f6f7f;
    text-transform: uppercase;
}

.document-section-title .icon {
    width: 18px;
    height: 18px;
}

.document-items {
    border: 1px solid #1f6f7f22;
    border-radius: 8px;
}

.document-items table {
    width: 100%;
    border-collapse: collapse;
    font-size: .8rem;
}

.document-items th {
    background: #f5f9fa;
    text-align: left;
    color: #446075;
    font-weight: 600;
}

.document-items td, .document-items th {
    padding: 10px 8px;
    border-bottom: 1px solid #1f6f7f1f;
    vertical-align: top;
}

.document-items tr:last-child td {
    border-bottom: 0;
}

.document-items th:first-child {
    width: 45%;
}

.document-items td:first-child {
    overflow-wrap: anywhere;
}

.document-items td:not(:first-child), .document-items th:not(:first-child) {
    text-align: right;
}

.document-items td p {
    margin: 4px 0 0;
    color: #446075;
    font-size: .75rem;
}

.document-totals {
    max-width: 320px;
    margin: 24px 0 0 auto;
    border: 1px solid #1f6f7f22;
    border-radius: 8px;
    overflow: hidden;
    font-size: .85rem;
}

.document-totals > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
}

.document-totals dd {
    margin: 0;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.document-grand-total {
    background: #f5f9fa;
    color: #153b64;
    font-weight: 700;
    border-top: 1px solid #1f6f7f22;
}

.document-grand-total dd {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.15rem;
}

.document-warning {
    margin-top: 12px;
    color: #446075;
    font-size: .85rem;
}

.document-conditions {
    background: #f5f9fa;
    padding: 20px 24px;
    border-top: 1px solid #1f6f7f1f;
}

.document-conditions > div {
    border: 1px solid #1f6f7f1f;
    border-radius: 6px;
    background: #fff;
    padding: 12px;
    font-size: .8rem;
}

.document-conditions p {
    margin: 0 0 8px;
}

.document-conditions p:last-child {
    margin-bottom: 0;
}

.document-footer {
    display: block;
    padding: 12px 24px;
    background: #f5f9fa;
    border-top: 1px solid #1f6f7f1f;
    color: #446075;
    text-align: center;
    font-size: .75rem;
    overflow-wrap: anywhere;
}

/* La muestra pública prioriza conceptos, IVA y total sin reducir la legibilidad ni agregar scroll. */
.budget-document-compact .document-heading {
    padding: 14px 16px;
    gap: 12px;
}

.budget-document-compact .document-heading :deep(.brand-logo) {
    width: 64px;
}

.budget-document-compact .document-label {
    font-size: 1.15rem;
    margin-bottom: 4px;
}

.budget-document-compact .document-number {
    font-size: .75rem;
}

.budget-document-compact .document-dates {
    padding: 8px 16px;
    gap: 6px 12px;
    font-size: .75rem;
}

.budget-document-compact .document-content {
    padding: 16px;
}

.budget-document-compact .document-title {
    font-size: 1.05rem;
    margin-bottom: 12px;
}

.budget-document-compact .document-parties {
    gap: 10px;
    margin-bottom: 16px;
}

.budget-document-compact .document-parties h3 {
    padding: 6px 10px;
}

.budget-document-compact .party-content {
    padding: 8px 10px;
}

.budget-document-compact .document-section-title {
    margin-bottom: 8px;
    font-size: .75rem;
}

.budget-document-compact .document-items table {
    min-width: 0;
}

.budget-document-compact .document-items th:first-child {
    width: auto;
}

.budget-document-compact .document-items td:last-child {
    white-space: nowrap;
}

.budget-document-compact .document-totals {
    margin-top: 12px;
}

.budget-document-compact .document-totals > div {
    padding: 8px 12px;
}

.budget-document-compact .document-conditions-summary {
    padding: 12px 16px;
    font-size: .75rem;
}

.budget-document-compact .document-conditions-summary p {
    margin: 0;
}

.budget-document-compact .document-conditions-summary p + p {
    margin-top: 4px;
}

/* La descripción se puede consultar sin desplazar los importes mientras se escribe. */
.documento-alcance {
    margin-bottom: 12px;
    font-size: .8rem;
    overflow-wrap: anywhere;
}

.documento-alcance summary {
    color: #1f6f7f;
    cursor: pointer;
    font-weight: 700;
}

.documento-alcance p {
    margin: 8px 0 0;
}

/* Los rangos de la solicitud pueden ocupar varias líneas en el documento compacto. */
.documento-estimacion .document-items td:last-child {
    width: 44%;
    white-space: normal;
    overflow-wrap: anywhere;
}

.documento-estimacion .document-grand-total {
    flex-wrap: wrap;
    border-top: 0;
}

.documento-estimacion .document-grand-total dd {
    margin-left: auto;
    overflow-wrap: anywhere;
}

.documento-estimacion .document-warning + .document-warning {
    margin-top: 6px;
}

.documento-estimacion .document-conditions {
    overflow-wrap: anywhere;
}

@container (max-width: 420px) {
    .document-heading {
        padding: 18px;
        gap: 12px;
    }

    .document-label {
        font-size: 1.1rem;
    }

    .document-content {
        padding: 16px;
    }

    .document-dates {
        padding-inline: 16px;
    }

    .document-parties {
        gap: 8px;
    }

    .party-content {
        padding: 10px;
        font-size: .8rem;
    }

    .document-conditions {
        padding: 16px;
    }

    .document-items table {
        min-width: 380px;
    }
}

.budget-document.budget-document-compact .document-items table {
    min-width: 0;
}

@container (max-width: 320px) {
    .document-parties {
        grid-template-columns: 1fr;
    }
}
</style>
