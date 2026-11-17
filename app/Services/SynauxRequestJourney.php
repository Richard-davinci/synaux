<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// El borrador anónimo vive en la sesión; nunca permite consultar solicitudes ni estimaciones de otra cuenta.
class SynauxRequestJourney
{
    public function __construct(private SynauxCatalog $catalog) {}

    // Carga preguntas públicas en dos consultas para que el selector pueda cambiar de servicio sin perder el formulario.
    public function questionMap(array $services): array
    {
        $questions = \Illuminate\Support\Facades\DB::table('estimator_questions')->where('active', 1)->orderBy('sort_order')->get();
        $options = \Illuminate\Support\Facades\DB::table('estimator_options')->where('active', 1)->orderBy('sort_order')->get()->groupBy('question_id');
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

    public function pageData(Request $request, array $data): array
    {
        $pending = $this->pending($request);
        $estimate = $data['selectedEstimate'] ?? null;
        $requestedSlug = $request->query('servicio');
        $resume = ! $estimate && $pending && (! $requestedSlug || $requestedSlug === $pending['calculation']['service']['id']);
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

    // Antes del ingreso solo se guardan los campos necesarios del proyecto, nunca credenciales ni datos privados de cuentas.
    public function prepare(Request $request): string
    {
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id', 'answers' => 'present|array|max:100',
            'title' => 'required|string|min:5|max:255', 'description' => 'required|string|min:10|max:10000',
            'skills' => 'nullable|string|max:1000', 'budget_reference' => 'nullable|string|max:255', 'desired_deadline' => 'nullable|string|max:255',
            'additional_services' => 'present|array|max:20',
            'additional_services.*.service_id' => 'required|integer|distinct|exists:services,id',
            'additional_services.*.quantity' => 'required|integer|min:1|max:1000',
            'additional_services.*.notes' => 'nullable|string|max:2000',
            'destination' => 'required|in:login,register,review',
        ]);
        $available = collect($this->catalog->services())->pluck('database_id')->all();
        foreach ($data['additional_services'] as $index => $extra) {
            if (! in_array((int) $extra['service_id'], $available, true) || (int) $extra['service_id'] === (int) $data['service_id']) {
                throw ValidationException::withMessages(["additional_services.$index.service_id" => 'Elegí otro servicio disponible.']);
            }
        }
        $calculation = $this->calculate($data);
        $previous = $this->pending($request);
        if ($previous && $previous['calculation']['service']['database_id'] === (int) $data['service_id']
            && $previous['calculation']['answers'] == $calculation['answers']) {
            $calculation = $previous['calculation'];
        }
        $form = collect($data)->except('destination')->all();
        $request->session()->put('pending_request', ['form' => $form, 'calculation' => $calculation, 'expires_at' => now()->addHours(2)->timestamp]);
        return ($request->user() || $data['destination'] === 'review') ? '/solicitudes/nueva?continuar=1' : ($data['destination'] === 'register' ? '/crear-cuenta' : '/ingresar');
    }
}
