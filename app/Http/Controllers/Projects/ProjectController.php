<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationCenter;
use App\Services\Presupuestos\ConsultasPresupuestos;
use App\Services\Projects\AsignacionesProyecto;
use App\Services\Projects\ProjectQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectController extends Controller
{
    // Comprueba el presupuesto del proyecto antes de cargar su historial y marcar sus avisos.
    public function show(Request $request, ConsultasPresupuestos $budgetQueries, ProjectQueries $projectQueries, NotificationCenter $notifications, AsignacionesProyecto $asignaciones, int $id)
    {
        $user = $request->user();
        $gestion = $user->puedeGestionarSynaux();
        if (!$gestion && $user->esProfesionalInterno()) {
            // Un responsable interno recibe el alcance, sin cargar importes, pagos ni datos del cliente.
            $proyecto = $projectQueries->asignados($user->id, $id)[0] ?? abort(404);
            $version = $proyecto['accepted_budget_version_id'];
            $notifications->readEntity($user, 'proyecto', $id);

            return Inertia::render('EspacioTrabajo', [
                'screen' => 'proyecto', 'area' => 'profesional', 'selected' => $proyecto,
                'tareas' => DB::table('project_tasks as tarea')->leftJoin('users as alta', 'alta.id', '=', 'tarea.created_by')
                    ->leftJoin('users as cierre', 'cierre.id', '=', 'tarea.completed_by')->where('tarea.project_id', $id)->orderBy('tarea.id')
                    ->get(['tarea.id', 'tarea.title', 'tarea.etapa', 'tarea.completed', 'tarea.completed_at', 'alta.name as agregada_por', 'cierre.name as completada_por']),
                'alcanceAsignado' => [
                    'entrega' => DB::table('budget_versions')->where('id', $version)->value('estimated_delivery_days'),
                    'entregables' => DB::table('budget_items')->where('budget_version_id', $version)
                        ->get(['description', 'quantity']),
                ],
            ]);
        }

        $proyecto = DB::table('projects')->where('id', $id)->firstOrFail();
        $versionId = $proyecto->accepted_budget_version_id;
        $quotes = $budgetQueries->quotes($user->id, $gestion, id: $versionId);
        $selected = $projectQueries->projects($quotes, id: $id)[0] ?? abort(404);
        // La vista informa si hay un archivo y conserva su ubicación privada en el servidor.
        $presupuesto = $quotes[0];
        $esTitular = (int)($presupuesto['request_owner'] ?? $presupuesto['client_user_id']) === (int)$user->id;
        $pagos = $gestion || $esTitular ? DB::table('proyecto_pagos')->where('project_id', $id)->orderBy('id')
            ->get(['id', 'concepto', 'importe_centavos', 'estado', 'intento', 'observacion', 'comprobante', 'revisado_at'])
            ->map(fn($pago) => [
                'id' => $pago->id, 'concepto' => $pago->concepto, 'importe_centavos' => $pago->importe_centavos,
                'estado' => $pago->estado, 'intento' => $pago->intento, 'observacion' => $pago->observacion,
                'tiene_comprobante' => filled($pago->comprobante), 'revisado_at' => $pago->revisado_at,
            ]) : [];
        $historialPresupuestos = [];
        if ($gestion || $esTitular) {
            // Gestión conserva también los borradores previos. El titular consulta las versiones enviadas.
            $versiones = $presupuesto['request_id'] !== null
                ? $budgetQueries->quotes($user->id, $gestion, requestId: (int)$presupuesto['request_id'])
                : $budgetQueries->quotes($user->id, $gestion, budgetId: (int)$presupuesto['budget_id']);
            foreach ($versiones as $version) {
                if ((int)$version['id'] === (int)$versionId || (!$gestion && $version['status'] === 'draft')) continue;

                $historialPresupuestos[] = Arr::only($version, [
                    'id', 'budget_id', 'version_number', 'status', 'display_status', 'sent_at', 'created_at', 'total', 'currency_code',
                ]);
            }
        }
        $asignacion = null;
        if ($gestion && ($interno = $asignaciones->presupuestoInterno($versionId))) {
            // El seguimiento conserva a quien emitió el presupuesto, incluso en proyectos anteriores.
            $supervisor = $proyecto->supervisor_id ?? $asignaciones->supervisor($interno);
            $editable = in_array($proyecto->status, ['pending', 'in_progress'], true);
            $asignacion = [
                'editable' => $editable,
                'supervisor' => $supervisor ? DB::table('users')->where('id', $supervisor)->value('name') : null,
                'responsable' => $proyecto->responsable_id
                    ? DB::table('users')->where('id', $proyecto->responsable_id)->first(['id', 'name']) : null,
                'profesionales' => $editable ? $asignaciones->profesionales()->orderBy('users.name')
                    ->get(['users.id', 'users.name'])->map(fn($persona) => $persona->only('id', 'name')) : [],
            ];
        }
        $notifications->readEntity($user, 'proyecto', $id);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'proyecto',
            'area' => $gestion ? 'gestion' : 'cliente',
            'selected' => $selected,
            'tareas' => DB::table('project_tasks as tarea')->leftJoin('users as alta', 'alta.id', '=', 'tarea.created_by')
                ->leftJoin('users as cierre', 'cierre.id', '=', 'tarea.completed_by')->where('tarea.project_id', $id)->orderBy('tarea.id')
                ->get(['tarea.id', 'tarea.title', 'tarea.etapa', 'tarea.completed', 'tarea.completed_at', 'alta.name as agregada_por', 'cierre.name as completada_por']),
            'pagos' => $pagos,
            'puedePresentarComprobante' => $esTitular,
            'puedeRevisarComprobante' => $gestion,
            'quotes' => $quotes,
            'historialPresupuestos' => $historialPresupuestos,
            'asignacion' => $asignacion,
        ]);
    }

    // Solo Gestión puede elegir al responsable de ejecución.
    public function asignar(Request $request, AsignacionesProyecto $asignaciones, int $id)
    {
        abort_unless($request->user()->puedeGestionarSynaux(), 403);
        $datos = $request->validate(['responsable_id' => 'required|integer|min:1']);
        $asignaciones->guardar($request->user(), $id, (int)$datos['responsable_id']);

        return back()->with('notice', 'Responsable del proyecto actualizado.');
    }

    // El responsable y Gestión mantienen el checklist agrupado; al cambiar su total se recalcula el avance.
    public function agregarTarea(Request $request, NotificationCenter $notifications, int $id)
    {
        $datos = $request->validate([
            'title' => 'required|string|max:180',
            'etapa' => ['required', 'string', Rule::in(['Planificación', 'Diseño', 'Desarrollo', 'Revisión', 'Entrega', 'Ejecución', 'Otros'])],
        ]);
        DB::transaction(function () use ($request, $notifications, $id, $datos) {
            $proyecto = DB::table('projects')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->autorizarEdicion($request, $proyecto);
            abort_unless(in_array($proyecto->status, ['pending', 'in_progress'], true), 409);
            DB::table('project_tasks')->insert([
                'project_id' => $id, 'created_by' => $request->user()->id, 'title' => $datos['title'],
                'etapa' => $datos['etapa'], 'completed' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->sincronizarAvanceChecklist($proyecto, $request, $notifications);
        });
        return back()->with('notice', 'Tarea agregada al proyecto.');
    }

    // Cada marca conserva quién la hizo y sincroniza el porcentaje, el historial y los avisos del proyecto.
    public function completarTarea(Request $request, NotificationCenter $notifications, int $id, int $tarea)
    {
        $completada = false;
        DB::transaction(function () use ($request, $notifications, $id, $tarea, &$completada) {
            $proyecto = DB::table('projects')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->autorizarEdicion($request, $proyecto);
            abort_unless($proyecto->status === 'in_progress', 409);
            $registro = DB::table('project_tasks')->where('project_id', $id)->where('id', $tarea)->lockForUpdate()->firstOrFail();
            $completada = !$registro->completed;
            DB::table('project_tasks')->where('id', $tarea)->update([
                'completed' => $completada, 'completed_by' => $completada ? $request->user()->id : null,
                'completed_at' => $completada ? now() : null, 'updated_at' => now(),
            ]);
            $this->sincronizarAvanceChecklist($proyecto, $request, $notifications);
        });
        return back()->with('notice', $completada ? 'Tarea completada.' : 'Tarea reabierta.');
    }

    // Si hay checklist activo, su proporción completada es la fuente del avance visible del proyecto.
    private function sincronizarAvanceChecklist(object $proyecto, Request $request, NotificationCenter $notifications): void
    {
        $tareas = DB::table('project_tasks')->where('project_id', $proyecto->id);
        $total = (clone $tareas)->count();
        if ($total === 0) return;

        $completadas = (clone $tareas)->where('completed', true)->count();
        $avance = match ($proyecto->status) {
            'in_progress' => (int)round($completadas * 100 / $total),
            'completed' => 100,
            'cancelled' => (int)$proyecto->progress,
            default => 0,
        };
        if ((int)$proyecto->progress === $avance) return;

        DB::table('projects')->where('id', $proyecto->id)->update(['progress' => $avance, 'updated_at' => now()]);
        $updateId = DB::table('project_updates')->insertGetId([
            'project_id' => $proyecto->id, 'user_id' => $request->user()->id, 'status' => $proyecto->status,
            'progress' => $avance, 'start_date' => $proyecto->start_date, 'end_date' => $proyecto->end_date,
            'notes' => null, 'next_steps' => $proyecto->next_steps, 'created_at' => now(),
        ]);
        $notifications->send('proyecto', $proyecto->id, 'proyecto-actualizado:' . $updateId,
            'Avance del proyecto actualizado', $proyecto->title,
            $notifications->projectRecipients($proyecto->id), management: true, actor: $request->user()->id);
    }

    // La misma regla autoriza a Gestión y al profesional responsable vigente.
    private function autorizarEdicion(Request $request, object $proyecto): void
    {
        abort_unless($request->user()->puedeGestionarSynaux()
            || ($request->user()->esProfesionalInterno() && (int)$proyecto->responsable_id === (int)$request->user()->id), 403);
    }

    // Cada avance se valida y conserva en una transacción; el cliente solo puede consultarlo.
    public function update(Request $request, ConsultasPresupuestos $budgetQueries, NotificationCenter $notifications)
    {
        abort_if($request->user()->esProfesionalInterno() && !$request->user()->puedeGestionarSynaux(), 403);
        $d = $request->validate([
            'id' => 'required|integer', 'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'progress' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|required_with:end_date|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:10000', 'next_steps' => 'nullable|string|max:5000',
        ]);
        DB::transaction(function () use ($request, $d, $budgetQueries, $notifications) {
            $project = DB::table('projects')->where('id', $d['id'])->lockForUpdate()->firstOrFail();
            $gestion = $request->user()->puedeGestionarSynaux();
            $responsable = $request->user()->esProfesionalInterno() && (int)$project->responsable_id === (int)$request->user()->id;
            $version = $gestion || $responsable
                ? ['issuer_id' => null]
                : ($budgetQueries->quotes($request->user()->id, false, id: $project->accepted_budget_version_id)[0] ?? null);
            abort_unless($version && ($gestion || $responsable || $version['issuer_id'] === $request->user()->id), 403);
            // Con tareas, el avance sale del checklist; el campo manual sigue para proyectos anteriores sin tareas.
            $totalTareas = DB::table('project_tasks')->where('project_id', $project->id)->count();
            $tareasCompletadas = DB::table('project_tasks')->where('project_id', $project->id)->where('completed', true)->count();
            if ($totalTareas > 0 && $d['status'] === 'completed' && $tareasCompletadas < $totalTareas) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Completá todas las tareas del checklist antes de marcar el proyecto como completado.',
                ]);
            }
            if ($totalTareas === 0 && (($d['status'] === 'completed' && (int)($d['progress'] ?? 0) !== 100)
                || ($d['status'] === 'pending' && (int)($d['progress'] ?? 0) !== 0))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'progress' => 'Usá 0% para pendiente y 100% para un proyecto completado.',
                ]);
            }
            if ($d['status'] === 'in_progress' && $project->status !== 'in_progress') {
                $senaPendiente = DB::table('proyecto_pagos')->where('project_id', $project->id)
                    ->where('concepto', 'sena')->where('estado', '!=', 'confirmado')->exists();
                if ($senaPendiente) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' => 'Confirmá el pago del anticipo antes de iniciar el proyecto.',
                    ]);
                }
            }
            $avance = $totalTareas > 0
                ? match ($d['status']) {
                    'in_progress' => (int)round($tareasCompletadas * 100 / $totalTareas),
                    'completed' => 100,
                    'cancelled' => (int)$project->progress,
                    default => 0,
                }
                : (int)($d['progress'] ?? $project->progress);
            $values = [
                'start_date' => $project->start_date, 'end_date' => $project->end_date,
                'notes' => $project->notes, 'next_steps' => $project->next_steps,
                ...collect($d)->except('id')->all(),
            ];
            $values['progress'] = $avance;
            // Reenviar los mismos datos no duplica el historial.
            $changed = collect($values)->contains(fn($value, $key) => (string)$value !== (string)$project->$key);
            if ($changed) {
                DB::table('projects')->where('id', $project->id)->update([...$values, 'updated_at' => now()]);
                $updateId = DB::table('project_updates')->insertGetId([
                    ...$values, 'project_id' => $project->id, 'user_id' => $request->user()->id, 'created_at' => now(),
                ]);
                // Un cambio real genera un aviso a las otras partes; reenviar los mismos datos no lo duplica.
                $notifications->send('proyecto', $project->id, 'proyecto-actualizado:' . $updateId, 'Proyecto actualizado', $project->title,
                    $notifications->projectRecipients($project->id), management: true, actor: $request->user()->id);
            }
        });

        return back()->with('notice', 'Avance del proyecto actualizado.');
    }
}
