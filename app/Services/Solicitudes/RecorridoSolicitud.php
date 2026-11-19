<?php

namespace App\Services\Solicitudes;

use App\Services\Catalog\ServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// El borrador anónimo vive en la sesión; nunca permite consultar solicitudes ni estimaciones de otra cuenta.
class RecorridoSolicitud
{
    public function __construct(private ServiceCatalog $catalog) {}

    // Carga preguntas públicas en dos consultas para que el selector pueda cambiar de servicio sin perder el formulario.
    public function questionMap(array $services): array
    {
        $questions = DB::table('estimator_questions')->where('active', 1)->orderBy('sort_order')->get();
        $options = DB::table('estimator_options')->where('active', 1)->orderBy('sort_order')->get()->groupBy('question_id');
        $map = [];
        foreach ($services as $service) {
            $map[$service['database_id']] = $questions->filter(fn ($question) => $question->service_id === null || (int) $question->service_id === (int) $service['database_id'])
                ->map(fn ($question) => [...(array) $question, 'options' => ($options[$question->id] ?? collect())->values()->all(),
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
        $resume = $pending && (int) ($pending['form']['estimate_id'] ?? 0) === (int) ($estimate['id'] ?? 0)
            && (int) ($pending['form']['id'] ?? 0) === (int) $draftId
            && (! $requestedSlug || $requestedSlug === $pending['calculation']['service']['id']);
        $service = $estimate
            ? collect($data['services'])->firstWhere('database_id', $estimate['service_id'])
            : ($requestedSlug ? $this->catalog->service((string) $requestedSlug)
                : ($resume ? $pending['calculation']['service'] : ($data['selected'] ?? $data['services'][0] ?? null)));
        $map = $this->questionMap($data['services']);
        $initialAnswers = array_replace(
            collect($data['pending']['answers'] ?? [])->mapWithKeys(fn ($answer) => [$answer['id'] => $answer['value']])->all(),
            $data['initialAnswers'] ?? [],
        );
        $descriptionQuestion = collect($map[$service['database_id'] ?? 0] ?? [])->firstWhere('from_project_description', true);
        $description = $descriptionQuestion ? ($initialAnswers[$descriptionQuestion['id']] ?? '') : '';
        $clientType = $request->query('cliente');
        foreach ($map[$service['database_id'] ?? 0] ?? [] as $question) {
            if (in_array($clientType, ['Particular', 'PyME', 'Empresa'], true)) {
                $option = collect($question['options'])->firstWhere('label', $clientType);
                if ($option) $initialAnswers[$question['id']] = $option->id;
            }
        }
        return [...$data, 'screen' => 'publicar', 'area' => $data['area'] ?? ($request->user() ? 'cliente' : 'public'),
            'accountContact' => $request->user()?->contactDetails(), 'requestQuestions' => $map,
            'requestServiceId' => $service['database_id'] ?? null,
            'requestDraft' => $resume ? $pending['form'] : null,
            'requestCalculation' => $resume ? $pending['calculation'] : null,
            'requestAdditionalPricing' => $resume ? ($pending['additional_services'] ?? []) : [],
            'requestStep' => $resume ? 3 : 1, 'requestInitialAnswers' => $initialAnswers,
            'requestInitialDescription' => is_string($description) ? $description : '', 'allowPublicRequest' => (bool) config('synaux.stage_two_enabled'),
        ];
    }

    // La descripción del proyecto responde la pregunta genérica de servicios manuales, sin pedir el mismo texto dos veces.
    public function calculate(array $data): array
    {
        $service = collect($this->catalog->services())->firstWhere('database_id', (int) $data['service_id']);
        if (! $service) throw ValidationException::withMessages(['service_id' => 'Elegí un servicio disponible.']);
        $answers = $data['answers'] ?? [];
        foreach ($this->questionMap([$service])[$service['database_id']] as $question) {
            if ($question['from_project_description']) $answers[$question['id']] = $data['description'];
        }
        return $this->catalog->calculate($service['id'], $answers);
    }

    // Cada adicional se valida con sus propias preguntas. Las comunes se contestan en el servicio principal.
    public function calculateAdditionalServices(int $mainId, array $items, array $mainAnswers, string $currency, array $previous = [], bool $allowIncomplete = false): array
    {
        if (! $items) {
            return [];
        }

        $catalog = collect($this->catalog->services())->keyBy('database_id');
        $previousByService = array_column($previous, null, 'service_id');
        $segments = ['Particular', 'PyME', 'Empresa'];
        $mainSegment = collect($mainAnswers)->pluck('label')->first(fn ($label) => in_array($label, $segments, true)) ?? 'Particular';
        $snapshots = [];
        foreach ($items as $index => $item) {
            $serviceId = (int) $item['service_id'];
            $service = $catalog->get($serviceId);
            if (! $service || $serviceId === $mainId) {
                throw ValidationException::withMessages(["additional_services.$index.service_id" => 'Elegí otro servicio disponible.']);
            }
            $questions = $this->catalog->questions($serviceId, specificOnly: true);
            $answers = $item['answers'] ?? [];
            // Los pedidos guardados con el formulario anterior usaban el segmento del principal.
            if (! array_key_exists('answers', $item)) {
                foreach ($questions as $question) {
                    $options = collect($question['options']);
                    if ($service['estimation_mode'] === 'manual' && $question['input_type'] === 'text'
                        && $question['question'] === '¿Qué objetivo querés lograr y qué necesitás resolver?') {
                        $answers[$question['id']] = $item['notes'] ?? null;
                    }
                    if ($question['input_type'] === 'single_choice' && $options->pluck('label')->intersect($segments)->count() === 3) {
                        $answers[$question['id']] = $options->firstWhere('label', $mainSegment)->id;
                    }
                }
            }
            try {
                $result = $this->catalog->calculate($service['id'], $answers, $questions, $allowIncomplete);
            } catch (ValidationException $error) {
                $prefix = "additional_services.$index.";
                throw ValidationException::withMessages(collect($error->errors())->mapWithKeys(
                    fn ($messages, $field) => [$prefix.$field => $messages],
                )->all());
            }
            $priced = $result['min'] !== null && $result['currency'] === $currency;
            $reference = $priced ? ['min' => $result['min'], 'max' => $result['max'], 'currency' => $currency] : null;
            $breakdown = $priced ? ['base_min' => $result['base_min'], 'base_max' => $result['base_max'], 'items' => $result['items']] : null;
            $saved = $previousByService[$serviceId] ?? null;
            if ((! $allowIncomplete || $priced) && ($saved['pricing_reference']['currency'] ?? null) === $currency
                && ($saved['answers'] ?? null) == $result['answers']) {
                $reference = $saved['pricing_reference'];
                $breakdown = $saved['pricing_breakdown'] ?? null;
            }
            $snapshots[] = [
                'service_id' => $serviceId, 'name' => $service['name'], 'quantity' => $item['quantity'],
                'notes' => $item['notes'] ?? '', 'answers' => $result['answers'],
                'pricing_reference' => $reference, 'pricing_breakdown' => $breakdown,
            ];
        }

        return $snapshots;
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
        if (! $estimateId) {
            unset($data['estimate_id']);
        }
        $previous = $this->pending($request);
        if ((int) ($previous['form']['service_id'] ?? 0) !== (int) $data['service_id']
            || (int) ($previous['form']['estimate_id'] ?? 0) !== (int) $estimateId
            || (int) ($previous['form']['id'] ?? 0) !== (int) $draftId) {
            $previous = null;
        }
        if ($estimateId) {
            $estimate = DB::table('estimates')->where('id', $estimateId)->where('user_id', $request->user()->id)
                ->where('service_id', $data['service_id'])
                ->when((int) ($draft->estimate_id ?? 0) !== (int) $estimateId, fn ($query) => $query->whereNull('deleted_at'))
                ->firstOrFail();
            $data['estimate_id'] = $estimateId;
            $calculation = [
                'service' => collect($this->catalog->services())->firstWhere('database_id', $estimate->service_id) ?? abort(404),
                'answers' => json_decode($estimate->answers_snapshot ?? '[]', true),
                'min' => $estimate->price_min, 'currency' => $estimate->currency_snapshot,
            ];
        } else {
            $calculation = $this->calculate($data);
            if (! ($previous['form']['estimate_id'] ?? null) && $previous
                && $previous['calculation']['service']['database_id'] === (int) $data['service_id']
                && $previous['calculation']['answers'] == $calculation['answers']) {
                $calculation = $previous['calculation'];
            }
        }
        $additional = $this->calculateAdditionalServices(
            (int) $data['service_id'], $data['additional_services'], $calculation['answers'],
            $calculation['currency'] ?? 'ARS', $previous['additional_services'] ?? [],
        );
        $form = collect($data)->except('destination')->all();
        $request->session()->put('pending_request', [
            'form' => $form, 'calculation' => $calculation, 'additional_services' => $additional, 'expires_at' => now()->addHours(2)->timestamp,
        ]);
        if ($draftId) {
            return '/solicitudes/'.$draftId;
        }
        if ($estimateId) {
            return '/solicitudes/nueva?estimacion='.$estimateId.'&continuar=1';
        }

        return ($request->user() || $data['destination'] === 'review') ? '/solicitudes/nueva?continuar=1' : ($data['destination'] === 'register' ? '/crear-cuenta' : '/ingresar');
    }
}
