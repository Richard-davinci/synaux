<?php

namespace App\Services\Solicitudes;

use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\EconomiaEstimada;
use App\Services\Catalog\TipoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// El borrador anónimo vive en la sesión; nunca permite consultar solicitudes ni estimaciones de otra cuenta.
class RecorridoSolicitud
{
    public function __construct(private ServiceCatalog $catalog)
    {
    }

    // Carga preguntas públicas en dos consultas para que el selector pueda cambiar de servicio sin perder el formulario.
    public function questionMap(array $services): array
    {
        $questions = DB::table('estimator_questions')->where('active', 1)->orderBy('sort_order')->get();
        $options = DB::table('estimator_options')->where('active', 1)->orderBy('sort_order')->get()->groupBy('question_id');
        $map = [];
        foreach ($services as $service) {
            $map[$service['database_id']] = $questions->filter(fn($question) => $question->service_id === null || (int)$question->service_id === (int)$service['database_id'])
                ->map(fn($question) => [...(array)$question, 'options' => ($options[$question->id] ?? collect())->values()->all(),
                    'from_project_description' => $service['estimation_mode'] === 'manual' && $question->input_type === 'text'
                        && $question->question === '¿Qué objetivo querés lograr y qué necesitás resolver?',
                ])->values()->all();
        }
        return $map;
    }

