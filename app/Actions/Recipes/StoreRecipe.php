<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use App\Support\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class StoreRecipe
{
    public function __construct(
        private SyncIngredientsAndSteps $sync,
        private SyncRecipeTags $syncTags,
        private ImageProcessor $imageProcessor,
    ) {}

    /**
     * @param  array{title: string, source_url?: ?string, servings: int, cook_time_minutes?: ?int, notes?: ?string}  $attributes
     * @param  array<int, array<string, mixed>>  $ingredients
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, int>  $tagIds
     */
    public function handle(User $user, array $attributes, array $ingredients, array $steps, array $tagIds = [], ?UploadedFile $image = null): Recipe
    {
        $imagePath = $image !== null
            ? $this->imageProcessor->processUpload($image)
            : null;

        return DB::transaction(function () use ($user, $attributes, $ingredients, $steps, $tagIds, $imagePath) {
            $recipe = $user->recipes()->create([
                'title' => $attributes['title'],
                'source_url' => $attributes['source_url'] ?? null,
                'servings' => $attributes['servings'],
                'cook_time_minutes' => $attributes['cook_time_minutes'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'image_path' => $imagePath,
            ]);

            $this->sync->handle($recipe, $ingredients, $steps);
            $this->syncTags->handle($recipe, $user, $tagIds);

            return $recipe;
        });
    }
}
