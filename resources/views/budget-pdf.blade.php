<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto synaux</title>
    <style>
        /* La impresión usa las fuentes y los recursos locales de la marca con paginación de tablas. */
        @font-face {
            font-family: Nunito;
            src: url("{{ resource_path('fonts/Nunito-Regular.ttf') }}") format("truetype");
        }

        @font-face {
            font-family: SpaceGrotesk;
            src: url("{{ resource_path('fonts/SpaceGrotesk-Bold.ttf') }}") format("truetype");
        }

        @font-face {
            font-family: Nunito;
            font-weight: bold;
            src: url("{{ resource_path('fonts/Nunito-Bold.ttf') }}") format("truetype");
        }

        @page {
            margin: 32px 36px;
        }

        body {
            font-family: Nunito, sans-serif;
            font-size: 12px;
            color: #153b64;
            line-height: 1.45;
        }

        h1, h2, h3 {
            font-family: SpaceGrotesk, sans-serif;
        }

        h1 {
            font-size: 23px;
            margin: 10px 0 4px;
        }

        h2 {
            font-size: 16px;
            margin: 16px 0 8px;
            page-break-after: avoid;
        }

        h3 {
            margin: 0 0 8px;
            font-size: 13px;
        }

        p {
            margin: 3px 0;
            overflow-wrap: break-word;
        }

        .header {
            border-bottom: 2px solid #2db3a3;
            padding-bottom: 12px;
        }

        .header img {
            width: 150px;
            height: auto;
        }

        .meta {
            color: #446075;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .parties td {
            width: 50%;
            background: #f5f9fa;
            padding: 10px;
            vertical-align: top;
            border: 4px solid white;
        }

        /* Los datos de las partes mantienen la jerarquía sin extender el bloque por sus renglones vacíos. */
        .parties p {
            margin: 2px 0;
            line-height: 1.28;
        }

        .items th {
            background: #f5f9fa;
            text-align: left;
            font-size: 10px;
        }

        .items td, .items th {
            padding: 9px 7px;
            border-bottom: 1px solid #d2e7e9;
        }

        .items td {
            vertical-align: top;
            overflow-wrap: break-word;
        }

        .items thead {
            display: table-header-group;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .amount, .items th.amount {
            text-align: right;
        }

        .quantity, .items th.quantity {
            text-align: center;
        }

        .totals {
            width: 48%;
            margin: 10px 0 0 auto;
            page-break-inside: avoid;
        }

        .totals td {
            padding: 5px 7px;
            border-bottom: 1px solid #d2e7e9;
        }

        .total {
            background: #f5f9fa;
            font-size: 13px;
        }

        .terms {
            white-space: pre-wrap;
        }

        /* Las cuotas y sus instrucciones se leen juntas si el bloque cabe en una página. */
        .payment-plan {
            margin-top: 12px;
            padding: 8px 10px;
            border: 1px solid #d2e7e9;
            background: #f5f9fa;
            page-break-inside: avoid;
        }

        .payment-plan h2 {
            margin: 0 0 6px;
        }

        .payment-plan p {
            line-height: 1.3;
            margin: 2px 0;
        }

        .payment-plan h3 {
            margin: 6px 0 3px;
        }

        /* Las instrucciones breves quedan al lado de las cuotas. Las extensas usan todo el ancho. */
        .payment-columns .payment-installments {
            float: left;
            width: 55%;
        }

        .payment-columns .payment-transfer {
            float: right;
            width: 42%;
        }

        .payment-columns h3 {
            margin-top: 0;
        }

        .payment-clear {
            clear: both;
        }

        .footer {
            margin-top: 12px;
            page-break-before: avoid;
            color: #446075;
            border-top: 1px solid #d2e7e9;
            padding-top: 8px;
        }
    </style>
</head>
<body>
@php
    // El documento muestra la moneda de la versión, con un formato independiente de la configuración del equipo.
    $money = fn ($value) => $budget['currency_code'].' '.number_format((float) $value, 2, ',', '.');
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : 'A coordinar';
    $client = $budget['client'] ?? [];
    $issuer = $budget['issuer'] ?? [];
    $transfer = $budget['plan_pago']['instrucciones'] ?? '';
    $transferInColumns = $transfer !== '' && mb_strlen($transfer) <= 240 && substr_count($transfer, "\n") <= 4;
    // Las nuevas propuestas muestran un importe por concepto; las versiones históricas retienen sus cantidades.
    $mostrarCantidad = collect($budget['items'])->contains(fn ($item) => (float) $item->quantity !== 1.0);
@endphp
<div class="header">
    <img src="{{ $logo }}" alt="synaux">
    <h1>{{ $budget['title'] }}</h1>
    <p>Presupuesto PRE-{{ $budget['budget_id'] }} · Versión {{ $budget['version_number'] }}
        · {{ $budget['status'] === 'draft' ? 'Borrador' : 'Presupuesto formal' }}</p>
</div>
<p class="meta">Fecha: {{ $date($budget['sent_at'] ?? $budget['created_at']) }} · Vigente
    hasta: {{ $date($budget['valid_until']) }}</p>
<table class="parties">
    <tr>
        <td><h3>Cliente</h3>
            <p>{{ $client['company'] ?? $client['name'] ?? 'Cliente de la solicitud' }}</p>@if(!empty($client['company']))
                <p>{{ $client['name'] ?? '' }}</p>
            @endif
            @foreach(['email','phone','address'] as $field)
                @if(!empty($client[$field]))
                    <p>{{ $client[$field] }}</p>
                @endif
            @endforeach</td>
        <td><h3>Emisor</h3>
            <p>{{ $issuer['name'] ?? 'synaux' }}</p>
            {{-- El CUIT se imprime con los datos de la versión guardada del presupuesto. --}}
            @if(!empty($issuer['cuit']))
                <p>CUIT {{ $issuer['cuit'] }}</p>
            @endif
            <p>{{ $issuer['contact'] ?? '' }}</p>
            <p>{{ $issuer['email'] ?? '' }}</p>
            {{-- La identidad de emisión corresponde a la instantánea guardada con esta versión. --}}
            @foreach(['phone','address'] as $field)
                @if(!empty($issuer[$field]))
                    <p>{{ $issuer[$field] }}</p>
                @endif
            @endforeach</td>
    </tr>
</table>
<h2>Detalle de servicios</h2>
<table class="items">
    <thead>
    <tr>
        <th style="width:{{ $mostrarCantidad ? '46%' : '72%' }}">Concepto y alcance</th>
        @if($mostrarCantidad)
            <th class="quantity" style="width:10%">Cant.</th>
            <th class="amount" style="width:22%">Precio unitario</th>
            <th class="amount" style="width:22%">Subtotal</th>
        @else
            <th class="amount" style="width:28%">Importe</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($budget['items'] as $item)
        <tr>
            <td class="terms">{{ $item->description }}</td>
            @if($mostrarCantidad)
                <td class="quantity">{{ $item->quantity }}</td>
                <td class="amount">{{ $money($item->unit_price) }}</td>
                <td class="amount">{{ $money($item->quantity * $item->unit_price) }}</td>
            @else
                <td class="amount">{{ $money($item->unit_price) }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
<table class="totals">
    <tr>
        <td>Subtotal</td>
        <td class="amount">{{ $money($budget['subtotal']) }}</td>
    </tr>
    <tr>
        <td>Impuestos ({{ $budget['tax_rate'] }}%)</td>
        <td class="amount">{{ $money($budget['total'] - $budget['subtotal']) }}</td>
    </tr>
    <tr class="total">
        <td>Total</td>
        <td class="amount">{{ $money($budget['total']) }}</td>
    </tr>
</table>
<h2>Plazos y condiciones</h2>
<p>Entrega estimada: {{ $budget['estimated_delivery_days'] }} días desde el inicio acordado.</p>
<p class="terms">{{ $budget['terms'] }}</p>
{{-- El PDF conserva las cuotas y las instrucciones de la versión emitida. --}}
@if(!empty($budget['plan_pago']['cuotas']))
    <div class="payment-plan{{ $transferInColumns ? ' payment-columns' : '' }}">
        <h2>Plan de pago</h2>
        <div class="payment-installments">
            @foreach($budget['plan_pago']['cuotas'] as $cuota)
                <p><strong>{{ $cuota['titulo'] }}@if(isset($cuota['porcentaje'])) · {{ $cuota['porcentaje'] }}%@endif · {{ $money($cuota['importe_centavos'] / 100) }}</strong></p>
                <p>{{ $cuota['momento'] }}</p>
            @endforeach
        </div>
        @if($transfer !== '')
            <div class="payment-transfer">
                <h3>Datos para la transferencia</h3>
                <p class="terms">{{ $transfer }}</p>
            </div>
        @endif
        <div class="payment-clear"></div>
    </div>
@endif
<p class="footer">synaux · Creciendo juntos</p>
</body>
</html>
