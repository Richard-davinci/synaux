<?php

namespace App\Services\Tariffs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Importa una referencia pública de Tarifario.org y la publica como una versión histórica nueva.
class TariffImport
{
    public const SOURCE = 'https://tarifario.org/diseno-y-maquetacion-c43';

    public function sync(): array
    {
        $html = $this->read(self::SOURCE);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // La importación se limita a la fila institucional y valida sus tres segmentos por separado.
        $xpath = new \DOMXPath($document);
        $rates = null;
        foreach ($xpath->query('//tr') as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells->length < 4) continue;
            $label = trim(preg_replace('/\s+/u', ' ', $cells->item(0)->textContent));
            if (!str_starts_with($label, 'Desarrollo de Web Site Institucional ')) continue;

            $rates = [];
            foreach (['Empresa' => 1, 'PyME' => 2, 'Particular' => 3] as $segment => $index) {
                $digits = preg_replace('/\D+/', '', $cells->item($index)->textContent);
                if ($digits === '' || (int)$digits <= 0) {
                    throw new RuntimeException('Tarifario.org no publicó un importe válido para ' . $segment . '.');
                }
                $rates[$segment] = (int)$digits;
            }
            break;
        }
        if (!$rates || count($rates) !== 3) {
            throw new RuntimeException('No se encontró la fila institucional A/B/C con el formato esperado.');
        }
        if (!str_contains($html, 'Home y hasta 5 secciones. No incluye tareas de programación')) {
            throw new RuntimeException('Cambió el alcance del servicio en Tarifario.org. Revisalo antes de actualizar.');
        }
        preg_match('/(?:Última|Ultima) actualización:\s*([^<]+)/iu', $html, $updated);
        $sourceDate = trim($updated[1] ?? '');
        if ($sourceDate === '') throw new RuntimeException('Tarifario.org no informa la fecha de actualización de esta tarifa.');
        $fingerprint = hash('sha256', json_encode([$rates, $sourceDate], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($rates, $fingerprint, $sourceDate) {
            // El bloqueo del servicio serializa las actualizaciones y evita publicar duplicados simultáneos.
            $service = DB::table('services')->where('slug', 'sitio-institucional')->lockForUpdate()->firstOrFail();
            $latest = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')->orderByDesc('id')->first();
            if ($latest && $latest->currency_code !== 'ARS') {
                throw new RuntimeException('La versión activa usa otra moneda. Revisá la moneda antes de importar.');
            }
            if (!$latest) {
                throw new RuntimeException('Primero cargá una versión completa del tarifario para los demás servicios.');
            }
            if ($latest?->source_url === self::SOURCE && $latest->source_fingerprint === $fingerprint) {
                return ['created' => false, 'id' => $latest->id, 'sourceDate' => $sourceDate];
            }
            // No activa una referencia parcial si ya faltan precios ajenos al servicio importado.
            $otherAutomatic = DB::table('services')->where('active', 1)->where('estimation_mode', 'automatic')
                ->where('id', '!=', $service->id)->pluck('id');
            $availableOtherServices = DB::table('service_prices')->where('tariff_version_id', $latest->id)->pluck('service_id');
            $requiredComponents = DB::table('service_components as c')->join('services as s', 's.id', '=', 'c.service_id')
                ->where('s.active', 1)->where('s.estimation_mode', 'automatic')->where('c.active', 1)->pluck('c.id');
            $availableComponents = DB::table('component_prices')->where('tariff_version_id', $latest->id)->pluck('service_component_id');
            if ($otherAutomatic->diff($availableOtherServices)->isNotEmpty() || $requiredComponents->diff($availableComponents)->isNotEmpty()) {
                throw new RuntimeException('La versión vigente tiene precios pendientes. Completalos antes de publicar una importación parcial.');
            }
            $id = DB::table('tariff_versions')->insertGetId([
                'name' => 'Tarifario.org · Sitio institucional · Fuente ' . $sourceDate . ' · ' . now()->format('Y-m-d H:i'),
                'currency_code' => 'ARS', 'active' => true, 'effective_from' => now(), 'created_at' => now(),
                'source_url' => self::SOURCE, 'source_checked_at' => now(), 'source_fingerprint' => $fingerprint,
            ]);

            // Copia los importes de los demás servicios y conceptos de la versión activa anterior.
            foreach (['service_prices' => ['service_id', $service->id], 'component_prices' => ['service_component_id', null]] as $table => [$key, $replaced]) {
                $rows = $latest ? DB::table($table)->where('tariff_version_id', $latest->id)
                    ->when($replaced !== null, fn($query) => $query->where($key, '!=', $replaced))->get() : [];
                foreach ($rows as $row) {
                    DB::table($table)->insert(['tariff_version_id' => $id, $key => $row->$key, 'price_min' => $row->price_min, 'price_max' => $row->price_max]);
                }
            }
            $amounts = array_values($rates);
            DB::table('service_prices')->insert([
                'tariff_version_id' => $id, 'service_id' => $service->id,
                'price_min' => min($amounts), 'price_max' => max($amounts),
            ]);
            foreach ($rates as $segment => $amount) {
                DB::table('tariff_segment_prices')->insert([
                    'tariff_version_id' => $id, 'service_id' => $service->id,
                    'segment' => $segment, 'price_min' => $amount, 'price_max' => $amount,
                ]);
            }

            return ['created' => true, 'id' => $id, 'sourceDate' => $sourceDate];
        });
    }

    // La URL viene fijada en el código y se limitan el tiempo de espera y el tamaño de respuesta.
    private function read(string $url): string
    {
        $response = Http::connectTimeout(5)->timeout(15)->get($url)->throw();
        if (strlen($response->body()) > 3000000) {
            throw new RuntimeException('El recurso remoto supera el tamaño esperado.');
        }
        return $response->body();
    }
}
