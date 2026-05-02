<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;
use App\Models\GrocerySession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class FetchUserHistory
{
    public function cookSessions(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return CookSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with(['recipe:id,title,image_path'])
            ->orderByDesc('completed_at')
            ->paginate($perPage, ['id', 'recipe_id', 'servings_multiplier', 'started_at', 'completed_at', 'notes'])
            ->withQueryString();
    }

    public function grocerySessions(User $user, int $perPage = 60): LengthAwarePaginator
    {
        return GrocerySession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with(['recipe:id,title,image_path'])
            ->orderByDesc('completed_at')
            ->paginate($perPage, ['id', 'recipe_id', 'phase', 'started_at', 'completed_at'], 'gpage')
            ->withQueryString();
    }
}
