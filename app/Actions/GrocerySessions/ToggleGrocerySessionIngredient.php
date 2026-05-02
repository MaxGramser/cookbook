<?php

namespace App\Actions\GrocerySessions;

use App\Models\GrocerySession;
use App\Models\RecipeIngredient;

final class ToggleGrocerySessionIngredient
{
    public function handle(GrocerySession $session, RecipeIngredient $ingredient, bool $checked): void
    {
        if ($checked) {
            $session->checkedIngredients()->syncWithoutDetaching([
                $ingredient->id => [
                    'checked_at' => now(),
                    'checked_in_phase' => $session->phase,
                ],
            ]);

            return;
        }

        $session->checkedIngredients()->detach($ingredient->id);
    }
}