    // Recupera únicamente el borrador de esta sesión y descarta automáticamente los datos vencidos.
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get('pending_request');
        if ($pending && ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('pending_request');
            return null;
        }
        return $pending;
    }

    public function pageData(Request $request, array $data, ?int $draftId = null): array
    {
        $pending = $this->pending($request);
        $estimate = $data['selectedEstimate'] ?? null;
        $requestedSlug = $request->query('servicio');
        // Una solicitud basada en una estimación guardada solo retoma el borrador de esa misma estimación.
        $resume = $pending && (int)($pending['form']['estimate_id'] ?? 0) === (int)($estimate['id'] ?? 0)
            && (int)($pending['form']['id'] ?? 0) === (int)$draftId
            && (!$requestedSlug || $requestedSlug === $pending['calculation']['service']['id']);
        $service = $estimate
            ? collect($data['services'])->firstWhere('database_id', $estimate['service_id'])
            : ($requestedSlug ? $this->catalog->service((string)$requestedSlug)
                : ($resume ? $pending['calculation']['service'] : ($data['selected'] ?? $data['services'][0] ?? null)));
        $map = $this->questionMap($data['services']);
        $initialAnswers = array_replace(
            collect($data['pending']['answers'] ?? [])->mapWithKeys(fn($answer) => [$answer['id'] => $answer['value']])->all(),
            $data['initialAnswers'] ?? [],
        );
        $descriptionQuestion = collect($map[$service['database_id'] ?? 0] ?? [])->firstWhere('from_project_description', true);
        $description = $descriptionQuestion ? ($initialAnswers[$descriptionQuestion['id']] ?? '') : '';
        $initialAnswers = array_replace($initialAnswers, TipoCliente::preseleccion(
            $map[$service['database_id'] ?? 0] ?? [], $request->query('cliente'),
        ));
        return [...$data, 'screen' => 'publicar', 'area' => $data['area'] ?? ($request->user() ? 'cliente' : 'public'),
            'accountContact' => $request->user()?->contactDetails(), 'requestQuestions' => $map,
            'requestServiceId' => $service['database_id'] ?? null,
            'requestDraft' => $resume ? $pending['form'] : null,
            'requestCalculation' => $resume ? $pending['calculation'] : null,
            'requestEstimateClientType' => TipoCliente::desdeRespuestas($estimate['answers'] ?? []),
            'requestAdditionalPricing' => $resume ? ($pending['additional_services'] ?? []) : [],
            'requestStep' => $resume ? 3 : 1, 'requestInitialAnswers' => $initialAnswers,
            'requestInitialDescription' => is_string($description) ? $description : '', 'allowPublicRequest' => (bool)config('synaux.stage_two_enabled'),
        ];
    }

    // La descripción del proyecto responde la pregunta genérica de servicios manuales, sin pedir el mismo texto dos veces.
    public function calculate(array $data): array
    {
        $service = collect($this->catalog->services())->firstWhere('database_id', (int)$data['service_id']);
        if (!$service) throw ValidationException::withMessages(['service_id' => 'Elegí un servicio disponible.']);
        $answers = $data['answers'] ?? [];
        foreach ($this->questionMap([$service])[$service['database_id']] as $question) {
            if ($question['from_project_description']) $answers[$question['id']] = $data['description'];
        }
        return $this->catalog->calculate($service['id'], $answers);
    }

    // Conserva el cálculo revisado cuando el formulario conserva el servicio y las respuestas, aunque se editen sus etiquetas.
    public function retomarCalculo(array $datos, ?array $anterior): array
    {
        $formulario = $anterior['form'] ?? [];
        $calculo = $anterior['calculation'] ?? null;
        if ($calculo && !($formulario['estimate_id'] ?? null) && (int)$formulario['service_id'] === (int)$datos['service_id']
            && (int)($formulario['id'] ?? 0) === (int)($datos['id'] ?? 0)
            && ($formulario['description'] ?? '') === ($datos['description'] ?? '')
            && $this->mismasRespuestas($formulario['answers'] ?? [], $datos['answers'] ?? [])) {
            return $calculo;
        }

        return $this->calculate($datos);
    }

    // Compara IDs y valores del formulario. Ignora el orden de selección y las respuestas vacías.
    private function mismasRespuestas(array $anterior, array $actual): bool
    {
        $normalizar = static function (array $respuestas): array {
            $valores = [];
            foreach ($respuestas as $id => $valor) {
                if ($valor === '' || $valor === null || $valor === []) continue;
                if (is_array($valor)) {
                    if (collect($valor)->contains(fn($opcion) => !is_int($opcion) && !is_string($opcion) && !is_float($opcion))) return [null];
                    $valor = array_unique(array_map('strval', $valor));
                    sort($valor, SORT_STRING);
                } else {
                    if (is_bool($valor)) return [null];
                    $valor = trim((string)$valor);
                }
                $valores[$id] = $valor;
            }
            ksort($valores);
            return $valores;
        };
        $valores = $normalizar($actual);
        return $valores !== [null] && $normalizar($anterior) === $valores;
    }

    // Cada adicional se valida con sus propias preguntas. Las comunes se contestan en el servicio principal.
    public function calculateAdditionalServices(int $mainId, array $items, array $mainAnswers, string $currency, array $previous = [], bool $allowIncomplete = false): array
    {
        if (!$items) {
            return [];
        }

        $catalog = collect($this->catalog->services())->keyBy('database_id');
        $previousByService = array_column($previous, null, 'service_id');
        $segmentoPrincipal = TipoCliente::desdeRespuestas($mainAnswers);
        $opcionesGlobales = TipoCliente::opcionesActivas();
        $opcionHeredada = $opcionesGlobales->firstWhere('clave_segmento', $segmentoPrincipal);
        $preguntaHeredada = $opcionHeredada ? DB::table('estimator_questions')->find($opcionHeredada->question_id) : null;
        $snapshots = [];
        foreach ($items as $index => $item) {
            $serviceId = (int)$item['service_id'];
            $service = $catalog->get($serviceId);
            $saved = $previousByService[$serviceId] ?? null;
            $answers = $item['answers'] ?? [];
            // El navegador no decide el tipo heredado. La instantánea aprobada se lee antes que el catálogo mutable.
            if ($preguntaHeredada) unset($answers[$preguntaHeredada->id]);
            if ($serviceId !== $mainId && $saved && $this->coincideAdicional($saved, $item, $answers, $segmentoPrincipal, $currency, $allowIncomplete)) {
                $snapshots[] = [...$saved, 'notes' => $item['notes'] ?? ''];
                continue;
            }
            if (!$service || $serviceId === $mainId) {
                throw ValidationException::withMessages(["additional_services.$index.service_id" => 'Elegí otro servicio disponible.']);
            }
            $questions = $this->catalog->questions($serviceId, specificOnly: true);
            // Completa el tipo de cliente desde el principal. Las configuraciones antiguas conservan su respuesta explícita.
            $answers += TipoCliente::preseleccion($questions, $segmentoPrincipal);
            if ($preguntaHeredada) {
                $questions[] = [...(array)$preguntaHeredada, 'options' => $opcionesGlobales];
                $answers[$preguntaHeredada->id] = $opcionHeredada->id;
            }
            // Los pedidos anteriores a las preguntas de adicionales conservan su descripción original.
            if (!array_key_exists('answers', $item)) {
                foreach ($questions as $question) {
                    if ($service['estimation_mode'] === 'manual' && $question['input_type'] === 'text'
                        && $question['question'] === '¿Qué objetivo querés lograr y qué necesitás resolver?') {
                        $answers[$question['id']] = $item['notes'] ?? null;
                    }
                }
            }
            try {
                $result = $this->catalog->calculate($service['id'], $answers, $questions, $allowIncomplete);
            } catch (ValidationException $error) {
                $prefix = "additional_services.$index.";
                throw ValidationException::withMessages(collect($error->errors())->mapWithKeys(
                    fn($messages, $field) => [$prefix . $field => $messages],
                )->all());
            }
            $priced = $result['min'] !== null && $result['currency'] === $currency;
            $reference = $priced ? ['min' => $result['min'], 'max' => $result['max'], 'currency' => $currency] : null;
            $parcial = $reference === null && $result['currency'] === $currency ? EconomiaEstimada::referenciaParcial(
                $result['base_min'], $result['base_max'], $result['items'], $currency,
            ) : null;
            // El desglose guarda la procedencia y los subtotales tanto completos como pendientes de revisión.
            $breakdown = ['base_min' => $result['base_min'], 'base_max' => $result['base_max'], 'items' => $result['items'],
                'currency' => $result['currency'], 'tariff_version_id' => $result['tariff_id'],
                'ccl_rate' => $result['ccl_rate'], 'ccl_source' => $result['ccl_source'],
                'subtotal_min' => $result['subtotal_min'], 'subtotal_max' => $result['subtotal_max'],
                'subtotal_total_min' => $result['subtotal_min'] === null ? null : round($result['subtotal_min'] * $item['quantity'], 2),
                'subtotal_total_max' => $result['subtotal_max'] === null ? null : round($result['subtotal_max'] * $item['quantity'], 2),
                'partial' => $reference === null, 'incomplete' => $result['incomplete'],
            ];
            // Conserva las respuestas propias del adicional y registra por separado el segmento heredado.
            $respuestasPropias = array_values(array_filter($result['answers'], fn($respuesta) => $respuesta['id'] !== $preguntaHeredada?->id));
            $snapshots[] = [
                'service_id' => $serviceId, 'name' => $service['name'], 'quantity' => $item['quantity'],
                'notes' => $item['notes'] ?? '', 'answers' => $respuestasPropias, 'segmento_cliente' => $segmentoPrincipal,
                'pricing_reference' => $reference, 'pricing_partial' => $parcial, 'pricing_breakdown' => $breakdown,
            ];
        }

        return $snapshots;
    }

    // Una edición de cantidad, respuestas o segmento genera un cálculo nuevo. Un borrador incompleto requiere validar al enviarse.
    private function coincideAdicional(array $guardado, array $item, array $answers, ?string $segmento, string $moneda, bool $permitirIncompleto): bool
    {
        $anterior = $guardado['pricing_breakdown'] ?? [];
        $respuestasGuardadas = $guardado['answers'] ?? [];
        if ((int)$guardado['quantity'] !== (int)$item['quantity']
            || (!$permitirIncompleto && ($anterior['incomplete'] ?? false))
            || (array_key_exists('segmento_cliente', $guardado) && $guardado['segmento_cliente'] !== $segmento)
            || ($guardado['pricing_reference']['currency'] ?? $guardado['pricing_partial']['currency'] ?? $anterior['currency'] ?? $moneda) !== $moneda
            || (!array_key_exists('answers', $guardado) && $answers)) {
            return false;
        }

        return $this->mismasRespuestas(array_column($respuestasGuardadas, 'value', 'id'), $answers);
    }

    // Antes del ingreso solo se guardan los campos necesarios del proyecto, nunca credenciales ni datos privados de cuentas.
    public function prepare(Request $request): string
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id', 'answers' => 'present|array|max:100',
            'estimate_id' => 'nullable|integer', 'id' => 'nullable|integer',
            'title' => 'required|string|min:5|max:255', 'description' => 'required|string|min:10|max:10000',
            'skills' => 'nullable|string|max:1000', 'budget_reference' => 'nullable|string|max:255', 'desired_deadline' => 'nullable|string|max:255',
            'additional_services' => 'present|array|max:20',
            'additional_services.*.service_id' => 'required|integer|distinct|exists:services,id',
            'additional_services.*.quantity' => 'required|integer|min:1|max:1000',
            'additional_services.*.notes' => 'nullable|string|max:2000',
            'additional_services.*.answers' => 'sometimes|array|max:100',
            'destination' => 'required|in:login,register,review',
        ]);
        // El cálculo de un borrador solo puede retomarlo su propietario.
        $draftId = $data['id'] ?? null;
        if ($draftId) {
            abort_unless($request->user(), 403);
            $draft = DB::table('project_requests')->where('id', $draftId)->where('user_id', $request->user()->id)
                ->where('status', 'draft')->firstOrFail();
        }
        $estimateId = $request->user() ? ($data['estimate_id'] ?? $draft->estimate_id ?? null) : null;
        if (!$estimateId) {
            unset($data['estimate_id']);
        }
        $previous = $this->pending($request);
        if ((int)($previous['form']['service_id'] ?? 0) !== (int)$data['service_id']
            || (int)($previous['form']['estimate_id'] ?? 0) !== (int)$estimateId
            || (int)($previous['form']['id'] ?? 0) !== (int)$draftId) {
            $previous = null;
        }
        if ($estimateId) {
            $estimate = DB::table('estimates')->where('id', $estimateId)->where('user_id', $request->user()->id)
                ->where('service_id', $data['service_id'])
                ->when((int)($draft->estimate_id ?? 0) !== (int)$estimateId, fn($query) => $query->whereNull('deleted_at'))
                ->firstOrFail();
            $data['estimate_id'] = $estimateId;
            $calculation = [
                'service' => collect($this->catalog->services())->firstWhere('database_id', $estimate->service_id) ?? abort(404),
                'answers' => json_decode($estimate->answers_snapshot ?? '[]', true),
                'min' => $estimate->price_min, 'currency' => $estimate->currency_snapshot,
            ];
        } else {
            $calculation = $this->retomarCalculo($data, $previous);
        }
        $additional = $this->calculateAdditionalServices(
            (int)$data['service_id'], $data['additional_services'], $calculation['answers'],
            $calculation['currency'] ?? 'ARS', $previous['additional_services'] ?? [],
        );
        $form = collect($data)->except('destination')->all();
        $request->session()->put('pending_request', [
            'form' => $form, 'calculation' => $calculation, 'additional_services' => $additional, 'expires_at' => now()->addHours(2)->timestamp,
        ]);
        if ($draftId) {
            return '/solicitudes/' . $draftId;
        }
        if ($estimateId) {
            return '/solicitudes/nueva?estimacion=' . $estimateId . '&continuar=1';
        }

        return ($request->user() || $data['destination'] === 'review') ? '/solicitudes/nueva?continuar=1' : ($data['destination'] === 'register' ? '/crear-cuenta' : '/ingresar');
    }
}
