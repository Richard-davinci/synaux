<?php

namespace App\Http\Controllers;

use App\Services\SynauxWorkspace;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class SynauxBudgetPdfController extends Controller
{
    // La descarga aplica los mismos filtros de propiedad, emisor y estado que la vista del presupuesto.
    public function show(Request $request, SynauxWorkspace $workspace, int $id)
    {
        $quotes = $workspace->quotes($request->user()->id, (bool) $request->user()->is_admin);
        $budget = collect($quotes)->firstWhere('id', $id);
        abort_unless($budget, 404);

        // Solo se cargan los recursos propios del proyecto y todos los datos del cliente se escapan en Blade.
        $fontDirectory = storage_path('app/pdf-fonts');
        if (! is_dir($fontDirectory)) {
            mkdir($fontDirectory, 0755, true);
        }
        $options = new Options([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'chroot' => resource_path(), 'fontDir' => $fontDirectory, 'fontCache' => $fontDirectory,
        ]);
        $pdf = new Dompdf($options);
        $logo = 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(resource_path('images/brand/synaux-horizontal-sin-slogan-color.svg')));
        $pdf->loadHtml(view('budget-pdf', ['budget' => $budget, 'logo' => $logo])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Synaux-PRE-'.$budget['budget_id'].'-v'.$budget['version_number'].'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
