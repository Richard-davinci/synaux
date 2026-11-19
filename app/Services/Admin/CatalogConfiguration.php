<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class CatalogConfiguration
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
}
