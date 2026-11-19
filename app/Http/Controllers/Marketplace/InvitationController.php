<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    // Invita a un perfil activo a una solicitud propia y evita repetir la misma invitación.
    public function store(Request $request)
    {
        $d = $request->validate(['request_id' => 'required|integer', 'professional_id' => 'required|integer|exists:professional_profiles,id']);
        $r = DB::table('project_requests')->where('id', $d['request_id'])->where('user_id', $request->user()->id)->where('status', 'open')->firstOrFail();
        $pro = DB::table('professional_profiles')->where('id', $d['professional_id'])->where('active', 1)->firstOrFail();
        abort_if($pro->user_id === $request->user()->id, 422);
        DB::table('project_request_invitations')->insertOrIgnore(['project_request_id' => $r->id, 'professional_profile_id' => $pro->id, 'status' => 'pending', 'created_at' => now()]);

        return redirect('/cliente/invitaciones')->with('notice', 'Invitación guardada para el profesional.');
    }

    // Valida que la respuesta provenga del destinatario de una invitación todavía pendiente.
    public function respond(Request $request)
    {
        $d = $request->validate(['id' => 'required|integer', 'status' => ['required', Rule::in(['accepted', 'declined'])]]);
        $i = DB::table('project_request_invitations')->where('id', $d['id'])->firstOrFail();
        $p = DB::table('professional_profiles')->where('id', $i->professional_profile_id)->firstOrFail();
        $r = DB::table('project_requests')->where('id', $i->project_request_id)->firstOrFail();
        abort_unless($p->user_id === $request->user()->id && $i->status === 'pending' && $r->status === 'open', 403);
        DB::table('project_request_invitations')->where('id', $i->id)->update(['status' => $d['status']]);

        return back()->with('notice', 'Respuesta guardada.');
    }
}
