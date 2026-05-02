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
        $user = $request->user();

        return Inertia::render('History', [
            'sessions' => Inertia::scroll(fn () => $action->cookSessions($user)),
            'grocerySessions' => Inertia::scroll(fn () => $action->grocerySessions($user)),
        ]);
    }
}
