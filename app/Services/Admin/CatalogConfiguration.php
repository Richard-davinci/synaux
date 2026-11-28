<?php

namespace App\Services\Admin;

use App\Services\Catalog\ServiceCatalog;
use App\Services\Tariffs\CotizacionCcl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CatalogConfiguration
{
    public function __construct(private ServiceCatalog $catalog, private PreciosCatalogo $precios, private CotizacionCcl $cotizacion)
    {
    }

    // Consulta la configuración del catálogo y sus relaciones para las pantallas administrativas.
    public function data(): array
    {
        $vigente = $this->precios->vigente();

        return [
            'featuredServiceIds' => array_column($this->catalog->featuredServices(), 'database_id'),
            'featuredServiceLimit' => ServiceCatalog::FEATURED_LIMIT,
            'categories' => DB::table('service_categories')->get(),
            // La fecha incluye la zona horaria para verse igual desde distintos navegadores.
            'services' => DB::table('services')->get()->map(fn($s) => [
                ...((array)$s),
                'deliverables' => json_decode($s->deliverables ?? '[]', true),
                'updated_at' => $s->updated_at ? Carbon::parse($s->updated_at, 'UTC')->toISOString() : null,
            ]),
            'components' => DB::table('service_components')->get(),
            'questions' => DB::table('estimator_questions')->orderBy('sort_order')->get()->map(fn($q) => [...((array)$q), 'options' => DB::table('estimator_options')->where('question_id', $q->id)->orderBy('sort_order')->get()]),
            // Tarifarios y servicios reutilizan las opciones de la pregunta global de tipo de cliente.
            'clientSegments' => $this->precios->segmentos(),
            'currentCcl' => $this->cotizacion->vigente($vigente),
            'rules' => DB::table('estimation_rules as r')->join('estimator_options as o', 'o.id', '=', 'r.estimator_option_id')->join('service_components as c', 'c.id', '=', 'r.service_component_id')->select('r.*', 'o.label', 'c.name')->get(),
            // La ficha recibe los precios publicados para editar; las versiones anteriores quedan en el servidor.
            'currentTariff' => $vigente ? [
                ...((array) $vigente),
                'services' => DB::table('service_prices')->where('tariff_version_id', $vigente->id)->get(),
                'components' => DB::table('component_prices')->where('tariff_version_id', $vigente->id)->get(),
                'segments' => DB::table('service_segment_prices')->where('tariff_version_id', $vigente->id)->get(),
            ] : null,
        ];
    }
}
