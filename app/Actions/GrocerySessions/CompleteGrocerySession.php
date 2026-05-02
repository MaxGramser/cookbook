<?php

namespace App\Actions\GrocerySessions;

use App\Models\GrocerySession;

final class CompleteGrocerySession
{
    public function handle(GrocerySession $session): GrocerySession
    {
        if ($session->completed_at === null) {
            $session->update(['completed_at' => now()]);
        }

        return $session;
    }
}
