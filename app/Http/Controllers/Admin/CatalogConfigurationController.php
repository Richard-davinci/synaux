<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PreciosCatalogo;
use App\Services\Catalog\ServiceCatalog;
use App\Services\Catalog\TipoCliente;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Guarda servicios, categorías y las preguntas, componentes y reglas vinculados al catálogo.
class CatalogConfigurationController extends Controller
{
    // Solo Administración puede sustituir la selección completa que se muestra en Inicio.
    public function saveFeatured(Request $request, ServiceCatalog $catalog)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);

        $data = $request->validate([
            'service_ids' => ['present', 'array', 'list', 'max:' . ServiceCatalog::FEATURED_LIMIT],
            'service_ids.*' => ['integer', 'distinct'],
        ], [
            'service_ids.max' => 'Podés destacar hasta tres servicios.',
            'service_ids.*.distinct' => 'Elegí servicios distintos.',
        ]);

        $catalog->saveFeaturedServices(array_map('intval', $data['service_ids']));

        return back()->with('notice', 'Servicios destacados del Inicio actualizados.');
    }

    // Los vínculos históricos y de configuración impiden el borrado, incluso si están inactivos.
    public function eliminar(Request $request, string $resource, int $id)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);

        $esServicio = $resource === 'servicios';
        $tabla = $esServicio ? 'services' : 'service_categories';
        $relaciones = $esServicio ? [
            ['service_components', 'service_id', 'conceptos de estimación'],
            ['estimator_questions', 'service_id', 'preguntas'],
            ['option_services', 'service_id', 'opciones de estimación'],
            ['service_prices', 'service_id', 'tarifarios'],
            ['professional_services', 'service_id', 'perfiles profesionales'],
            ['portfolio_projects', 'service_id', 'trabajos de portfolio'],
            ['estimates', 'service_id', 'estimaciones'],
            ['project_requests', 'service_id', 'solicitudes'],
            ['budgets', 'direct_service_id', 'presupuestos'],
        ] : [['services', 'service_category_id', 'servicios']];

        try {
            DB::transaction(function () use ($tabla, $id, $relaciones, $esServicio) {
                $registro = DB::table($tabla)->where('id', $id);
                $registro->lockForUpdate()->firstOrFail();
                $vinculos = [];
                foreach ($relaciones as [$relacion, $columna, $nombre]) {
                    if (DB::table($relacion)->where($columna, $id)->exists()) {
                        $vinculos[] = $nombre;
                    }
                }
                if ($esServicio) {
                    // Estas dos listas guardan identificadores dentro de JSON y carecen de clave foránea.
                    $destacados = DB::table('site_home_settings')->where('id', 1)->value('featured_service_ids');
                    if (in_array($id, json_decode($destacados ?? '[]', true), true)) {
                        $vinculos[] = 'servicios destacados';
                    }
                    $solicitudes = DB::table('project_requests')->select('id', 'requested_services')
                        ->whereNotNull('requested_services')->where('requested_services', '!=', '[]')->lazyById();
                    foreach ($solicitudes as $solicitud) {
                        $idsAdicionales = array_column(json_decode($solicitud->requested_services, true) ?? [], 'service_id');
                        if (in_array($id, array_map('intval', $idsAdicionales), true)) {
                            $vinculos[] = 'solicitudes con este servicio adicional';
                            break;
                        }
                    }
                }
                if ($vinculos) {
                    throw ValidationException::withMessages([
                        'eliminar_catalogo' => 'El registro está vinculado a ' . implode(', ', $vinculos) . '.',
                    ]);
                }
                $registro->delete();
            }, 3);
        } catch (QueryException $error) {
            // La clave foránea es la última defensa ante un vínculo agregado durante el borrado.
            if ((int)($error->errorInfo[1] ?? 0) !== 1451
                && !str_contains($error->errorInfo[2] ?? '', 'FOREIGN KEY constraint failed')) {
                throw $error;
            }
            throw ValidationException::withMessages(['eliminar_catalogo' => 'El registro tiene datos asociados.']);
        }

        return back()->with('notice', $esServicio ? 'Servicio eliminado.' : 'Categoría eliminada.');
    }

    // Valida el recurso permitido y guarda sus cambios solo para una cuenta administrativa.
    public function save(Request $request, string $resource, PreciosCatalogo $catalogoPrecios)
    {
        abort_unless($request->user()->puedeAdministrarSynaux(), 403);
        $table = match ($resource) {
            'servicios' => 'services',
            'preguntas' => 'estimator_questions',
            'componentes' => 'service_components',
            'reglas' => 'estimation_rules',
            'categorias' => 'service_categories',
            default => abort(404),
        };
        $id = $request->validate(['id' => 'nullable|integer'])['id'] ?? null;
        $common = ['active' => 'required|boolean'];
        $rules = match ($resource) {
            'servicios' => [...$common, 'name' => 'required|string|max:255', 'service_category_id' => 'required|integer|exists:service_categories,id',
                'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('services', 'slug')->ignore($id)], 'description' => 'required|string|max:10000', 'summary' => 'required|string|max:1000',
                'estimation_mode' => ['required', Rule::in(['manual', 'automatic'])], 'deliverables' => 'required|string|max:10000'],
            'preguntas' => [...$common, 'service_id' => 'nullable|integer|exists:services,id', 'question' => 'required|string|max:1000',
                'input_type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'number', 'text'])], 'sort_order' => 'required|integer|min:0|max:1000', 'options_text' => 'nullable|string|max:5000',
                'quantity_component_id' => 'nullable|integer|exists:service_components,id', 'required' => 'sometimes|boolean'],
            'componentes' => [...$common, 'service_id' => 'required|integer|exists:services,id', 'name' => 'required|string|max:255', 'slug' => 'required|alpha_dash|max:150',
                'description' => 'required|string|max:10000', 'unit_code' => 'required|string|max:32', 'is_default' => 'required|boolean', 'default_quantity' => 'required|numeric|gt:0|max:10000'],
            'reglas' => [...$common, 'estimator_option_id' => 'required|integer|exists:estimator_options,id', 'service_component_id' => 'required|integer|exists:service_components,id', 'quantity' => 'required|numeric|min:0|max:10000'],
            'categorias' => [...$common, 'name' => 'required|string|max:255', 'slug' => ['required', 'alpha_dash', Rule::unique('service_categories', 'slug')->ignore($id)], 'description' => 'required|string|max:10000'],
        };
        $data = $request->validate($rules);
        // Cada rótulo de sistema viaja con su ID actual, independientemente de su posición o del texto anterior.
        $etiquetasSegmentos = $resource === 'preguntas' ? $request->validate([
            'etiquetas_segmentos' => 'sometimes|array|size:3',
            'etiquetas_segmentos.*' => 'required|string|max:255|distinct:ignore_case',
        ])['etiquetas_segmentos'] ?? null : null;
        // El alta puede guardar la ficha y sus precios juntos, o dejar la ficha sin publicar.
        $precios = $resource === 'servicios' && $data['estimation_mode'] === 'automatic' && $request->has('precios')
            ? $request->validate(PreciosCatalogo::reglas())['precios']
            : null;
        // El contexto del editor impide modificar por accidente registros de otro servicio.
        $context = $request->validate(['catalog_service_id' => 'nullable|integer|exists:services,id'])['catalog_service_id'] ?? null;
        if ($context && in_array($resource, ['preguntas', 'componentes'], true)) {
            $old = $id ? DB::table($table)->where('id', $id)->firstOrFail() : null;
            abort_if($old && $old->service_id !== null && (int)$old->service_id !== (int)$context, 422, 'El registro pertenece a otro servicio.');
            abort_if(($data['service_id'] ?? null) !== null && (int)$data['service_id'] !== (int)$context, 422, 'Elegí el servicio de este editor.');
        }
        if ($context && $resource === 'reglas') {
            $component = DB::table('service_components')->where('id', $data['service_component_id'])->firstOrFail();
            abort_unless((int)$component->service_id === (int)$context, 422, 'El componente pertenece a otro servicio.');
            if ($id) {
                $old = DB::table('estimation_rules as r')->join('service_components as c', 'c.id', '=', 'r.service_component_id')->where('r.id', $id)->select('c.service_id')->firstOrFail();
                abort_unless((int)$old->service_id === (int)$context, 422);
            }
        }
        $savedId = DB::transaction(function () use ($resource, $table, $id, $data, $precios, $catalogoPrecios, $etiquetasSegmentos) {
            if ($resource === 'servicios') $catalogoPrecios->bloquearVersiones();
            $anterior = null;
            if ($id) {
                $anterior = DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
                // Cambiar el alcance no debe romper cantidades ni retirar una pregunta de otros servicios.
                if ($resource === 'preguntas' && array_key_exists('service_id', $data)) {
                    if ($anterior->service_id === null && $data['service_id'] !== null) {
                        throw ValidationException::withMessages(['service_id' => 'Una pregunta general debe conservar su alcance.']);
                    }
                    if ($anterior->quantity_component_id !== null && $data['service_id'] === null) {
                        throw ValidationException::withMessages(['service_id' => 'Desvinculá el concepto de cantidad antes de convertirla en pregunta general.']);
                    }
                }
            }
            if ($resource === 'servicios') {
                // Registra la edición de la ficha comercial con la hora del servidor.
                $data['updated_at'] = now();
                $data['deliverables'] = json_encode(array_values(array_filter(array_map('trim', explode("\n", $data['deliverables'])))));
            }
            if ($resource === 'componentes') {
                $duplicate = DB::table($table)->where('service_id', $data['service_id'])->where('slug', $data['slug'])->when($id, fn($q) => $q->where('id', '!=', $id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['slug' => 'Este identificador ya existe para el servicio.']);
                }
            }
            if ($resource === 'reglas') {
                $option = DB::table('estimator_options as o')->join('estimator_questions as q', 'q.id', '=', 'o.question_id')->where('o.id', $data['estimator_option_id'])->select('q.service_id')->firstOrFail();
                $component = DB::table('service_components')->where('id', $data['service_component_id'])->firstOrFail();
                abort_if($option->service_id && $option->service_id !== $component->service_id, 422, 'La opción y el componente deben pertenecer al mismo servicio.');
                $duplicate = DB::table($table)->where('estimator_option_id', $data['estimator_option_id'])->where('service_component_id', $data['service_component_id'])->when($id, fn($q) => $q->where('id', '!=', $id))->exists();
                abort_if($duplicate, 422, 'Esta relación ya existe.');
            }
            $options = null;
            if ($resource === 'preguntas') {
                if ($anterior?->clave_sistema === TipoCliente::PREGUNTA) {
                    return $this->guardarPreguntaTipoCliente($anterior, $data, $etiquetasSegmentos);
                }
                if (!empty($data['required']) || !array_key_exists('required', $data)) $data['required'] = true;
                $options = $data['options_text'] ?? '';
                unset($data['options_text']);
                if (!empty($data['quantity_component_id'])) {
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
            if ($resource === 'servicios' && $data['estimation_mode'] === 'automatic') {
                if ($precios !== null) $catalogoPrecios->guardarServicio($id, $precios);
                $publicacionNueva = $data['active'] && (!$anterior?->active || $anterior->estimation_mode !== 'automatic');
                if ($publicacionNueva && !$catalogoPrecios->tieneCobertura($id)) {
                    throw ValidationException::withMessages(['active' => 'Completá los precios del servicio antes de publicarlo. Podés guardar la ficha sin publicar.']);
                }
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
            return $id;
        }, 3);

        $mensaje = 'Configuración guardada.';
        if ($resource === 'servicios' && $request->boolean('configure_after_save')) {
            $seccion = $data['estimation_mode'] === 'automatic' ? 'precios' : 'preguntas';
            return redirect("/admin/servicios/$savedId/editar?config=$seccion")->with('notice', $mensaje);
        }
        return back()->with('notice', $mensaje);
    }

    // La pregunta del sistema conserva su alcance, tipo, estado y opciones. Solo se editan los rótulos y el orden de la pregunta.
    private function guardarPreguntaTipoCliente(object $pregunta, array $datos, ?array $etiquetas): int
    {
        if (!$datos['active'] || !($datos['required'] ?? true) || $datos['input_type'] !== 'single_choice'
            || ($datos['service_id'] ?? null) !== null || ($datos['quantity_component_id'] ?? null) !== null) {
            throw ValidationException::withMessages(['question' => 'La pregunta de tipo de cliente debe seguir activa, obligatoria y disponible para todos los servicios.']);
        }

        $opciones = DB::table('estimator_options')->where('question_id', $pregunta->id)
            ->whereIn('clave_segmento', array_keys(TipoCliente::ETIQUETAS))->orderBy('sort_order')->lockForUpdate()->get();
        if ($etiquetas === null && array_key_exists('options_text', $datos)) {
            $textoActual = $opciones->pluck('label')->implode("\n");
            if (str_replace("\r\n", "\n", $datos['options_text'] ?? '') !== $textoActual) {
                throw ValidationException::withMessages(['options_text' => 'Editá cada rótulo desde su campo de tipo de cliente.']);
            }
        }
        if ($etiquetas !== null && (count($etiquetas) !== $opciones->count() || $opciones->pluck('id')->diff(array_keys($etiquetas))->isNotEmpty())) {
            throw ValidationException::withMessages(['etiquetas_segmentos' => 'Los textos deben corresponder a las opciones existentes de tipo de cliente.']);
        }
        DB::table('estimator_questions')->where('id', $pregunta->id)->update([
            'question' => $datos['question'], 'sort_order' => $datos['sort_order'],
        ]);
        foreach ($etiquetas ?? [] as $id => $texto) {
            DB::table('estimator_options')->where('question_id', $pregunta->id)->where('id', $id)->update(['label' => trim($texto)]);
        }

        return (int)$pregunta->id;
    }
}
