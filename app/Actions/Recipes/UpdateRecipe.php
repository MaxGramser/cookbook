<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UpdateRecipe
{
    public function __construct(private SyncIngredientsAndSteps $sync) {}

    /**
     * @param  array{title: string, source_url?: ?string, servings: int, cook_time_minutes?: ?int, notes?: ?string}  $attributes
     * @param  array<int, array<string, mixed>>  $ingredients
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(Recipe $recipe, array $attributes, array $ingredients, array $steps, ?UploadedFile $image = null): Recipe
    {
        return DB::transaction(function () use ($recipe, $attributes, $ingredients, $steps, $image) {
            $recipe->update([
                'title' => $attributes['title'],
                'source_url' => $attributes['source_url'] ?? null,
                'servings' => $attributes['servings'],
                'cook_time_minutes' => $attributes['cook_time_minutes'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                ...($image !== null
                    ? ['image_path' => $image->store('recipes', 'public')]
                    : []),
            ]);

            $recipe->ingredients()->delete();
            $recipe->steps()->delete();
            $this->sync->handle($recipe, $ingredients, $steps);

            return $recipe->refresh();
        });
    }
}
