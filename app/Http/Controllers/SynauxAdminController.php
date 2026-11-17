<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Administración del motor de estimación: solo admite recursos y campos enumerados explícitamente.
class SynauxAdminController extends Controller
{
    // Consulta la configuración del catálogo y sus relaciones para las pantallas administrativas.
    public function data(): array
    {
        return [
            'categories' => DB::table('service_categories')->get(),
            'services' => DB::table('services')->get()->map(fn ($s) => [...((array) $s), 'deliverables' => json_decode($s->deliverables ?? '[]', true)]),
            'components' => DB::table('service_components')->get(),
            'questions' => DB::table('estimator_questions')->orderBy('sort_order')->get()->map(fn ($q) => [...((array) $q), 'options' => DB::table('estimator_options')->where('question_id', $q->id)->orderBy('sort_order')->get()]),
            'rules' => DB::table('estimation_rules as r')->join('estimator_options as o', 'o.id', '=', 'r.estimator_option_id')->join('service_components as c', 'c.id', '=', 'r.service_component_id')->select('r.*', 'o.label', 'c.name')->get(),
            'tariffs' => DB::table('tariff_versions')->orderByDesc('id')->get()->map(fn ($t) => [...((array) $t),
                'services' => DB::table('service_prices')->where('tariff_version_id', $t->id)->get(), 'components' => DB::table('component_prices')->where('tariff_version_id', $t->id)->get()]),
        ];
    }

