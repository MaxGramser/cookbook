<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;
use App\Models\Recipe;
use App\Models\User;

final class StartCookSession
{
    public function handle(User $user, Recipe $recipe): CookSession
    {
        return $recipe->cookSessions()->create([
            'user_id' => $user->id,
            'servings_multiplier' => 1,
            'started_at' => now(),
        ]);
    }
}
