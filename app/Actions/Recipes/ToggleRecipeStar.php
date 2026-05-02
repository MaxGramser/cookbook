<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;

final class ToggleRecipeStar
{
    public function handle(Recipe $recipe): Recipe
    {
        $recipe->update([
            'starred_at' => $recipe->starred_at === null ? now() : null,
        ]);

        return $recipe;
    }
}
