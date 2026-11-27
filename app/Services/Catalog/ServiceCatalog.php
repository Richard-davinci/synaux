<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Centraliza catálogo, preguntas y cálculo para que el resultado público y el historial coincidan.
class ServiceCatalog
{
    public const FEATURED_LIMIT = 3;

    // Conserva la selección automática hasta el primer guardado y excluye servicios despublicados.
    public function featuredServices(): array
    {
        $services = collect($this->services())->keyBy('database_id');
        $saved = DB::table('site_home_settings')->where('id', 1)->value('featured_service_ids');

        if ($saved === null) {
            return $services->unique('service_category_id')->take(self::FEATURED_LIMIT)->values()->all();
        }

        return collect(json_decode($saved, true, flags: JSON_THROW_ON_ERROR))
            ->map(fn(int $id) => $services->get($id))->filter()->values()->all();
    }

    // Valida contra la oferta pública y guarda toda la selección en una sola escritura.
    public function saveFeaturedServices(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            // Comparte el bloqueo con el borrado porque los destacados guardan identificadores en JSON.
            DB::table('services')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
            $activeIds = array_column($this->services(), 'database_id');

            if (array_diff($ids, $activeIds)) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Solo podés destacar servicios activos de categorías activas.',
                ]);
            }

            DB::table('site_home_settings')->upsert([
                ['id' => 1, 'featured_service_ids' => json_encode($ids, JSON_THROW_ON_ERROR)],
            ], ['id'], ['featured_service_ids']);
        }, 3);
    }

    // Une servicios y categorías y prepara los campos compartidos por catálogo, formularios y detalles.
    public function services(bool $all = false): array
    {
        return DB::table('services as s')->join('service_categories as c', 'c.id', '=', 's.service_category_id')
            ->when(!$all, fn($q) => $q->where('s.active', 1)->where('c.active', 1))
            ->orderBy('s.id')->select('s.*', 'c.name as category')->get()->map(fn($s) => [
                ...((array)$s), 'database_id' => $s->id, 'id' => $s->slug,
                'summary' => $s->summary ?? $s->description,
                'includes' => json_decode($s->deliverables ?? '[]', true),
                'mode' => $s->estimation_mode === 'automatic' ? 'Estimación automática' : 'Presupuesto profesional',
            ])->all();
    }

    // Prepara referencias vigentes para todos los servicios activos o para una selección del catálogo.
    public function references(?int $tariffId = null, ?array $services = null): array
    {
        // Para pedidos anteriores se consulta su versión publicada, aunque ya no esté vigente.
        $tariff = $tariffId !== null
            ? DB::table('tariff_versions')->find($tariffId)
            : DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')->orderByDesc('id')->first();
        if (!$tariff) {
            return [];
        }
        $bases = DB::table('service_prices')->where('tariff_version_id', $tariff->id)->get()->keyBy('service_id');
        $prices = DB::table('component_prices')->where('tariff_version_id', $tariff->id)->get()->keyBy('service_component_id');
        $components = DB::table('service_components')->where('active', 1)->get()->groupBy('service_id');
        $references = [];
        foreach (($services ?? $this->services()) as $service) {
            $base = $bases->get($service['database_id']);
            $parts = $components->get($service['database_id'], collect());
            if (!$base || $parts->contains(fn($part) => !$prices->has($part->id))) {
                continue;
            }
            $min = (float)$base->price_min;
            $max = (float)$base->price_max;
            // Tarifas A/B/C directas prevalecen sobre la aproximación lineal del componente heredado.
            $sourceSegments = DB::table('tariff_segment_prices')->where('tariff_version_id', $tariff->id)
                ->where('service_id', $service['database_id'])->get();
            foreach ($parts->where('is_default', 1) as $part) {
                $min += $prices[$part->id]->price_min * $part->default_quantity;
                $max += $prices[$part->id]->price_max * $part->default_quantity;
            }
            // Cada segmento usa su opción y regla real; la tarjeta no calcula factores fijos en JavaScript.
            $segments = [];
            $segmentPart = $parts->firstWhere('slug', 'segmento-tarifario-2026-11');
            if ($sourceSegments->isNotEmpty()) {
                $segments = $sourceSegments->map(fn($item) => [
                    'label' => $item->segment, 'min' => (float)$item->price_min, 'max' => (float)$item->price_max,
                ])->all();
                $min = min(array_column($segments, 'min'));
                $max = max(array_column($segments, 'max'));
            } elseif ($segmentPart) {
                $options = DB::table('estimator_options as o')
                    ->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
                    ->join('estimation_rules as r', 'r.estimator_option_id', '=', 'o.id')
                    ->where('q.service_id', $service['database_id'])->where('q.active', 1)->where('o.active', 1)
                    ->where('r.active', 1)->where('r.service_component_id', $segmentPart->id)
                    ->orderBy('o.sort_order')->select('o.label', 'r.quantity')->get();
                foreach ($options as $option) {
                    $segments[] = [
                        'label' => $option->label,
                        'min' => round($min + $prices[$segmentPart->id]->price_min * $option->quantity, 2),
                        'max' => round($max + $prices[$segmentPart->id]->price_max * $option->quantity, 2),
                    ];
                }
            }
            $references[$service['id']] = [
                'min' => round($min, 2), 'max' => round($max, 2), 'currency' => $tariff->currency_code,
                'date' => $tariff->effective_from, 'segments' => $segments,
                'segment' => $parts->contains('slug', 'segmento-tarifario-2026-11') ? 'Particular' : null,
                'source' => $service['id'] === 'sitio-institucional' ? $tariff->source_url : null,
            ];
        }

        return $references;
    }

    // Los adicionales comparten segmento y moneda con el resumen de la solicitud.
    public function extraReference(?array $reference, array $answers, string $currency): ?array
    {
        if (!$reference || $reference['currency'] !== $currency) {
            return null;
        }

        $segment = collect($answers)->pluck('label')->first(fn($label) => in_array($label, ['Particular', 'PyME', 'Empresa'], true))
            ?? 'Particular';
        $price = $reference['segments'] ? collect($reference['segments'])->firstWhere('label', $segment) : $reference;

        return $price ? ['min' => $price['min'], 'max' => $price['max'], 'currency' => $currency] : null;
    }

    // Resuelve un servicio activo por su identificador público; devuelve 404 si no existe.
    public function service(string $slug): array
    {
        return collect($this->services())->firstWhere('id', $slug) ?? abort(404);
    }

    // Carga preguntas y opciones activas del servicio en el orden configurado.
    public function questions(int $serviceId, bool $specificOnly = false): array
    {
        return DB::table('estimator_questions')->where('active', 1)
            ->where(fn($q) => $q->where('service_id', $serviceId)->when(!$specificOnly, fn($query) => $query->orWhereNull('service_id')))
            ->orderBy('sort_order')->get()->map(fn($q) => [
                ...((array)$q),
                'options' => DB::table('estimator_options')->where('question_id', $q->id)->where('active', 1)->orderBy('sort_order')->get(),
            ])->all();
    }

    // Valida las respuestas, calcula en el servidor y conserva el resultado en la sesión para continuar.
    public function calculate(string $slug, array $answers, ?array $questions = null, bool $allowIncomplete = false): array
    {
        $service = $this->service($slug);
        $questions ??= $this->questions($service['database_id']);
        $snapshot = [];
        $selected = [];
        $quantities = [];
        $incomplete = false;
        // Revalida todas las respuestas en el servidor; no confía en rótulos ni importes del navegador.
        foreach ($questions as $q) {
            $value = $answers[$q['id']] ?? null;
            $values = $q['input_type'] === 'multiple_choice' ? (array)$value : [$value];
            if ($value === null || $value === '' || $values === []) {
                // Una respuesta opcional vacía no agrega componentes ni altera el importe.
                if (!($q['required'] ?? true)) continue;
                if ($allowIncomplete) {
                    $incomplete = true;
                    continue;
                }
                throw ValidationException::withMessages(["answers.{$q['id']}" => 'Completá esta respuesta.']);
            }
            if (collect($values)->contains(fn($answer) => !is_int($answer) && !is_float($answer) && !is_string($answer))) {
                throw ValidationException::withMessages(["answers.{$q['id']}" => 'Revisá el formato de esta respuesta.']);
            }
            $labels = [];
            foreach ($values as $v) {
                if (in_array($q['input_type'], ['single_choice', 'multiple_choice'], true)) {
                    $option = collect($q['options'])->first(fn($o) => (string)$o->id === (string)$v);
                    if (!$option) {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Elegí una opción válida.']);
                    }
                    $selected[] = $option->id;
                    $labels[] = $option->label;
                } elseif ($q['input_type'] === 'number') {
                    if (!is_numeric($v) || $v <= 0 || $v > 10000) {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Ingresá una cantidad entre 1 y 10000.']);
                    }
                    if ($q['quantity_component_id']) {
                        $quantities[$q['quantity_component_id']] = (float)$v;
                    }
                    $labels[] = (string)$v;
                } else {
                    if (!is_string($v) || mb_strlen(trim($v)) > 5000 || trim($v) === '') {
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
                $quantities[$c->id] = $quantities[$c->id] ?? (float)$c->default_quantity;
            }
        }
        foreach ($rules as $rule) {
            if ($components->has($rule->service_component_id)) {
                $quantities[$rule->service_component_id] = ($quantities[$rule->service_component_id] ?? 0) + (float)$rule->quantity;
            }
        }
        $tariff = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->first();
        // La referencia institucional de Tarifario.org solo cubre hasta cinco secciones.
        $sourceSegment = null;
        $answerLabels = collect($snapshot)->pluck('label');
        if ($tariff && $answerLabels->contains('1 a 5')) {
            $clientSegment = $answerLabels->first(fn($label) => in_array($label, ['Particular', 'PyME', 'Empresa'], true));
            if ($clientSegment) {
                $sourceSegment = DB::table('tariff_segment_prices')->where('service_id', $service['database_id'])
                    ->where('tariff_version_id', $tariff->id)->where('segment', $clientSegment)->first();
            }
        }
        $base = $tariff ? DB::table('service_prices')->where('tariff_version_id', $tariff->id)->where('service_id', $service['database_id'])->first() : null;
        $available = !$incomplete && $service['estimation_mode'] === 'automatic' && $base !== null;
        $min = $base ? (int)round($base->price_min * 100) : 0;
        $max = $base ? (int)round($base->price_max * 100) : 0;
        $items = [];
        foreach ($sourceSegment ? [] : $quantities as $id => $quantity) {
            if ($quantity <= 0 || !$components->has($id)) {
                continue;
            }
            $price = $tariff ? DB::table('component_prices')->where('tariff_version_id', $tariff->id)->where('service_component_id', $id)->first() : null;
            $available = $available && $price !== null;
            $items[] = ['component_id' => $id, 'name' => $components[$id]->name, 'slug' => $components[$id]->slug, 'unit' => $components[$id]->unit_code, 'quantity' => $quantity, 'min' => $price?->price_min, 'max' => $price?->price_max];
            $min += (int)round(($price?->price_min ?? 0) * 100 * $quantity);
            $max += (int)round(($price?->price_max ?? 0) * 100 * $quantity);
        }
        // Si hay un importe publicado para el segmento elegido, usa el valor externo exacto.
        if ($sourceSegment && $tariff && (int)$sourceSegment->tariff_version_id === (int)$tariff->id && $available) {
            $min = (int)round($sourceSegment->price_min * 100);
            $max = (int)round($sourceSegment->price_max * 100);
        }
        // Tarifario.org no da importes para más de cinco secciones ni para un segmento sin equivalencia.
        $hasSourceSegments = $tariff && DB::table('tariff_segment_prices')->where('tariff_version_id', $tariff->id)
            ->where('service_id', $service['database_id'])->exists();
        if ($hasSourceSegments && !$sourceSegment) $available = false;

        // Sin configuración completa se informa revisión profesional, nunca un precio ficticio.
        return ['key' => (string)Str::uuid(), 'service' => $service, 'answers' => $snapshot, 'items' => $items,
            'mode' => $available ? 'automatic' : 'manual', 'tariff_id' => $available ? $tariff->id : null,
            'currency' => $tariff?->currency_code ?? 'ARS', 'base_min' => $available ? $base->price_min : null,
            'base_max' => $available ? $base->price_max : null, 'min' => $available ? $min / 100 : null,
            'max' => $available ? $max / 100 : null, 'date' => now()->toDateTimeString()];
    }
}
