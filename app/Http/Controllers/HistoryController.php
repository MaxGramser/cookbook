<?php

namespace App\Http\Controllers;

use App\Actions\CookSessions\FetchUserHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request, FetchUserHistory $action): Response
    {
        $history = $action->handle($request->user());

        return Inertia::render('History', [
            'sessions' => $history['cook'],
            'grocerySessions' => $history['grocery'],
        ]);
    }
}
