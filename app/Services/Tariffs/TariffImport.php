<?php

namespace App\Services\Tariffs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Lee datos públicos sin ejecutar JavaScript remoto y publica una nueva versión solo cuando cambia la referencia.
class TariffImport
{
    public const SOURCE = 'https://tarifario.ar/categoria/dev-web-app#service-pd-3';
    public const RATE = CotizacionCcl::ENDPOINT;

    public function sync(): array
    {
        $html = $this->read('https://tarifario.ar/');
        preg_match_all('~<script[^>]+src="(/_next/static/chunks/[^"]+\.js)"~', $html, $scripts);
        if (count($scripts[1]) > 30 || !$scripts[1]) {
            throw new RuntimeException('Cambió la estructura de Tarifario.ar. Revisá la integración.');
        }

        // Se acepta únicamente la ficha vinculada al alcance de hasta siete secciones.
        $card = null;
        foreach (array_unique($scripts[1]) as $path) {
            $script = $this->read('https://tarifario.ar' . $path);
            if (preg_match('~\{id:"pd-3",[^{}]+\}~u', $script, $match)) {
                $card = $match[0];
                break;
            }
        }
        if (!$card || !str_contains($card, 'category:"dev-web-app"') || !str_contains($card, 'priceType:"minimum"')) {
            throw new RuntimeException('No se encontró la ficha institucional con el formato esperado.');
        }
        preg_match('~description:("[^"]*")~u', $card, $description);
        $scope = json_decode($description[1] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
        if ($scope !== 'Sitio (hasta 7 secciones). UI/UX. Form. Blog básico. Responsive. CMS (WP) básico. SEO on-page inicial. 2 rondas.') {
            throw new RuntimeException('Cambió el alcance de la ficha. Revisalo antes de actualizar sus precios.');
        }
        preg_match('~priceUSD:([0-9]+(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?)(?:,|\})~i', $card, $price);
        $usd = (float)($price[1] ?? 0);
        $rateData = Http::connectTimeout(5)->timeout(15)->get(self::RATE)->throw()->json();
        $rate = $rateData['venta'] ?? null;
        if ($usd <= 0 || $usd > 1000000 || !is_numeric($rate) || $rate <= 0 || $rate > 1000000
            || ($rateData['casa'] ?? '') !== 'contadoconliqui') {
            throw new RuntimeException('El precio o la cotización recibida no son válidos.');
        }
        $checked = \Carbon\Carbon::parse($rateData['fechaActualizacion'] ?? throw new RuntimeException('La cotización no informa fecha.'));
        if ($checked->lt(now()->subDays(7)) || $checked->gt(now()->addHour())) {
            throw new RuntimeException('La cotización recibida está fuera de fecha.');
        }
        $rate = (float)$rate;
        $amount = (int)round($usd * $rate * 0.5);
        // El esquema de componentes representa incrementos iguales entre los tres segmentos.
        if ((int)round($usd * $rate) !== 2 * $amount || (int)round($usd * $rate * 1.5) !== 3 * $amount) {
            throw new RuntimeException('Los redondeos requieren revisar el esquema de segmentos antes de importar.');
        }
        $fingerprint = hash('sha256', json_encode([$scope, $usd, $rate]));

        return DB::transaction(function () use ($usd, $rate, $amount, $fingerprint) {
            // El bloqueo del servicio serializa la publicación de dos actualizaciones concurrentes.
            $service = DB::table('services')->where('slug', 'sitio-institucional')->lockForUpdate()->firstOrFail();
            $component = DB::table('service_components')->where('service_id', $service->id)
                ->where('slug', 'segmento-tarifario-2026-11')->where('active', 1)->first();
            if (!$component || DB::table('service_components')->where('service_id', $service->id)->where('active', 1)->count() !== 1) {
                throw new RuntimeException('Guardá el servicio institucional para preparar su configuración y dejá activo un solo componente tarifario.');
            }
            $existing = DB::table('tariff_versions')->where('source_fingerprint', $fingerprint)->first();
            if ($existing) {
                return ['created' => false, 'id' => $existing->id, 'pyme' => $amount * 2];
            }
            $latest = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')->orderByDesc('id')->first();
            $id = DB::table('tariff_versions')->insertGetId([
                'name' => 'Tarifario.ar · Institucional · ' . now()->format('Y-m-d H:i'),
                'currency_code' => 'ARS', 'active' => true, 'effective_from' => now(), 'created_at' => now(),
                'source_url' => self::SOURCE, 'source_usd' => $usd, 'source_rate' => $rate,
                'source_checked_at' => now(), 'source_fingerprint' => $fingerprint,
            ]);
            // Conserva los precios de otros servicios cuando la versión anterior utiliza la misma moneda.
            foreach (['service_prices' => ['service_id', $service->id], 'component_prices' => ['service_component_id', $component->id]] as $table => [$key, $replaced]) {
                if ($latest && $latest->currency_code !== 'ARS') {
                    throw new RuntimeException('La versión activa usa otra moneda. Revisá la conversión antes de importar.');
                }
                $rows = $latest ? DB::table($table)->where('tariff_version_id', $latest->id)->where($key, '!=', $replaced)->get() : [];
                foreach ($rows as $row) {
                    DB::table($table)->insert(['tariff_version_id' => $id, $key => $row->$key, 'price_min' => $row->price_min, 'price_max' => $row->price_max]);
                }
                DB::table($table)->insert(['tariff_version_id' => $id, $key => $replaced, 'price_min' => $amount, 'price_max' => $amount]);
            }
            return ['created' => true, 'id' => $id, 'pyme' => $amount * 2];
        });
    }

    // Los enlaces provienen exclusivamente del dominio fijo y las solicitudes tienen límites de espera.
    private function read(string $url): string
    {
        $response = Http::connectTimeout(5)->timeout(15)->get($url)->throw();
        if (strlen($response->body()) > 3000000) {
            throw new RuntimeException('El recurso remoto supera el tamaño esperado.');
        }
        return $response->body();
    }
}