    // Valida el recurso permitido y guarda sus cambios solo para una cuenta administrativa.
    public function save(Request $request, string $resource)
    {
        abort_unless($request->user()->is_admin, 403);
        $table = match ($resource) {
            'servicios' => 'services', 'preguntas' => 'estimator_questions', 'componentes' => 'service_components', 'reglas' => 'estimation_rules', 'categorias' => 'service_categories', 'tarifario' => 'tariff_versions', default => abort(404),
        };
        if ($resource === 'tarifario') {
            return $this->tariff($request);
        }
        $id = $request->validate(['id' => 'nullable|integer'])['id'] ?? null;
        $common = ['active' => 'required|boolean'];
        $rules = match ($resource) {
            'servicios' => [...$common, 'name' => 'required|string|max:255', 'service_category_id' => 'required|integer|exists:service_categories,id',
                'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('services', 'slug')->ignore($id)], 'description' => 'required|string|max:10000', 'summary' => 'required|string|max:1000',
                'estimation_mode' => ['required', Rule::in(['manual', 'automatic'])], 'deliverables' => 'required|string|max:10000'],
            'preguntas' => [...$common, 'service_id' => 'nullable|integer|exists:services,id', 'question' => 'required|string|max:1000',
                'input_type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'number', 'text'])], 'sort_order' => 'required|integer|min:0|max:1000', 'options_text' => 'nullable|string|max:5000',
                'quantity_component_id' => 'nullable|integer|exists:service_components,id'],
            'componentes' => [...$common, 'service_id' => 'required|integer|exists:services,id', 'name' => 'required|string|max:255', 'slug' => 'required|alpha_dash|max:150',
                'description' => 'required|string|max:10000', 'unit_code' => 'required|string|max:32', 'is_default' => 'required|boolean', 'default_quantity' => 'required|numeric|gt:0|max:10000'],
            'reglas' => [...$common, 'estimator_option_id' => 'required|integer|exists:estimator_options,id', 'service_component_id' => 'required|integer|exists:service_components,id', 'quantity' => 'required|numeric|min:0|max:10000'],
            'categorias' => [...$common, 'name' => 'required|string|max:255', 'slug' => ['required', 'alpha_dash', Rule::unique('service_categories', 'slug')->ignore($id)], 'description' => 'required|string|max:10000'],
        };
        $data = $request->validate($rules);
        DB::transaction(function () use ($resource, $table, $id, $data) {
            if ($id) {
                DB::table($table)->where('id', $id)->firstOrFail();
            }
            if ($resource === 'servicios') {
                $data['deliverables'] = json_encode(array_values(array_filter(array_map('trim', explode("\n", $data['deliverables'])))));
            }
            if ($resource === 'componentes') {
                $duplicate = DB::table($table)->where('service_id', $data['service_id'])->where('slug', $data['slug'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['slug' => 'Este identificador ya existe para el servicio.']);
                }
            }
            if ($resource === 'reglas') {
                $option = DB::table('estimator_options as o')->join('estimator_questions as q', 'q.id', '=', 'o.question_id')->where('o.id', $data['estimator_option_id'])->select('q.service_id')->firstOrFail();
                $component = DB::table('service_components')->where('id', $data['service_component_id'])->firstOrFail();
                abort_if($option->service_id && $option->service_id !== $component->service_id, 422, 'La opción y el componente deben pertenecer al mismo servicio.');
                $duplicate = DB::table($table)->where('estimator_option_id', $data['estimator_option_id'])->where('service_component_id', $data['service_component_id'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
                abort_if($duplicate, 422, 'Esta relación ya existe.');
            }
            $options = null;
            if ($resource === 'preguntas') {
                $options = $data['options_text'] ?? '';
                unset($data['options_text']);
                if (! empty($data['quantity_component_id'])) {
                    $component = DB::table('service_components')->where('id', $data['quantity_component_id'])->firstOrFail();
                    abort_unless($data['input_type'] === 'number' && $component->service_id === $data['service_id'], 422);
                }
                if (in_array($data['input_type'], ['single_choice', 'multiple_choice'], true) && trim($options) === '') {
                    throw ValidationException::withMessages(['options_text' => 'Agregá por lo menos una opción por línea.']);
                }
            }
            if ($id) {
                DB::table($table)->where('id', $id)->update($data);
            } else {
                $id = DB::table($table)->insertGetId($data);
            }
            if ($options !== null) {
                // Desactiva opciones anteriores y reutiliza sus identificadores; no borra respuestas históricas.
                DB::table('estimator_options')->where('question_id', $id)->update(['active' => 0]);
                foreach (array_filter(array_map('trim', explode("\n", $options))) as $order => $label) {
                    $old = DB::table('estimator_options')->where('question_id', $id)->where('label', $label)->first();
                    if ($old) {
                        DB::table('estimator_options')->where('id', $old->id)->update(['active' => 1, 'sort_order' => $order]);
                    } else {
                        DB::table('estimator_options')->insert(['question_id' => $id, 'label' => $label, 'sort_order' => $order, 'active' => 1]);
                    }
                }
            }
        });

        return back()->with('notice', 'Configuración guardada.');
    }

    // Valida rangos y cobertura antes de crear una versión nueva e independiente del tarifario.
    private function tariff(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])], 'active' => 'required|boolean',
            'services' => 'present|array|max:100', 'services.*.id' => 'required|integer|distinct|exists:services,id',
            'services.*.min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'services.*.max' => 'required|numeric|decimal:0,2|min:0|max:100000000',
            'components' => 'present|array|max:1000', 'components.*.id' => 'required|integer|distinct|exists:service_components,id',
            'components.*.min' => 'required|numeric|decimal:0,2|min:0|max:100000000', 'components.*.max' => 'required|numeric|decimal:0,2|min:0|max:100000000']);
        foreach (['services', 'components'] as $type) {
            foreach ($data[$type] as $index => $price) {
                if ($price['max'] < $price['min']) {
                    throw ValidationException::withMessages(["$type.$index.max" => 'El máximo debe ser mayor o igual al mínimo.']);
                }
            }
        }
        if ($data['active']) {
            $automatic = DB::table('services')->where('active', 1)->where('estimation_mode', 'automatic')->pluck('id');
            $required = DB::table('service_components')->whereIn('service_id', $automatic)->where('active', 1)->pluck('id');
            if ($automatic->diff(array_column($data['services'], 'id'))->isNotEmpty() || $required->diff(array_column($data['components'], 'id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['active' => 'Completá la base y todos los componentes de los servicios automáticos antes de activar.']);
            }
        }
        DB::transaction(function () use ($data) {
            // Cada publicación crea una versión; no se editan los valores que originaron cálculos anteriores.
            $id = DB::table('tariff_versions')->insertGetId(['name' => $data['name'], 'currency_code' => $data['currency_code'], 'effective_from' => now(), 'active' => $data['active'], 'created_at' => now()]);
            foreach (['services' => ['service_prices', 'service_id'], 'components' => ['component_prices', 'service_component_id']] as $type => [$table, $key]) {
                foreach ($data[$type] as $price) {
                    DB::table($table)->insert(['tariff_version_id' => $id, $key => $price['id'], 'price_min' => $price['min'], 'price_max' => $price['max']]);
                }
            }
        });

        return back()->with('notice', 'Nueva versión del tarifario guardada.');
    }
}
