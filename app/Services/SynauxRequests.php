<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SynauxRequests
{
    // Recibe el usuario autenticado y los datos validados. Bloquea el borrador al editar y conserva la estimación de origen.
    public function save(int $userId, array $d): int
    {
        return DB::transaction(function () use ($userId, $d) {
            $existing = ! empty($d['id']) ? DB::table('project_requests')->where('id', $d['id'])->lockForUpdate()->firstOrFail() : null;
            if ($existing) {
                abort_unless($existing->user_id === $userId && $existing->status === 'draft', 403);
            }
            // La categoría se deriva del catálogo. Un borrador antiguo conserva su servicio aunque se haya desactivado.
            $service = DB::table('services as s')->join('service_categories as c', 'c.id', '=', 's.service_category_id')
                ->where('s.id', $d['service_id'])->select('s.id', 's.active', 'c.active as category_active', 'c.name as category')->firstOrFail();
            if ((! $existing || $existing->service_id !== (int) $d['service_id']) && (! $service->active || ! $service->category_active)) {
                throw ValidationException::withMessages(['service_id' => 'Elegí un servicio disponible en el catálogo.']);
            }
            $d['category'] = $service->category;

            // Recupera también la estimación del borrador cuando el formulario omite su identificador.
            $estimateId = $d['estimate_id'] ?? $existing?->estimate_id;
            $estimate = $estimateId ? DB::table('estimates')->where('id', $estimateId)->where('user_id', $userId)->firstOrFail() : null;
            // Solo el borrador que ya estaba vinculado puede seguir usando una estimación eliminada.
            if ($estimate?->deleted_at && (! $existing || $existing->estimate_id !== $estimate->id)) {
                throw ValidationException::withMessages(['estimate_id' => 'Esta estimación fue eliminada. Prepará una nueva estimación.']);
            }
            if ($estimate && $estimate->service_id !== (int) $d['service_id']) {
                throw ValidationException::withMessages(['service_id' => 'El servicio debe coincidir con la estimación de origen.']);
            }
            // Los servicios se identifican por registros del catálogo y sus nombres quedan en la instantánea del pedido.
            $additional = json_decode($existing?->requested_services ?? '[]', true);
            if (array_key_exists('additional_services', $d)) {
                $additional = [];
                foreach ($d['additional_services'] as $index => $item) {
                    $extra = DB::table('services')->where('id', $item['service_id'])->where('active', 1)->first();
                    if (! $extra || $extra->id === (int) $d['service_id']) {
                        throw ValidationException::withMessages(["additional_services.$index.service_id" => 'Elegí otro servicio disponible.']);
                    }
                    $additional[] = [
                        'service_id' => $extra->id, 'name' => $extra->name,
                        'quantity' => $item['quantity'], 'notes' => $item['notes'] ?? '',
                    ];
                }
            }
            $owner = DB::table('users')->where('id', $userId)->firstOrFail();
            $contact = $d['contact'] ?? json_decode($existing?->contact_snapshot ?? 'null', true)
                ?? ['name' => $owner->name, 'email' => $owner->email, 'phone' => $owner->phone, 'company' => $owner->company, 'address' => $owner->address];
            $values = ['user_id' => $userId, 'service_id' => $d['service_id'], 'estimate_id' => $estimate?->id ?? $existing?->estimate_id,
                'title' => $d['title'], 'description' => $d['description'], 'visibility' => $d['visibility'], 'category' => $d['category'] ?? null,
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
                app(SynauxNotificationCenter::class)->send('solicitud', $id, 'solicitud-enviada:'.$id,
                    'Nueva solicitud', $d['title'], management: true, actor: $userId);
            }

            return $id;
        });
    }
}
