<?php

namespace App\Services\Catalog;

use App\Services\Tariffs\CotizacionCcl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Centraliza catálogo, preguntas y cálculo para que el resultado público y el historial coincidan.
class ServiceCatalog
{
    public const FEATURED_LIMIT = 3;

    public function __construct(private CotizacionCcl $cotizacion)
    {
    }

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
        // Una referencia histórica conserva el valor de su versión y nunca toma el CCL actual.
        $ccl = in_array($tariff->currency_code, ['USD', 'ARS'], true)
            ? ($tariffId === null ? $this->cotizacion->vigente($tariff) : ['valor' => $tariff->ccl_rate ?? $tariff->source_rate])
            : null;
        $bases = DB::table('service_prices')->where('tariff_version_id', $tariff->id)->get()->keyBy('service_id');
        $prices = DB::table('component_prices')->where('tariff_version_id', $tariff->id)->get()->keyBy('service_component_id');
        $segmentPrices = DB::table('service_segment_prices as p')
            ->join('estimator_options as o', 'o.id', '=', 'p.estimator_option_id')
            ->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
            ->where('p.tariff_version_id', $tariff->id)->orderBy('o.sort_order')
            ->select('p.*', 'o.label', 'o.clave_segmento', 'o.active as opcion_activa', 'q.active as pregunta_activa', 'q.clave_sistema')
            ->get()->groupBy('service_id');
        $components = DB::table('service_components')->where('active', 1)->get()->groupBy('service_id');
        $references = [];
        foreach (($services ?? $this->services()) as $service) {
            // Un servicio que hoy se cotiza a medida conserva sus versiones para pedidos previos.
            if ($tariffId === null && $service['estimation_mode'] !== 'automatic') continue;

            $base = $bases->get($service['database_id']);
            $parts = $components->get($service['database_id'], collect());
            $segmentRows = $segmentPrices->get($service['database_id'], collect());
            $configuredSegments = $segmentRows->filter(fn($row) => $row->clave_sistema === TipoCliente::PREGUNTA
                && isset(TipoCliente::ETIQUETAS[$row->clave_segmento]) && ($tariffId !== null || ($row->opcion_activa && $row->pregunta_activa)));
            $partsForPrice = $segmentRows->isNotEmpty()
                ? $parts->reject(fn($part) => $part->slug === TipoCliente::CONCEPTO_HEREDADO)
                : $parts;
            // Un adicional opcional sin importe se cotiza si se elige. Los incluidos sí afectan la referencia pública.
            $incluidos = $partsForPrice->where('is_default', 1);
            if (!$base || $incluidos->contains(fn($part) => !$prices->has($part->id))) {
                continue;
            }
            // Un precio base técnico o variantes deshabilitadas no se publican como una oferta de cero.
            if ($configuredSegments->isEmpty() && ($segmentRows->isNotEmpty() || $this->baseTecnica($base))) continue;
            // Si hay variantes, el importe del segmento reemplaza la base. Los adicionales siguen sumándose.
            $min = $configuredSegments->isEmpty() ? (float)$base->price_min : 0.0;
            $max = $configuredSegments->isEmpty() ? (float)$base->price_max : 0.0;
            foreach ($incluidos as $part) {
                $cantidad = $this->modoCobro($part)->unidades((float)$part->default_quantity, [], 0);
                $min += round($prices[$part->id]->price_min * $cantidad, 2);
                $max += round($prices[$part->id]->price_max * $cantidad, 2);
            }
            // Cada variante tiene su propio importe; las reglas antiguas solo sirven como respaldo histórico.
            $segments = $configuredSegments->map(fn($price) => [
                'key' => $price->clave_segmento,
                'label' => $price->label,
                'min' => round((float)$price->price_min + $min, 2),
                'max' => round((float)$price->price_max + $max, 2),
            ])->values()->all();
            if (!$segments) {
                $segmentPart = $parts->firstWhere('slug', TipoCliente::CONCEPTO_HEREDADO);
                if ($segmentPart && !$prices->has($segmentPart->id)) continue;
                if ($segmentPart && $prices->has($segmentPart->id)) {
                    $options = DB::table('estimator_options as o')
                        ->join('estimator_questions as q', 'q.id', '=', 'o.question_id')
                        ->join('estimation_rules as r', 'r.estimator_option_id', '=', 'o.id')
                        ->where('q.active', 1)->whereIn('o.clave_segmento', array_keys(TipoCliente::ETIQUETAS))
                        ->where('o.active', 1)->where('r.active', 1)->where('r.service_component_id', $segmentPart->id)
                        ->orderByDesc('q.clave_sistema')->orderBy('o.sort_order')->select('o.clave_segmento', 'o.label', 'r.quantity')
                        ->get()->unique('clave_segmento');
                    foreach ($options as $option) {
                        $segments[] = [
                            'key' => $option->clave_segmento,
                            'label' => $option->label,
                            'min' => round($min + $prices[$segmentPart->id]->price_min * $option->quantity, 2),
                            'max' => round($max + $prices[$segmentPart->id]->price_max * $option->quantity, 2),
                        ];
                    }
                    if (!$segments) continue;
                }
            }
            $referencia = collect($segments)->firstWhere('key', 'particular') ?? ($segments[0] ?? null);
            $references[$service['id']] = [
                'min' => $referencia['min'] ?? round($min, 2), 'max' => $referencia['max'] ?? round($max, 2), 'currency' => $tariff->currency_code,
                'ccl_rate' => $ccl['valor'] ?? null, 'ccl_source' => $ccl['fuente'] ?? null,
                'date' => $tariff->effective_from, 'segments' => $segments,
                'segment' => $referencia['label'] ?? null,
                'segmentos_heredados' => $segmentRows->isEmpty() && $referencia !== null,
            ];
        }

