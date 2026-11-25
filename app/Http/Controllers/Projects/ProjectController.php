<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationCenter;
use App\Services\Presupuestos\ConsultasPresupuestos;
use App\Services\Projects\ProjectQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectController extends Controller
{
    // Comprueba el presupuesto del proyecto antes de cargar su historial y marcar sus avisos.
    public function show(Request $request, ConsultasPresupuestos $budgetQueries, ProjectQueries $projectQueries, NotificationCenter $notifications, int $id)
    {
        $user = $request->user();
        $versionId = DB::table('projects')->where('id', $id)->value('accepted_budget_version_id') ?? abort(404);
        $gestion = $user->puedeGestionarSynaux();
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
        $notifications->readEntity($user, 'proyecto', $id);

        return Inertia::render('EspacioTrabajo', [
            'screen' => 'proyecto',
            'area' => $gestion ? 'gestion' : 'cliente',
            'selected' => $selected,
            'pagos' => $pagos,
            'puedePresentarComprobante' => $esTitular,
            'puedeRevisarComprobante' => $gestion,
            'quotes' => $quotes,
            'historialPresupuestos' => $historialPresupuestos,
        ]);
    }

    // Cada avance se valida y conserva en una transacción; el cliente solo puede consultarlo.
    public function update(Request $request, ConsultasPresupuestos $budgetQueries, NotificationCenter $notifications)
    {
        $d = $request->validate([
            'id' => 'required|integer', 'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'progress' => 'required|integer|min:0|max:100',
            'start_date' => 'nullable|required_with:end_date|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:10000', 'next_steps' => 'nullable|string|max:5000',
        ]);
        DB::transaction(function () use ($request, $d, $budgetQueries, $notifications) {
            $project = DB::table('projects')->where('id', $d['id'])->lockForUpdate()->firstOrFail();
            $version = $budgetQueries->quotes($request->user()->id, $request->user()->puedeGestionarSynaux(), id: $project->accepted_budget_version_id)[0] ?? null;
            abort_unless($version && ($request->user()->puedeGestionarSynaux() || $version['issuer_id'] === $request->user()->id), 403);
            // Estado y porcentaje deben comunicar el mismo avance.
            if (($d['status'] === 'completed' && (int)$d['progress'] !== 100)
                || ($d['status'] === 'pending' && (int)$d['progress'] !== 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'progress' => 'Usá 0% para pendiente y 100% para un proyecto completado.',
                ]);
            }
            $values = [
                'start_date' => $project->start_date, 'end_date' => $project->end_date,
                'notes' => $project->notes, 'next_steps' => $project->next_steps,
                ...collect($d)->except('id')->all(),
            ];
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
