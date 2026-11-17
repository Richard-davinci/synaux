<?php

namespace App\Http\Controllers;

use App\Services\SynauxTariffImport;
use Illuminate\Validation\ValidationException;
use Throwable;

class SynauxTariffImportController extends Controller
{
    // El middleware administrativo autoriza el botón. El importador mantiene los importes anteriores ante cualquier fallo.
    public function store(SynauxTariffImport $import)
    {
        try {
            $result = $import->sync();
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['tarifario' => 'No se pudo actualizar el tarifario. Los precios anteriores siguen disponibles. Revisá el registro del servidor.']);
        }

        return back()->with('notice', $result['created'] ? 'Se importó una nueva versión desde Tarifario.ar.' : 'El tarifario ya coincide con la fuente consultada.');
    }
}
