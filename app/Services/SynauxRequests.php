<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

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
            $estimate = ! empty($d['estimate_id']) ? DB::table('estimates')->where('id', $d['estimate_id'])->where('user_id', $userId)->firstOrFail() : null;
            abort_if($estimate && $estimate->service_id !== (int) $d['service_id'], 422, 'El servicio debe coincidir con la estimación de origen.');
            $values = ['user_id' => $userId, 'service_id' => $d['service_id'], 'estimate_id' => $estimate?->id ?? $existing?->estimate_id,
                'title' => $d['title'], 'description' => $d['description'], 'visibility' => $d['visibility'], 'category' => $d['category'] ?? null,
                'skills' => json_encode($d['skills']), 'budget_reference' => $d['budget_reference'] ?? null,
                'desired_deadline' => $d['desired_deadline'] ?? null, 'status' => $d['publish'] ? 'open' : 'draft',
                'published_at' => $d['publish'] && $d['visibility'] === 'public' ? now() : null,
                'answers_snapshot' => $estimate?->answers_snapshot ?? $existing?->answers_snapshot, 'updated_at' => now()];
            if ($existing) {
                DB::table('project_requests')->where('id', $existing->id)->update($values);

                return $existing->id;
            }

            return DB::table('project_requests')->insertGetId([...$values, 'created_at' => now()]);
        });
    }
}
