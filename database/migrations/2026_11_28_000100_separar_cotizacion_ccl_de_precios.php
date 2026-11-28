<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // La cotización aplicada tiene su propio registro; el catálogo mantiene las versiones de importes.
        Schema::create('cotizacion_ccl', function (Blueprint $tabla) {
            $tabla->unsignedTinyInteger('id')->primary();
            $tabla->decimal('valor', 15, 4)->nullable();
            $tabla->string('fuente', 20)->nullable();
            $tabla->timestamp('fecha_fuente')->nullable();
            $tabla->timestamp('aplicada_en')->nullable();
        });
        Schema::table('estimates', function (Blueprint $tabla) {
            $tabla->decimal('ccl_rate_snapshot', 15, 4)->nullable();
            $tabla->string('ccl_source_snapshot', 20)->nullable();
        });

        $vigente = DB::table('tariff_versions')->where('active', 1)->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
        $valor = $vigente?->ccl_rate ?? $vigente?->source_rate;
        DB::table('cotizacion_ccl')->insert([
            'id' => 1,
            'valor' => $valor,
            'fuente' => $valor !== null ? 'registrada' : null,
            'aplicada_en' => $valor !== null ? ($vigente->ccl_rate_at ?? $vigente->source_checked_at ?? $vigente->effective_from) : null,
        ]);

        // Solo recupera el valor asociado a la versión que usó cada estimación anterior.
        DB::table('estimates')->whereNotNull('tariff_version_id')->update([
            'ccl_rate_snapshot' => DB::table('tariff_versions')->select('ccl_rate')
                ->whereColumn('tariff_versions.id', 'estimates.tariff_version_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('estimates', fn (Blueprint $tabla) => $tabla->dropColumn(['ccl_rate_snapshot', 'ccl_source_snapshot']));
        Schema::dropIfExists('cotizacion_ccl');
    }
};
