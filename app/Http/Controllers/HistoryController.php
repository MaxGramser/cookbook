<?php

namespace App\Http\Controllers;

use App\Models\CookSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = CookSession::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('completed_at')
            ->with(['recipe:id,title,image_path'])
            ->orderByDesc('completed_at')
            ->limit(200)
            ->get(['id', 'recipe_id', 'servings_multiplier', 'started_at', 'completed_at', 'notes']);

        return Inertia::render('History', [
            'sessions' => $sessions,
        ]);
    }
}
