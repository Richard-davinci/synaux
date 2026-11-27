<script setup>
import BudgetDocument from './DocumentoPresupuesto.vue';

// Los dos renglones ilustrativos reciben los conceptos e importes del ejemplo; la cantidad unitaria permite sumar sus precios sin depender del catálogo.
const exampleItems = [
    {
        description: 'Sitio institucional\nDiseño adaptable a celulares, desarrollo y carga inicial de hasta cinco secciones.',
        quantity: 1,
        unit_price: 676360
    },
    {
        description: 'Identidad visual\nLogotipo y variantes, paleta, tipografías y guía básica de uso.',
        quantity: 1,
        unit_price: 537164
    },
];
// El subtotal suma los renglones y el total aplica el IVA del ejemplo en centavos para conservar los decimales del documento.
const exampleSubtotal = exampleItems.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);
const exampleTaxRate = 21;
const exampleTotal = Math.round(exampleSubtotal * (1 + exampleTaxRate / 100) * 100) / 100;
// El documento es ilustrativo y no consulta ni modifica cuentas, solicitudes o precios publicados.
const exampleDate = new Date();
const exampleBudget = {
    title: 'Sitio e identidad para tu organización', version_number: 1, created_at: exampleDate.toISOString(),
    valid_until: new Date(exampleDate.getTime() + 30 * 86400000).toISOString().slice(0, 10),
    currency_code: 'ARS', tax_rate: exampleTaxRate, estimated_delivery_days: 30,
    items: exampleItems, subtotal: exampleSubtotal, total: exampleTotal,
    terms: 'Incluye una ronda de ajustes por servicio. Las revisiones adicionales se cotizan aparte.',
};
</script>

<template>
    <!-- La muestra reutiliza el documento de la plataforma con valores ilustrativos. -->
    <figure id="presupuesto-de-ejemplo" class="budget-example" tabindex="-1" aria-labelledby="budget-example-caption">
        <figcaption id="budget-example-caption" class="example-label">Documento de ejemplo · Importes ilustrativos
        </figcaption>
        <BudgetDocument compact example :budget="exampleBudget"
                        :client="{ name: 'Cliente de ejemplo', company: 'Tu organización' }"
                        :issuer="{ name: 'synaux' }"/>
    </figure>
</template>

<style scoped>
/* El ancla respeta el encabezado cuando el documento se abre en el prototipo. */
.budget-example {
    min-width: 0;
    margin: 0;
    scroll-margin-top: max(0px, calc(var(--navbar-height, 0px) - 8px));
    padding: 12px;
    background: #fff;
    border: 1px solid #1f6f7f26;
    border-radius: 16px;
    box-shadow: 0 10px 28px #153b6412;
}

.budget-example:focus-visible {
    outline: 2px solid #1f6f7f;
    outline-offset: 3px;
}

.example-label {
    margin: 0;
    padding: 4px 8px 12px;
    font-size: .8rem;
    color: #446075;
}
</style>
