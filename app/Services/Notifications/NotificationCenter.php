<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationCenter
{
    // Recibe eventos internos ya autorizados; los destinos se construyen aquí y nunca desde un formulario.
    public function send(string $type, int $id, string $event, string $title, string $message, array $recipients = [], bool $management = false, ?int $actor = null): void
    {
        $href = match ($type) {
            'solicitud' => '/solicitudes/'.$id,
            'presupuesto' => '/presupuestos/'.$id,
            'proyecto' => '/proyectos/'.$id,
            'consulta' => '/gestion/contactos?consulta='.$id.'#consulta-'.$id,
        };
        $direct = collect($recipients)->filter()->map(fn ($user) => (int) $user)->unique();
        $admins = $management ? DB::table('users')->where('is_admin', true)->pluck('id') : collect();
        foreach ($direct->merge($admins)->unique() as $userId) {
            // Normaliza los ids para que la exclusión del autor funcione también con otros controladores PDO.
            $userId = (int) $userId;
            if ($userId === $actor) continue;
            DB::table('synaux_notifications')->insertOrIgnore([
                'user_id' => $userId, 'event_key' => $event, 'entity_type' => $type, 'entity_id' => $id,
                'title' => $title, 'message' => mb_substr($message, 0, 255), 'href' => $href,
                'requires_admin' => ! $direct->contains($userId), 'created_at' => now(),
            ]);
        }
    }

    // La bandeja se limita a la cuenta actual y retira avisos de gestión si perdió ese permiso.
    public function query(User $user): Builder
    {
        return DB::table('synaux_notifications')->where('user_id', $user->id)
            ->when(! $user->is_admin, fn ($query) => $query->where('requires_admin', false));
    }

    // La lista reciente está acotada; los contadores e identificadores incluyen todos los avisos sin leer.
    public function data(?User $user): array
    {
        $empty = ['unread_count' => 0, 'items' => [], 'unread_entities' => []];
        if (! $user || ! Schema::hasTable('synaux_notifications')) return $empty;
        $unread = $this->query($user)->whereNull('read_at')->get(['entity_type', 'entity_id']);

        return [
            'unread_count' => $unread->count(),
            'items' => $this->query($user)->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('id')->limit(30)->get(['id', 'title', 'message', 'href', 'entity_type', 'entity_id', 'read_at', 'created_at']),
            'unread_entities' => $unread->groupBy('entity_type')->map(fn ($rows) => $rows->pluck('entity_id')->unique()->values())->all(),
        ];
    }

    // Abrir un detalle autorizado marca solo sus avisos en esta cuenta, sin afectar a otros destinatarios.
    public function readEntity(User $user, string $type, int $id): void
    {
        $this->query($user)->where('entity_type', $type)->where('entity_id', $id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    // Resuelve cliente y emisor de un proyecto tanto con solicitud directa como con propuesta elegida.
    public function projectRecipients(int $projectId): array
    {
        $parties = DB::table('projects as project')->join('budget_versions as v', 'v.id', '=', 'project.accepted_budget_version_id')
            ->join('budgets as b', 'b.id', '=', 'v.budget_id')->leftJoin('proposals as p', 'p.id', '=', 'b.proposal_id')
            ->leftJoin('project_requests as r', fn ($join) => $join->on('r.id', '=', DB::raw('COALESCE(b.project_request_id, p.project_request_id)')))
            ->leftJoin('professional_profiles as pro', fn ($join) => $join->on('pro.id', '=', DB::raw('COALESCE(b.professional_profile_id, p.professional_profile_id)')))
            ->where('project.id', $projectId)->selectRaw('COALESCE(r.user_id, b.client_user_id) as client_id, pro.user_id as issuer_id')->first();

        return [$parties?->client_id, $parties?->issuer_id];
    }
}