        return $references;
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
        $opcionGlobal = null;
        $opcionHeredada = null;
        $cantidadesIngresadas = [];
        $incomplete = false;
        // Revalida todas las respuestas en el servidor; no confía en rótulos ni importes del navegador.
        foreach ($questions as $q) {
            $value = $answers[$q['id']] ?? null;
            $values = $q['input_type'] === 'multiple_choice' ? (array)$value : [$value];
            if ($value === null || $value === '' || $values === []) {
                // Si falta el tipo de cliente, un servicio con variantes queda a revisión.
                if (($q['clave_sistema'] ?? null) === TipoCliente::PREGUNTA) continue;
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
            // Repetir el mismo ID en una selección múltiple no representa una segunda elección.
            if ($q['input_type'] === 'multiple_choice') $value = $values = array_values(array_unique($values, SORT_STRING));
            $labels = [];
            foreach ($values as $v) {
                if (in_array($q['input_type'], ['single_choice', 'multiple_choice'], true)) {
                    $option = collect($q['options'])->first(fn($o) => (string)$o->id === (string)$v);
                    if (!$option) {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Elegí una opción válida.']);
                    }
                    $selected[] = $option->id;
                    $labels[] = $option->label;
                    if (isset(TipoCliente::ETIQUETAS[$option->clave_segmento ?? ''])) {
                        if (($q['clave_sistema'] ?? null) === TipoCliente::PREGUNTA) $opcionGlobal = $option->id;
                        else $opcionHeredada = $option->id;
                    }
                } elseif ($q['input_type'] === 'number') {
                    if (!is_numeric($v) || $v <= 0 || $v > 10000) {
                        throw ValidationException::withMessages(["answers.{$q['id']}" => 'Ingresá una cantidad entre 1 y 10000.']);
                    }
                    if ($q['quantity_component_id']) {
                        $cantidadesIngresadas[$q['quantity_component_id']][] = (float)$v;
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
        $tariff = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->first();
        $base = $tariff ? DB::table('service_prices')->where('tariff_version_id', $tariff->id)->where('service_id', $service['database_id'])->first() : null;
        // La presencia de variantes obliga a usar el ID de la opción del sistema, incluso si falta su precio.
        $preciosSegmento = $tariff ? DB::table('service_segment_prices')->where('tariff_version_id', $tariff->id)
            ->where('service_id', $service['database_id'])->get()->keyBy('estimator_option_id') : collect();
        $segmentPrice = $preciosSegmento->get($opcionGlobal);

        $components = DB::table('service_components')->where('service_id', $service['database_id'])->where('active', 1)
            // El concepto de segmentos solo se usa para tarifarios heredados; las nuevas versiones guardan importes directos.
            ->when($preciosSegmento->isNotEmpty(), fn($query) => $query->where('slug', '!=', TipoCliente::CONCEPTO_HEREDADO))
            ->get()->keyBy('id');
        $rules = DB::table('estimation_rules')->whereIn('estimator_option_id', $selected)->where('active', 1)->get();
        // Una tarifa antigua puede tener reglas locales y generales. Solo se aplica una al concepto de segmento.
        $componenteHeredado = $components->firstWhere('slug', TipoCliente::CONCEPTO_HEREDADO);
        $reglaHeredada = $componenteHeredado ? $rules->where('service_component_id', $componenteHeredado->id)
            ->firstWhere('estimator_option_id', $opcionHeredada ?? $opcionGlobal) : null;
        // Agrupa unidades aportadas por respuestas distintas. El modo del concepto decide cómo se combinan.
        $unidadesPorRegla = [];
        foreach ($rules as $rule) {
            if ($rule->service_component_id === $componenteHeredado?->id && $rule->id !== $reglaHeredada?->id) continue;
            if ($components->has($rule->service_component_id)) {
                $unidadesPorRegla[$rule->service_component_id] = ($unidadesPorRegla[$rule->service_component_id] ?? 0) + (float)$rule->quantity;
            }
        }
        $tarifaAplicada = $service['estimation_mode'] === 'automatic' ? $tariff : null;
        $baseDisponible = $tarifaAplicada !== null && $base !== null;
        if ($preciosSegmento->isNotEmpty() || ($base && $this->baseTecnica($base))) {
            $baseDisponible = $baseDisponible && $segmentPrice !== null;
        }
        if ($componenteHeredado) $baseDisponible = $baseDisponible && $reglaHeredada !== null;
        $baseMin = $baseDisponible ? ($segmentPrice?->price_min ?? $base->price_min) : null;
        $baseMax = $baseDisponible ? ($segmentPrice?->price_max ?? $base->price_max) : null;
        $precios = $tarifaAplicada ? DB::table('component_prices')->where('tariff_version_id', $tarifaAplicada->id)
            ->whereIn('service_component_id', $components->keys())->get()->keyBy('service_component_id') : collect();
        $available = !$incomplete && $baseDisponible;
        $items = [];
        foreach ($components as $id => $concepto) {
            $modo = $this->modoCobro($concepto);
            $quantity = $modo->unidades($concepto->is_default ? (float)$concepto->default_quantity : 0,
                $cantidadesIngresadas[$id] ?? [], $unidadesPorRegla[$id] ?? 0);
            if ($quantity <= 0) continue;

            $price = $precios->get($id);
            $available = $available && $price !== null;
            $items[] = ['component_id' => $id, 'name' => $concepto->name, 'slug' => $concepto->slug,
                'unit' => $concepto->unit_code, 'modo_cobro' => $modo->value, 'quantity' => $quantity,
                'min' => $price?->price_min, 'max' => $price?->price_max,
                'subtotal_min' => $price ? round($price->price_min * $quantity, 2) : null,
                'subtotal_max' => $price ? round($price->price_max * $quantity, 2) : null];
        }

        // Una revisión conserva lo conocido y su procedencia, aunque todavía no exista un total estimable.
        $subtotal = EconomiaEstimada::subtotal($baseMin, $baseMax, $items);
        $ccl = in_array($tarifaAplicada?->currency_code, ['USD', 'ARS'], true) ? $this->cotizacion->vigente($tarifaAplicada) : null;
        return ['key' => (string)Str::uuid(), 'service' => $service, 'answers' => $snapshot, 'items' => $items,
            'mode' => $available ? 'automatic' : 'manual', 'tariff_id' => $tarifaAplicada?->id, 'incomplete' => $incomplete,
            'currency' => $tariff?->currency_code ?? 'ARS', 'ccl_rate' => $ccl['valor'] ?? null,
            'ccl_source' => $ccl['fuente'] ?? null,
            'base_min' => $baseMin, 'base_max' => $baseMax, ...$subtotal,
            'min' => $available ? $subtotal['subtotal_min'] : null, 'max' => $available ? $subtotal['subtotal_max'] : null,
            'date' => now()->toDateTimeString()];
    }

    // El concepto tarifario antiguo y las configuraciones sin modalidad conservan la suma anterior.
    private function modoCobro(object $concepto): ModoCobro
    {
        return $concepto->slug === TipoCliente::CONCEPTO_HEREDADO
            ? ModoCobro::Heredado : (ModoCobro::tryFrom($concepto->modo_cobro ?? '') ?? ModoCobro::Heredado);
    }

    // Los importes comerciales de las variantes viven en su tabla. Esta fila común es solo un respaldo técnico.
    private function baseTecnica(object $precio): bool
    {
        return (float)$precio->price_min === 0.0 && (float)$precio->price_max === 0.0;
    }
}
