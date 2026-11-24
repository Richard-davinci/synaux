<?php

namespace App\Services\Presupuestos;

use Illuminate\Support\Facades\DB;

class ConsultasPresupuestos
{
    // Resuelve los dos orígenes del presupuesto y filtra versiones según cliente, emisor y estado.
    public function quotes(?int $userId, bool $management = false, ?int $id = null, ?int $budgetId = null): array
    {
        // Los dos orígenes del DER (solicitud directa o propuesta elegida) se resuelven antes de autorizar.
        return DB::table('budget_versions as v')->join('budgets as b', 'b.id', '=', 'v.budget_id')
            ->leftJoin('proposals as p', 'p.id', '=', 'b.proposal_id')
            ->leftJoin('project_requests as r', fn($j) => $j->on('r.id', '=', DB::raw('COALESCE(b.project_request_id, p.project_request_id)')))
            ->leftJoin('professional_profiles as pro', fn($j) => $j->on('pro.id', '=', DB::raw('COALESCE(b.professional_profile_id, p.professional_profile_id)')))
            ->when(!$management, fn($q) => $q->where(fn($w) => $w->where('r.user_id', $userId)->orWhere('pro.user_id', $userId)->orWhere('b.client_user_id', $userId)))
            ->when($id !== null, fn($q) => $q->where('v.id', $id))
            ->when($budgetId !== null, fn($q) => $q->where('v.budget_id', $budgetId))
            ->select('v.*', 'r.id as request_id', 'r.title as request_title', 'r.user_id as request_owner', 'pro.user_id as issuer_id', 'b.client_user_id')
            ->orderByDesc('v.id')->get()->filter(fn($v) => $management || $v->issuer_id === $userId || $v->status !== 'draft')
            ->map(function ($v) {
                $items = DB::table('budget_items')->where('budget_version_id', $v->id)->get();
                $subtotal = $items->sum(fn($i) => (int)round($i->quantity * $i->unit_price * 100));
                $total = (int)round($subtotal * (1 + $v->tax_rate / 100));
                $latestSent = DB::table('budget_versions')->where('budget_id', $v->budget_id)->whereNotNull('sent_at')->max('version_number');

                // Los documentos emitidos para contactos externos se comparten como PDF.
                $external = $v->request_id === null && $v->client_user_id === null;

                return [...((array)$v), 'external' => $external, 'client' => json_decode($v->client_snapshot ?? 'null', true), 'issuer' => json_decode($v->issuer_snapshot ?? 'null', true), 'items' => $items, 'subtotal' => $subtotal / 100, 'total' => $total / 100,
                    'current' => (int)$latestSent === (int)$v->version_number,
                    'display_status' => $v->status === 'sent' && $v->valid_until < today()->toDateString() ? 'expired' : ($external && $v->status === 'sent' ? 'issued' : $v->status)];
            })->values()->all();
    }
}
