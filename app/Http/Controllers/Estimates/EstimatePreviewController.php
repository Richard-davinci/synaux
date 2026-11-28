<?php

namespace App\Http\Controllers\Estimates;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Estimates\RevisionPrevisualizacion;
use App\Services\Solicitudes\RecorridoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cliente público temporal: calcula contra catálogo y tarifa vigentes sin escribir en sesión ni en tablas. */
final class EstimatePreviewController extends Controller
{
    public function __invoke(Request $request, ServiceCatalog $catalog, RecorridoSolicitud $requests)
    {
        abort_if($request->user()?->esCuentaInterna(), 403);

        $data = $request->validate([
            'service' => ['required', 'string', 'max:120'],
            'answers' => ['present', 'array', 'max:100'],
            'additional_services' => ['sometimes', 'array', 'max:20'],
            'additional_services.*.service_id' => ['required', 'integer', 'distinct'],
            'additional_services.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'additional_services.*.answers' => ['sometimes', 'array', 'max:100'],
        ]);

        // El catálogo público excluye servicios inactivos y servicios de categorías desactivadas.
        $service = $catalog->service($data['service']);
        $questions = $catalog->questions($service['database_id']);
        $this->rejectUnknownQuestionIds($data['answers'], $questions, 'answers');
        $main = $catalog->calculate($service['id'], $data['answers'], $questions, true);
        $questions = $catalog->visibleQuestions($questions, $data['answers']);
        $missing = $this->missingRequired($questions, $data['answers']);
        $incomplete = $main['incomplete'] || $missing !== [];
        $mainItemsMissingPrice = collect($main['items'])->contains(fn($item) => $item['min'] === null || $item['max'] === null);
        $mainTariffMissing = $service['estimation_mode'] === 'automatic' && $main['tariff_id'] === null;
        $mainBaseMissing = $service['estimation_mode'] === 'automatic'
            && $main['tariff_id'] !== null && $main['base_min'] === null;
        $mainComplete = !$incomplete && !$mainItemsMissingPrice && !$mainTariffMissing && !$mainBaseMissing
            && $service['estimation_mode'] === 'automatic' && $main['min'] !== null;

        $extrasInput = $data['additional_services'] ?? [];
        $published = collect($catalog->services())->keyBy('database_id');
        foreach ($extrasInput as $index => $extra) {
            $extraService = $published->get((int)$extra['service_id']);
            if (!$extraService || (int)$extraService['database_id'] === (int)$service['database_id']) {
                throw ValidationException::withMessages([
                    "additional_services.$index.service_id" => 'Elegí otro servicio publicado y disponible.',
                ]);
            }
            $scopedQuestions = $catalog->questions((int)$extraService['database_id']);
            $this->rejectUnknownQuestionIds($extra['answers'] ?? [], $scopedQuestions, "additional_services.$index.answers");
        }

        // La lógica de herencia, segmentación, unidades y cantidades es la misma que al preparar una solicitud.
        $extras = $requests->calculateAdditionalServices(
            (int)$service['database_id'], $extrasInput, $main['answers'], $main['currency'], [], true,
        );

        $currency = $main['currency'];
        $extraRows = [];
        $extraKnownMin = 0.0;
        $extraKnownMax = 0.0;
        $extraHasKnown = false;
        $extraIncomplete = false;
        foreach ($extras as $extra) {
            $breakdown = $extra['pricing_breakdown'] ?? [];
            $reference = $extra['pricing_reference'] ?? $extra['pricing_partial'] ?? null;
            $quantity = (int)$extra['quantity'];
            $knownMin = $breakdown['subtotal_total_min'] ?? null;
            $knownMax = $breakdown['subtotal_total_max'] ?? null;
            if ($knownMin !== null && $knownMax !== null && ($reference['currency'] ?? $breakdown['currency'] ?? null) === $currency) {
                $extraKnownMin += (float)$knownMin;
                $extraKnownMax += (float)$knownMax;
                $extraHasKnown = true;
            }
            $pending = !$extra['pricing_reference'] || ($breakdown['incomplete'] ?? false);
            $extraIncomplete = $extraIncomplete || $pending;
            $extraRows[] = [
                'service_id' => $extra['service_id'], 'name' => $extra['name'], 'quantity' => $quantity,
                'unit_minimo' => $reference['min'] ?? null, 'unit_maximo' => $reference['max'] ?? null,
                'subtotal_minimo' => $knownMin, 'subtotal_maximo' => $knownMax,
                'moneda' => $reference['currency'] ?? $breakdown['currency'] ?? $currency,
                'conceptos' => $breakdown['items'] ?? [], 'estado' => $pending ? 'revision_profesional' : 'listo',
            ];
        }

        $mainKnown = $main['subtotal_min'] !== null;
        $extrasKnown = $extraHasKnown;
        $knownMin = !$mainKnown && !$extrasKnown ? null : round((float)($main['subtotal_min'] ?? 0) + $extraKnownMin, 2);
        $knownMax = !$mainKnown && !$extrasKnown ? null : round((float)($main['subtotal_max'] ?? 0) + $extraKnownMax, 2);
        $itemsMissingPrice = $mainItemsMissingPrice;
        $configurationErrors = [];
        if ($mainTariffMissing) $configurationErrors[] = 'tarifario_no_disponible';

        $states = [];
        if ($configurationErrors) $states[] = 'error_configuracion';
        if ($mainBaseMissing) $states[] = 'segmento_sin_precio';
        if ($missing) $states[] = 'respuestas_pendientes';
        if ($itemsMissingPrice || $extraIncomplete) $states[] = 'conceptos_pendientes_de_cotizacion';
        if ($service['estimation_mode'] !== 'automatic') $states[] = 'revision_profesional';
        $complete = !$states;
        if ($complete) $states[] = 'listo';

        $tariff = $main['tariff_id'] ? DB::table('tariff_versions')->find($main['tariff_id']) : null;
        $ccl = $main['ccl_rate'];
        $ars = static fn(?float $amount): ?float => $amount === null ? null
            : ($currency === 'ARS' ? round($amount, 2) : ($currency === 'USD' && $ccl !== null ? round($amount * (float)$ccl, 2) : null));
        $segmentAnswer = collect($main['answers'])->first(function ($answer) use ($questions) {
            $question = collect($questions)->firstWhere('id', $answer['id']);
            return ($question['clave_sistema'] ?? null) === \App\Services\Catalog\TipoCliente::PREGUNTA;
        });

        return response()->json([
            'estado' => $states,
            'completa' => $complete,
            'preguntas_pendientes' => $missing,
            'servicio' => ['id' => $service['id'], 'nombre' => $service['name']],
            'principal' => [
                'segmento' => $segmentAnswer['label'] ?? null,
                'subtotal_minimo' => $main['subtotal_min'], 'subtotal_maximo' => $main['subtotal_max'],
                'total_estimado' => $mainComplete ? ['minimo' => $main['min'], 'maximo' => $main['max']] : null,
                'completa' => $mainComplete,
            ],
            'base' => ['minimo' => $main['base_min'], 'maximo' => $main['base_max'], 'moneda' => $currency],
            'adicionales' => collect($main['items'])->map(fn($item) => [
                ...$item, 'servicio' => $service['name'], 'subtotal_minimo' => $item['subtotal_min'],
                'subtotal_maximo' => $item['subtotal_max'], 'estado' => $item['min'] === null ? 'sin_precio' : 'listo',
            ])->all(),
            'servicios_adicionales' => $extraRows,
            'total_adicionales' => [
                'minimo' => round(collect($main['items'])->sum(fn($item) => $item['subtotal_min'] ?? 0) + $extraKnownMin, 2),
                'maximo' => round(collect($main['items'])->sum(fn($item) => $item['subtotal_max'] ?? 0) + $extraKnownMax, 2),
                'parcial' => $itemsMissingPrice || $extraIncomplete,
            ],
            'subtotal_conocido' => $knownMin === null ? null : ['minimo' => $knownMin, 'maximo' => $knownMax],
            'total_estimado' => $complete && $main['min'] !== null
                ? ['minimo' => round($main['min'] + $extraKnownMin, 2), 'maximo' => round($main['max'] + $extraKnownMax, 2)] : null,
            'moneda' => $currency,
            'ccl' => $ccl === null ? null : ['valor' => $ccl, 'fuente' => $main['ccl_source']],
            'equivalente_ars' => ['subtotal_minimo' => $ars($knownMin), 'subtotal_maximo' => $ars($knownMax),
                'total_minimo' => $complete ? $ars(round($main['min'] + $extraKnownMin, 2)) : null,
                'total_maximo' => $complete ? $ars(round($main['max'] + $extraKnownMax, 2)) : null],
            'version_tarifario' => $tariff ? ['id' => $tariff->id, 'nombre' => $tariff->name] : null,
            'revision' => RevisionPrevisualizacion::generar($main, $extras),
            'requiere_revision' => !$complete,
        ]);
    }

    private function rejectUnknownQuestionIds(array $answers, array $questions, string $field): void
    {
        $allowed = array_map('strval', array_column($questions, 'id'));
        $unknown = array_diff(array_map('strval', array_keys($answers)), $allowed);
        if ($unknown) {
            throw ValidationException::withMessages([$field => 'Hay respuestas que no corresponden a las preguntas de este servicio.']);
        }
    }

    private function missingRequired(array $questions, array $answers): array
    {
        return collect($questions)->filter(function ($question) use ($answers) {
            if (!$question['required']) return false;
            $value = $answers[$question['id']] ?? null;
            return $value === null || $value === '' || $value === [];
        })->map(fn($question) => $question['question'])->values()->all();
    }
}
