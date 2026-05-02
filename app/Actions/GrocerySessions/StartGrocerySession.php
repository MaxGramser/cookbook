<?php

namespace App\Actions\GrocerySessions;

use App\Models\GrocerySession;
use App\Models\Recipe;
use App\Models\User;

final class StartGrocerySession
{
    public function handle(User $user, Recipe $recipe): GrocerySession
    {
        return $recipe->grocerySessions()->create([
            'user_id' => $user->id,
            'phase' => GrocerySession::PHASE_HOME,
            'started_at' => now(),
        ]);
    }
}
