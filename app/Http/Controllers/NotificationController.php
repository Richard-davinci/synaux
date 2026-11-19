<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationCenter;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // La lectura individual o total usa siempre la cuenta autenticada; un id ajeno devuelve 404.
    public function read(Request $request, NotificationCenter $center, ?int $id = null)
    {
        $query = $center->query($request->user());
        if ($id !== null) {
            $record = (clone $query)->where('id', $id)->firstOrFail();
            $query->where('id', $record->id);
        }
        $query->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
