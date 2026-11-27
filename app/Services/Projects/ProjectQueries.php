<?php

namespace App\Services\Projects;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProjectQueries
{
    // Devuelve los proyectos asociados a versiones de presupuesto que la cuenta puede consultar.
    public function projects(array $quotes, ?int $id = null): array
    {
        $projects = DB::table('projects')->whereIn('accepted_budget_version_id', array_column($quotes, 'id'))
            ->when($id !== null, fn($query) => $query->where('id', $id))->orderByDesc('id')->get();
        // El historial se consulta solo para los proyectos autorizados y no expone datos de la cuenta del gestor.
        $updates = DB::table('project_updates')->whereIn('project_id', $projects->pluck('id'))
            ->orderByDesc('id')->get(['id', 'project_id', 'status', 'progress', 'start_date', 'end_date', 'notes', 'next_steps', 'created_at'])->groupBy('project_id');
        return $projects->map(fn($project) => [
            ...Arr::except((array)$project, ['supervisor_id', 'responsable_id']), 'updates' => ($updates[$project->id] ?? collect())->map(fn($update) => (array)$update)->all(),
        ])->all();
    }

    // Una asignación concede la lectura del proyecto, con una selección acotada de sus campos operativos.
    public function asignados(int $usuario, ?int $proyecto = null): array
    {
        return DB::table('projects')->where('responsable_id', $usuario)
            ->when($proyecto !== null, fn($consulta) => $consulta->where('id', $proyecto))
            ->orderByDesc('id')->get(['id', 'title', 'status', 'progress', 'start_date', 'end_date',
                'next_steps', 'notes', 'created_at', 'accepted_budget_version_id'])
            ->map(fn($registro) => (array)$registro)->all();
    }

    // Relaciona al cliente registrado con proyectos de solicitudes, propuestas o presupuestos directos.
    public function forClient(int $clientId): LengthAwarePaginator
    {
        return DB::table('projects as project')
            ->join('budget_versions as version', 'version.id', '=', 'project.accepted_budget_version_id')
            ->join('budgets as budget', 'budget.id', '=', 'version.budget_id')
            ->leftJoin('proposals as proposal', 'proposal.id', '=', 'budget.proposal_id')
            ->leftJoin('project_requests as request', fn($join) => $join
                ->on('request.id', '=', DB::raw('COALESCE(budget.project_request_id, proposal.project_request_id)')))
            ->where(fn($query) => $query->where('budget.client_user_id', $clientId)->orWhere('request.user_id', $clientId))
            ->orderByDesc('project.id')
            ->paginate(15, ['project.id', 'project.title', 'project.status', 'project.progress']);
    }
}
