<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationCenter;
use App\Services\Presupuestos\ConsultasPresupuestos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PagoProyectoController extends Controller
{
    public function __construct(private ConsultasPresupuestos $presupuestos, private NotificationCenter $avisos)
    {
    }

    // El titular informa el pago. Un comprobante en revisión debe resolverse antes de reemplazarlo.
    public function presentar(Request $request, int $proyecto, int $pago)
    {
        $presupuesto = $this->presupuesto($request, $proyecto);
        abort_unless($this->esCliente($request, $presupuesto), 403);
        $request->validate(['comprobante' => 'required|file|mimes:pdf,png,jpg,jpeg|max:5120']);
        $this->consultaPago($proyecto, $pago)->firstOrFail();
        $nuevo = $request->file('comprobante')->store('comprobantes-proyectos', 'local');
        if (!$nuevo) {
            throw ValidationException::withMessages(['comprobante' => 'No pudimos guardar el comprobante. Volvé a intentarlo.']);
        }

        try {
            $anterior = DB::transaction(function () use ($request, $proyecto, $pago, $nuevo) {
                $consulta = $this->consultaPago($proyecto, $pago);
                $actual = $consulta->lockForUpdate()->firstOrFail();
                if (!in_array($actual->estado, ['pendiente', 'observado'], true)) {
                    throw ValidationException::withMessages(['comprobante' => 'El comprobante ya está en revisión o el pago fue confirmado.']);
                }

                $intento = $actual->intento + 1;
                $consulta->update([
                    'comprobante' => $nuevo, 'estado' => 'en_revision', 'intento' => $intento,
                    'presentado_at' => now(), 'observacion' => null, 'revisado_at' => null, 'revisado_por' => null,
                ]);
                $this->avisos->send('proyecto', $proyecto, "comprobante:$pago:$intento",
                    'Comprobante de pago recibido', 'Hay un comprobante de transferencia para revisar.', management: true,
                    actor: $request->user()->id);

                return $actual->comprobante;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($nuevo);
            throw $error;
        }
        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }

        return back()->with('notice', 'Comprobante enviado a revisión.');
    }

    // Gestión confirma la acreditación o devuelve una observación sobre el intento que tiene abierto.
    public function revisar(Request $request, int $proyecto, int $pago)
    {
        abort_unless($request->user()->puedeGestionarSynaux(), 403);
        $presupuesto = $this->presupuesto($request, $proyecto);
        $datos = $request->validate([
            'decision' => 'required|in:confirmado,observado', 'intento' => 'required|integer|min:1',
            'observacion' => 'required_if:decision,observado|nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $proyecto, $pago, $presupuesto, $datos) {
            $consulta = $this->consultaPago($proyecto, $pago);
            $actual = $consulta->lockForUpdate()->firstOrFail();
            if ($actual->estado !== 'en_revision' || (int)$actual->intento !== (int)$datos['intento']) {
                throw ValidationException::withMessages(['decision' => 'El comprobante cambió. Actualizá la página antes de revisarlo.']);
            }

            $confirmado = $datos['decision'] === 'confirmado';
            $consulta->update([
                'estado' => $datos['decision'], 'observacion' => $confirmado ? null : $datos['observacion'],
                'revisado_por' => $request->user()->id, 'revisado_at' => now(),
            ]);
            $this->avisos->send('proyecto', $proyecto, "comprobante-revisado:$pago:$actual->intento",
                $confirmado ? 'Pago confirmado' : 'Comprobante observado',
                $confirmado ? 'Gestión confirmó la acreditación de tu transferencia.' : 'Revisá la observación en el proyecto.',
                [$presupuesto['request_owner'] ?? $presupuesto['client_user_id']], actor: $request->user()->id);
        });

        return back()->with('notice', $datos['decision'] === 'confirmado' ? 'Pago confirmado.' : 'Observación enviada.');
    }

    // El archivo queda fuera de la carpeta pública y se descarga solo después de comprobar el acceso.
    public function descargar(Request $request, int $proyecto, int $pago)
    {
        $presupuesto = $this->presupuesto($request, $proyecto);
        abort_unless($request->user()->puedeGestionarSynaux() || $this->esCliente($request, $presupuesto), 403);
        $ruta = $this->consultaPago($proyecto, $pago)->firstOrFail()->comprobante;
        $disco = Storage::disk('local');
        abort_unless($ruta && $disco->exists($ruta), 404);

        return $disco->download($ruta, 'comprobante-' . $pago . '.' . pathinfo($ruta, PATHINFO_EXTENSION), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    // Reutiliza la autorización del presupuesto aceptado y verifica que el pago pertenezca al proyecto de la URL.
    private function presupuesto(Request $request, int $proyecto): array
    {
        $usuario = $request->user();
        $version = DB::table('projects')->where('id', $proyecto)->value('accepted_budget_version_id') ?? abort(404);

        return $this->presupuestos->quotes($usuario->id, $usuario->puedeGestionarSynaux(), id: $version)[0] ?? abort(404);
    }

    private function esCliente(Request $request, array $presupuesto): bool
    {
        return (int)($presupuesto['request_owner'] ?? $presupuesto['client_user_id']) === (int)$request->user()->id;
    }

    private function consultaPago(int $proyecto, int $pago)
    {
        return DB::table('proyecto_pagos')->where('project_id', $proyecto)->where('id', $pago);
    }
}
