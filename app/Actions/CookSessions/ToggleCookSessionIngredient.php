<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;
use App\Models\RecipeIngredient;

final class ToggleCookSessionIngredient
{
    public function handle(CookSession $session, RecipeIngredient $ingredient, bool $checked): void
    {
        if ($checked) {
            $session->checkedIngredients()->syncWithoutDetaching([
                $ingredient->id => ['checked_at' => now()],
            ]);

            return;
        }

        $session->checkedIngredients()->detach($ingredient->id);
    }
}
