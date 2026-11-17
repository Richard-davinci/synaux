<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Presupuesto Synaux</title>
<style>
/* La impresión usa las fuentes y los recursos locales de la marca con paginación de tablas. */
@font-face { font-family: Nunito; src: url("{{ resource_path('fonts/Nunito-Regular.ttf') }}") format("truetype"); }
@font-face { font-family: SpaceGrotesk; src: url("{{ resource_path('fonts/SpaceGrotesk-Bold.ttf') }}") format("truetype"); }
@font-face { font-family: Nunito; font-weight: bold; src: url("{{ resource_path('fonts/Nunito-Bold.ttf') }}") format("truetype"); }
@page { margin: 32px 36px; }
body { font-family: Nunito, sans-serif; font-size: 12px; color: #153b64; line-height: 1.45; }
h1,h2,h3 { font-family: SpaceGrotesk, sans-serif; }
h1 { font-size: 23px; margin: 10px 0 4px; } h2 { font-size: 16px; margin-top: 22px; } h3 { margin: 0 0 8px; font-size: 13px; }
p { margin: 3px 0; overflow-wrap: break-word; }
.header { border-bottom: 2px solid #2db3a3; padding-bottom: 15px; }
.header img { width: 150px; height: auto; }
.meta { color: #446075; margin: 12px 0; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.parties td { width: 50%; background: #f5f9fa; padding: 12px; vertical-align: top; border: 4px solid white; }
.items th { background: #f5f9fa; text-align: left; font-size: 10px; }
.items td, .items th { padding: 9px 7px; border-bottom: 1px solid #d2e7e9; }
.items td { vertical-align: top; overflow-wrap: break-word; }
.items thead { display: table-header-group; } .items tr { page-break-inside: avoid; }
.amount { text-align: right; } .totals { width: 48%; margin: 14px 0 0 auto; }
.totals td { padding: 7px; border-bottom: 1px solid #d2e7e9; } .total { background: #f5f9fa; font-size: 13px; }
.terms { white-space: pre-wrap; } .footer { margin-top: 24px; color: #446075; border-top: 1px solid #d2e7e9; padding-top: 8px; }
</style>
</head>
<body>
@php
    // El documento muestra la moneda de la versión, con un formato independiente de la configuración del equipo.
    $money = fn ($value) => $budget['currency_code'].' '.number_format((float) $value, 2, ',', '.');
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : 'A coordinar';
    $client = $budget['client'] ?? [];
    $issuer = $budget['issuer'] ?? [];
@endphp
<div class="header">
<img src="{{ $logo }}" alt="Synaux">
<h1>{{ $budget['title'] }}</h1>
<p>Presupuesto PRE-{{ $budget['budget_id'] }} · Versión {{ $budget['version_number'] }} · {{ $budget['status'] === 'draft' ? 'Borrador' : 'Presupuesto formal' }}</p>
</div>
<p class="meta">Fecha: {{ $date($budget['sent_at'] ?? $budget['created_at']) }} · Vigente hasta: {{ $date($budget['valid_until']) }}</p>
<table class="parties"><tr>
<td><h3>Cliente</h3><p>{{ $client['company'] ?? $client['name'] ?? 'Cliente de la solicitud' }}</p>@if(!empty($client['company']))<p>{{ $client['name'] ?? '' }}</p>@endif
@foreach(['email','phone','address'] as $field) @if(!empty($client[$field]))<p>{{ $client[$field] }}</p>@endif @endforeach</td>
<td><h3>Emisor</h3><p>{{ $issuer['name'] ?? 'Synaux' }}</p><p>{{ $issuer['contact'] ?? '' }}</p><p>{{ $issuer['email'] ?? '' }}</p></td>
</tr></table>
<h2>Detalle de servicios</h2>
<table class="items"><thead><tr><th style="width:46%">Concepto y alcance</th><th style="width:10%">Cant.</th><th style="width:22%">Precio unitario</th><th style="width:22%">Subtotal</th></tr></thead><tbody>
@foreach($budget['items'] as $item)<tr><td class="terms">{{ $item->description }}</td><td>{{ $item->quantity }}</td><td class="amount">{{ $money($item->unit_price) }}</td><td class="amount">{{ $money($item->quantity * $item->unit_price) }}</td></tr>@endforeach
</tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="amount">{{ $money($budget['subtotal']) }}</td></tr><tr><td>Impuestos ({{ $budget['tax_rate'] }}%)</td><td class="amount">{{ $money($budget['total'] - $budget['subtotal']) }}</td></tr><tr class="total"><td>Total</td><td class="amount">{{ $money($budget['total']) }}</td></tr></table>
<h2>Plazos y condiciones</h2><p>Entrega estimada: {{ $budget['estimated_delivery_days'] }} días desde el inicio acordado.</p>
<p class="terms">{{ $budget['terms'] }}</p>
<p class="footer">Synaux · Creciendo juntos</p>
</body>
</html>
