<?php

namespace App\Services\Projects;

use Illuminate\Support\Facades\DB;

class ProjectQueries
{
    // Devuelve los proyectos asociados a versiones de presupuesto que la cuenta puede consultar.
    public function projects(array $quotes, ?int $id = null): array
    {
        $projects = DB::table('projects')->whereIn('accepted_budget_version_id', array_column($quotes, 'id'))
            ->when($id !== null, fn ($query) => $query->where('id', $id))->orderByDesc('id')->get();
        // El historial se consulta solo para los proyectos autorizados y no expone datos de la cuenta del gestor.
        $updates = DB::table('project_updates')->whereIn('project_id', $projects->pluck('id'))
            ->orderByDesc('id')->get(['id', 'project_id', 'status', 'progress', 'start_date', 'end_date', 'notes', 'next_steps', 'created_at'])->groupBy('project_id');
        return $projects->map(fn ($project) => [
            ...((array) $project), 'updates' => ($updates[$project->id] ?? collect())->map(fn ($update) => (array) $update)->all(),
        ])->all();
    }
}
