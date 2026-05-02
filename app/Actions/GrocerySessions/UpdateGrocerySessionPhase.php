<?php

namespace App\Actions\GrocerySessions;

use App\Models\GrocerySession;

final class UpdateGrocerySessionPhase
{
    public function handle(GrocerySession $session, string $phase): GrocerySession
    {
        if ($session->completed_at !== null) {
            return $session;
        }

        $session->update(['phase' => $phase]);

        return $session;
    }
}
