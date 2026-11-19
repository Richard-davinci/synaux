<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfessionalProfileController extends Controller
{
    // Actualiza el perfil profesional de la propia cuenta y normaliza su lista de habilidades.
    public function update(Request $request)
    {
        $d = $request->validate(['brand_name' => 'required|string|max:255', 'headline' => 'required|string|max:255', 'bio' => 'required|string|max:5000',
            'skills' => 'nullable|string|max:1000', 'location' => 'nullable|string|max:255', 'availability_status' => ['required', Rule::in(['available', 'busy', 'unavailable'])], 'active' => 'required|boolean']);
        $d['skills'] = json_encode($this->tags($d['skills'] ?? ''));
        DB::table('professional_profiles')->updateOrInsert(['user_id' => $request->user()->id], [...$d, 'updated_at' => now()]);

        return redirect('/profesional/perfil')->with('notice', 'Perfil profesional actualizado.');
    }

    // Convierte un texto separado por comas en una lista sin elementos vacíos ni repetidos.
    private function tags(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $text)))));
    }
}
