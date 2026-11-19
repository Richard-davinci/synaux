<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    // Valida y registra una consulta; la confirmación corresponde a un registro realmente guardado.
    public function store(Request $request, NotificationCenter $notifications)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:254',
            'topic' => 'required|string|max:255', 'message' => 'required|string|min:10|max:10000']);
        // La consulta y sus avisos se guardan juntos para que una confirmación siempre sea consistente.
        DB::transaction(function () use ($data, $request, $notifications) {
            $id = DB::table('contact_inquiries')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
            $notifications->send('consulta', $id, 'consulta-recibida:'.$id,
                'Nueva consulta', $data['topic'], management: true, actor: $request->user()?->id);
        });

        return redirect('/contacto?enviado=1')->with('notice', 'Recibimos tu consulta. Quedó guardada para su revisión.');
    }
}
