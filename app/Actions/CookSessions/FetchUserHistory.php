<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;
use App\Models\GrocerySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class FetchUserHistory
{
    /**
     * @return array{cook: Collection<int, CookSession>, grocery: Collection<int, GrocerySession>}
     */
    public function handle(User $user, int $limit = 200): array
    {
        $cook = CookSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with(['recipe:id,title,image_path'])
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get(['id', 'recipe_id', 'servings_multiplier', 'started_at', 'completed_at', 'notes']);

        $grocery = GrocerySession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with(['recipe:id,title,image_path'])
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get(['id', 'recipe_id', 'phase', 'started_at', 'completed_at']);

        return [
            'cook' => $cook,
            'grocery' => $grocery,
        ];
    }
}
