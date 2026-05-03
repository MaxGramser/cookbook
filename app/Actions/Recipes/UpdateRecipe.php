<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Support\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class UpdateRecipe
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
    public function handle(Recipe $recipe, array $attributes, array $ingredients, array $steps, array $tagIds = [], ?UploadedFile $image = null): Recipe
    {
        $newImagePath = $image !== null
            ? $this->imageProcessor->processUpload($image)
            : null;
        $oldImagePath = $recipe->image_path;

        return DB::transaction(function () use ($recipe, $attributes, $ingredients, $steps, $tagIds, $newImagePath, $oldImagePath) {
            $recipe->update([
                'title' => $attributes['title'],
                'source_url' => $attributes['source_url'] ?? null,
                'servings' => $attributes['servings'],
                'cook_time_minutes' => $attributes['cook_time_minutes'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                ...($newImagePath !== null
                    ? ['image_path' => $newImagePath]
                    : []),
            ]);

            if ($newImagePath !== null && $oldImagePath !== null) {
                Storage::disk('public')->delete($oldImagePath);
            }

            $recipe->ingredients()->delete();
            $recipe->steps()->delete();
            $this->sync->handle($recipe, $ingredients, $steps);
            $this->syncTags->handle($recipe, $recipe->user, $tagIds);

            return $recipe->refresh();
        });
    }
}
