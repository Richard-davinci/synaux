<?php

namespace App\Services\Projects;

use App\Models\Organizacion;
use App\Models\User;
use App\Services\Notifications\NotificationCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignacionesProyecto
{
    public function __construct(private NotificationCenter $avisos)
    {
    }

    // Solo los proyectos emitidos por Gestión pertenecen al recorrido interno de asignación.
    public function presupuestoInterno(int $version): ?object
    {
        return DB::table('budget_versions as v')->join('budgets as b', 'b.id', '=', 'v.budget_id')
            ->where('v.id', $version)->whereNull('b.proposal_id')->first(['b.professional_profile_id']);
    }

    // El formulario lista profesionales activos del equipo actual, sin exponer cuentas de otras organizaciones.
    public function profesionales()
    {
        return Organizacion::principal()->miembros()->wherePivot('rol', 'profesional')
            ->where('users.activacion_pendiente', false);
    }

    // Los proyectos históricos pueden recuperar como supervisor al emisor de su presupuesto.
    public function supervisor(?object $presupuesto): ?int
    {
        return $presupuesto?->professional_profile_id
            ? DB::table('professional_profiles')->where('id', $presupuesto->professional_profile_id)->value('user_id')
            : null;
    }

    // El bloqueo del proyecto serializa reasignaciones y el aviso se envía una vez por cambio.
    public function guardar(User $actor, int $proyectoId, int $responsableId): void
    {
        abort_unless($actor->puedeGestionarSynaux(), 403);

        DB::transaction(function () use ($actor, $proyectoId, $responsableId) {
            $proyecto = DB::table('projects')->where('id', $proyectoId)->lockForUpdate()->firstOrFail();
            $presupuesto = $this->presupuestoInterno($proyecto->accepted_budget_version_id);
            abort_unless($presupuesto, 404);

            if (!in_array($proyecto->status, ['pending', 'in_progress'], true)) {
                throw ValidationException::withMessages(['responsable_id' => 'El proyecto ya está cerrado.']);
            }
            if ($responsableId !== (int)$actor->id
                && !$this->profesionales()->where('users.id', $responsableId)->lockForUpdate()->first(['users.id'])) {
                throw ValidationException::withMessages(['responsable_id' => 'Elegí a un profesional interno con cuenta activa.']);
            }
            if ((int)$proyecto->responsable_id === $responsableId) return;

            DB::table('projects')->where('id', $proyectoId)->update([
                'supervisor_id' => $proyecto->supervisor_id ?? $this->supervisor($presupuesto) ?? $actor->id,
                'responsable_id' => $responsableId, 'updated_at' => now(),
            ]);
            $cambio = DB::table('proyecto_asignaciones')->insertGetId([
                'project_id' => $proyectoId, 'responsable_id' => $responsableId, 'asignado_por' => $actor->id, 'created_at' => now(),
            ]);
            $this->avisos->send('proyecto', $proyectoId, 'proyecto-asignado:' . $cambio,
                'Proyecto asignado', $proyecto->title, [$responsableId], actor: $actor->id);
        }, 3);
    }
}
