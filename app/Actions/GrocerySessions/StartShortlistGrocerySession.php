<?php

namespace App\Actions\GrocerySessions;

use App\Models\GrocerySession;
use App\Models\Shortlist;
use App\Models\User;

final class StartShortlistGrocerySession
{
    public function handle(User $user, Shortlist $shortlist): GrocerySession
    {
        return $shortlist->grocerySessions()->create([
            'user_id' => $user->id,
            'phase' => GrocerySession::PHASE_HOME,
            'started_at' => now(),
        ]);
    }
}
