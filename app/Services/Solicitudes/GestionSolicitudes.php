<?php

namespace App\Services\Solicitudes;

use App\Models\Organizacion;
use App\Models\User;
use App\Services\Notifications\NotificationCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionSolicitudes
{
    public function __construct(
        private RecorridoSolicitud $journey,
        private NotificationCenter $notifications,
    )
    {
    }

    // Recibe el usuario autenticado y los datos validados. Bloquea el borrador al editar y conserva la estimación de origen.
    public function save(int $userId, array $d, ?array $pending = null): int
    {
        return DB::transaction(function () use ($userId, $d, $pending) {
            // Los adicionales se guardan en JSON; el bloqueo evita vincular un servicio que se está eliminando.
            $adicionales = array_map('intval', array_column($d['additional_services'] ?? [], 'service_id'));
            if ($adicionales) {
                $existentes = DB::table('services')->whereIn('id', $adicionales)->orderBy('id')->lockForUpdate()->pluck('id')->all();
                if (array_diff($adicionales, $existentes)) {
                    throw ValidationException::withMessages(['additional_services' => 'Un servicio adicional ya no está disponible.']);
                }
            }
            // Una cuenta del equipo usa su identidad laboral y no entra en la cartera como cliente.
            $usuario = User::findOrFail($userId);
            abort_if($usuario->perteneceAEquipo(), 403);
            $existing = !empty($d['id']) ? DB::table('project_requests')->where('id', $d['id'])->lockForUpdate()->firstOrFail() : null;
            if ($existing) {
                abort_unless($existing->user_id === $userId && $existing->status === 'draft', 403);
            }
            // La categoría se deriva del catálogo. Un borrador antiguo conserva su servicio aunque se haya desactivado.
            $service = DB::table('services as s')->join('service_categories as c', 'c.id', '=', 's.service_category_id')
                ->where('s.id', $d['service_id'])->select('s.id', 's.name', 's.slug', 's.active', 'c.active as category_active', 'c.name as category')->firstOrFail();
            if ((!$existing || $existing->service_id !== (int)$d['service_id']) && (!$service->active || !$service->category_active)) {
                throw ValidationException::withMessages(['service_id' => 'Elegí un servicio disponible en el catálogo.']);
            }
            $d['category'] = $service->category;

            // Recupera también la estimación del borrador cuando el formulario omite su identificador.
            $estimateId = $d['estimate_id'] ?? $existing?->estimate_id;
            $estimate = $estimateId ? DB::table('estimates')->where('id', $estimateId)->where('user_id', $userId)->firstOrFail() : null;
            // Solo el borrador que ya estaba vinculado puede seguir usando una estimación eliminada.
            if ($estimate?->deleted_at && (!$existing || $existing->estimate_id !== $estimate->id)) {
                throw ValidationException::withMessages(['estimate_id' => 'Esta estimación fue eliminada. Prepará una nueva estimación.']);
            }
            if ($estimate && $estimate->service_id !== (int)$d['service_id']) {
                throw ValidationException::withMessages(['service_id' => 'El servicio debe coincidir con la estimación de origen.']);
            }
            $estimateSnapshot = $estimate ? [
                'price_min' => $estimate->price_min,
                'price_max' => $estimate->price_max,
                'base_min' => $estimate->base_min_snapshot,
                'base_max' => $estimate->base_max_snapshot,
                'currency' => $estimate->currency_snapshot,
                'items' => json_decode($estimate->items_snapshot ?? '[]', true),
            ] : (json_decode($existing?->estimate_snapshot ?? 'null', true));
            // Los servicios se identifican por registros del catálogo y sus nombres quedan en la instantánea del pedido.
            $additional = json_decode($existing?->requested_services ?? '[]', true);
            if (array_key_exists('additional_services', $d)) {
                $samePreparedService = (int)($pending['form']['service_id'] ?? 0) === (int)$d['service_id']
                    && (int)($pending['form']['id'] ?? 0) === (int)($d['id'] ?? 0);
                $additional = $this->journey->calculateAdditionalServices(
                    (int)$d['service_id'], $d['additional_services'], json_decode($estimate?->answers_snapshot ?? '[]', true),
                    $estimate?->currency_snapshot ?? 'ARS',
                    [...$additional, ...($samePreparedService ? ($pending['additional_services'] ?? []) : [])], allowIncomplete: !$d['publish'],
                );
            }
            // Todos los servicios comparten un contacto, copiado del perfil si el pedido todavía no tiene uno.
            $contact = $d['contact'] ?? json_decode($existing?->contact_snapshot ?? 'null', true)
                ?? $usuario->contactDetails();
            $values = ['user_id' => $userId, 'service_id' => $d['service_id'], 'estimate_id' => $estimate?->id ?? $existing?->estimate_id,
                'title' => $d['title'], 'description' => $d['description'], 'visibility' => $d['visibility'], 'category' => $d['category'] ?? null,
                'service_name_snapshot' => $service->name, 'service_slug_snapshot' => $service->slug,
                'estimate_snapshot' => $estimateSnapshot === null ? $existing?->estimate_snapshot : json_encode($estimateSnapshot),
                'contact_snapshot' => json_encode($contact), 'requested_services' => json_encode($additional),
                'skills' => json_encode($d['skills']), 'budget_reference' => $d['budget_reference'] ?? null,
                'desired_deadline' => $d['desired_deadline'] ?? null, 'status' => $d['publish'] ? 'open' : 'draft',
                'published_at' => $d['publish'] && $d['visibility'] === 'public' ? now() : null,
                'answers_snapshot' => $estimate?->answers_snapshot ?? $existing?->answers_snapshot, 'updated_at' => now()];
            if ($existing) {
                DB::table('project_requests')->where('id', $existing->id)->update($values);
                $id = $existing->id;
            } else {
                $id = DB::table('project_requests')->insertGetId([...$values, 'created_at' => now()]);
            }
            // Solo el envío definitivo avisa a gestión; guardar un borrador no publica su existencia.
            if ($d['publish']) {
                // La primera solicitud enviada incorpora al cliente a la cartera de synaux.
                DB::table('organizacion_clientes')->insertOrIgnore([
                    'organizacion_id' => Organizacion::principal()->id, 'user_id' => $userId,
                ]);
                $this->notifications->send('solicitud', $id, 'solicitud-enviada:' . $id,
                    'Nueva solicitud', $d['title'], management: true, actor: $userId);
            }

            return $id;
        });
    }
}
