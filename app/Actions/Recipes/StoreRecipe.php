<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class StoreRecipe
{
    public function __construct(private SyncIngredientsAndSteps $sync) {}

    /**
     * @param  array{title: string, source_url?: ?string, servings: int, cook_time_minutes?: ?int, notes?: ?string}  $attributes
     * @param  array<int, array<string, mixed>>  $ingredients
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function handle(User $user, array $attributes, array $ingredients, array $steps, ?UploadedFile $image = null): Recipe
    {
        return DB::transaction(function () use ($user, $attributes, $ingredients, $steps, $image) {
            $recipe = $user->recipes()->create([
                'title' => $attributes['title'],
                'source_url' => $attributes['source_url'] ?? null,
                'servings' => $attributes['servings'],
                'cook_time_minutes' => $attributes['cook_time_minutes'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'image_path' => $image?->store('recipes', 'public'),
            ]);

            $this->sync->handle($recipe, $ingredients, $steps);

            return $recipe;
        });
    }
}
