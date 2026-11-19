<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    // Permite una propuesta de un perfil activo ante una oportunidad abierta o una invitación aceptada.
    public function store(Request $request)
    {
        $d = $request->validate(['request_id' => 'required|integer', 'message' => 'required|string|min:10|max:10000',
            'estimated_delivery_days' => 'required|integer|min:1|max:3650', 'amount' => 'nullable|numeric|min:0|max:100000000', 'currency_code' => ['required', Rule::in(['ARS', 'USD', 'EUR'])]]);
        $profile = DB::table('professional_profiles')->where('user_id', $request->user()->id)->where('active', 1)->firstOrFail();
        $r = DB::table('project_requests')->where('id', $d['request_id'])->firstOrFail();
        $invited = DB::table('project_request_invitations')->where('project_request_id', $r->id)->where('professional_profile_id', $profile->id)->where('status', 'accepted')->exists();
        abort_unless($r->status === 'open' && ($r->visibility === 'public' || $invited) && $r->user_id !== $request->user()->id, 403);
        $existing = DB::table('proposals')->where('project_request_id', $r->id)->where('professional_profile_id', $profile->id)->first();
        abort_if($existing, 422, 'Ya presentaste una propuesta para esta solicitud.');
        DB::table('proposals')->insert(['project_request_id' => $r->id, 'professional_profile_id' => $profile->id, 'message' => $d['message'],
            'estimated_delivery_days' => $d['estimated_delivery_days'], 'amount' => $d['amount'] ?? null, 'currency_code' => $d['currency_code'], 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);

        return redirect('/profesional/propuestas')->with('notice', 'Propuesta presentada.');
    }

    // El cliente elige un profesional para presupuestar; esta selección todavía no crea un proyecto.
    public function select(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer']);
        DB::transaction(function () use ($request, $d) {
            $p = DB::table('proposals')->where('id', $d['id'])->lockForUpdate()->firstOrFail();
            $r = DB::table('project_requests')->where('id', $p->project_request_id)->lockForUpdate()->firstOrFail();
            abort_unless($r->user_id === $request->user()->id && $r->status === 'open' && $p->status === 'submitted', 403);
            abort_if(DB::table('proposals')->where('project_request_id', $r->id)->where('status', 'accepted')->exists(), 422, 'Ya elegiste una propuesta.');
            DB::table('proposals')->where('id', $p->id)->update(['status' => 'accepted', 'updated_at' => now()]);
        });

        return back()->with('notice', 'Profesional elegido para preparar el presupuesto. Esto todavía no crea una contratación.');
    }

    // Permite retirar únicamente una propuesta enviada por el profesional actual.
    public function withdraw(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer']);
        $p = DB::table('proposals')->where('id', $d['id'])->firstOrFail();
        $profile = DB::table('professional_profiles')->where('id', $p->professional_profile_id)->firstOrFail();
        abort_unless($profile->user_id === $request->user()->id && $p->status === 'submitted', 403);
        DB::table('proposals')->where('id', $p->id)->update(['status' => 'withdrawn', 'updated_at' => now()]);

        return back()->with('notice', 'Propuesta retirada.');
    }
}
