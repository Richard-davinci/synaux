<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Centraliza catálogo, preguntas y cálculo para que el resultado público y el historial coincidan.
class SynauxCatalog
{
    // Une servicios y categorías y prepara los campos compartidos por catálogo, formularios y detalles.
    public function services(bool $all = false): array
    {
        return DB::table('services as s')->join('service_categories as c', 'c.id', '=', 's.service_category_id')
            ->when(! $all, fn ($q) => $q->where('s.active', 1)->where('c.active', 1))
            ->orderBy('s.id')->select('s.*', 'c.name as category')->get()->map(fn ($s) => [
                ...((array) $s), 'database_id' => $s->id, 'id' => $s->slug,
                'summary' => $s->summary ?? $s->description,
                'includes' => json_decode($s->deliverables ?? '[]', true),
                'mode' => $s->estimation_mode === 'automatic' ? 'Estimación automática' : 'Presupuesto profesional',
            ])->all();
    }

    // Resuelve un servicio activo por su identificador público; devuelve 404 si no existe.
    public function service(string $slug): array
    {
        return collect($this->services())->firstWhere('id', $slug) ?? abort(404);
    }

    // Carga preguntas y opciones activas del servicio en el orden configurado.
    public function questions(int $serviceId): array
    {
        return DB::table('estimator_questions')->where('active', 1)
            ->where(fn ($q) => $q->where('service_id', $serviceId)->orWhereNull('service_id'))
            ->orderBy('sort_order')->get()->map(fn ($q) => [
                ...((array) $q),
                'options' => DB::table('estimator_options')->where('question_id', $q->id)->where('active', 1)->orderBy('sort_order')->get(),
            ])->all();
    }

    // Valida las respuestas, calcula en el servidor y conserva el resultado en la sesión para continuar.
    public function calculate(string $slug, array $answers): array
    {
        $service = $this->service($slug);
        $questions = $this->questions($service['database_id']);
        $snapshot = [];
        $selected = [];
        $quantities = [];
        // Revalida todas las respuestas en el servidor; no confía en rótulos ni importes del navegador.
        foreach ($questions as $q) {
            $value = $answers[$q['id']] ?? null;
            $values = $q['input_type'] === 'multiple_choice' ? (array) $value : [$value];
            if ($value === null || $value === '' || $values === []) {
                throw ValidationException::withMessages(["answers.{$q['id']}" => 'Completá esta respuesta.']);
            }
            $labels = [];
            foreach ($values as $v) {
                if (in_array($q['input_type'], ['single_choice', 'multiple_choice'], true)) {
                    $option = collect($q['options'])->first(fn ($o) => (string) $o->id === (string) $v);
                    if (! $option) {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Elegí una opción válida.']);
                    }
                    $selected[] = $option->id;
                    $labels[] = $option->label;
                } elseif ($q['input_type'] === 'number') {
                    if (! is_numeric($v) || $v <= 0 || $v > 10000) {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Ingresá una cantidad entre 1 y 10000.']);
                    }
                    if ($q['quantity_component_id']) {
                        $quantities[$q['quantity_component_id']] = (float) $v;
                    }
                    $labels[] = (string) $v;
                } else {
                    if (! is_string($v) || mb_strlen(trim($v)) > 5000 || trim($v) === '') {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Escribí una respuesta de hasta 5000 caracteres.']);
                    }
                    $labels[] = trim($v);
                }
            }
            $snapshot[] = ['id' => $q['id'], 'question' => $q['question'], 'value' => $value, 'label' => implode(', ', $labels)];
        }
        $components = DB::table('service_components')->where('service_id', $service['database_id'])->where('active', 1)->get()->keyBy('id');
        $rules = DB::table('estimation_rules')->whereIn('estimator_option_id', $selected)->where('active', 1)->get();
        foreach ($components as $c) {
            if ($c->is_default) {
                $quantities[$c->id] = $quantities[$c->id] ?? (float) $c->default_quantity;
            }
        }
        foreach ($rules as $rule) {
            if ($components->has($rule->service_component_id)) {
                $quantities[$rule->service_component_id] = ($quantities[$rule->service_component_id] ?? 0) + (float) $rule->quantity;
            }
        }
        $tariff = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->first();
        $base = $tariff ? DB::table('service_prices')->where('tariff_version_id', $tariff->id)->where('service_id', $service['database_id'])->first() : null;
        $available = $service['estimation_mode'] === 'automatic' && $base !== null;
        $min = $base ? (int) round($base->price_min * 100) : 0;
        $max = $base ? (int) round($base->price_max * 100) : 0;
        $items = [];
        foreach ($quantities as $id => $quantity) {
            if ($quantity <= 0 || ! $components->has($id)) {
                continue;
            }
            $price = $tariff ? DB::table('component_prices')->where('tariff_version_id', $tariff->id)->where('service_component_id', $id)->first() : null;
            $available = $available && $price !== null;
            $items[] = ['component_id' => $id, 'name' => $components[$id]->name, 'unit' => $components[$id]->unit_code, 'quantity' => $quantity, 'min' => $price?->price_min, 'max' => $price?->price_max];
            $min += (int) round(($price?->price_min ?? 0) * 100 * $quantity);
            $max += (int) round(($price?->price_max ?? 0) * 100 * $quantity);
        }

        // Sin configuración completa se informa revisión profesional, nunca un precio ficticio.
        return ['key' => (string) Str::uuid(), 'service' => $service, 'answers' => $snapshot, 'items' => $items,
            'mode' => $available ? 'automatic' : 'manual', 'tariff_id' => $available ? $tariff->id : null,
            'currency' => $tariff?->currency_code ?? 'ARS', 'base_min' => $available ? $base->price_min : null,
            'base_max' => $available ? $base->price_max : null, 'min' => $available ? $min / 100 : null,
            'max' => $available ? $max / 100 : null, 'date' => now()->toDateTimeString()];
    }
}
