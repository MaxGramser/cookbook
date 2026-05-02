<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;
use App\Models\RecipeStep;

final class ToggleCookSessionStep
{
    public function handle(CookSession $session, RecipeStep $step, bool $checked): void
    {
        if ($checked) {
            $session->checkedSteps()->syncWithoutDetaching([
                $step->id => ['checked_at' => now()],
            ]);

            return;
        }

        $session->checkedSteps()->detach($step->id);
    }
}
