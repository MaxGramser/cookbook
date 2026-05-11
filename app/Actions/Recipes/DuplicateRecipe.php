<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DuplicateRecipe
{
    private const DISK = 'public';

    public function handle(Recipe $source, User $targetUser): Recipe
    {
        $source->loadMissing(['ingredients', 'steps', 'tags']);

        return DB::transaction(function () use ($source, $targetUser): Recipe {
            $copy = $targetUser->recipes()->create([
                'title' => $source->title,
                'source_url' => $source->source_url,
                'servings' => $source->servings,
                'cook_time_minutes' => $source->cook_time_minutes,
                'notes' => $source->notes,
                'image_path' => $this->copyImage($source->image_path),
            ]);

            foreach ($source->ingredients as $ingredient) {
                $copy->ingredients()->create($this->ingredientAttributes($ingredient));
            }

            foreach ($source->steps as $step) {
                $copy->steps()->create($this->stepAttributes($step));
            }

            $tagIds = $this->resolveTagIds($source, $targetUser);

            if ($tagIds !== []) {
                $copy->tags()->sync($tagIds);
            }

            return $copy;
        });
    }

    private function copyImage(?string $sourcePath): ?string
    {
        if ($sourcePath === null || $sourcePath === '') {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'webp';
        $directory = trim(dirname($sourcePath), '.');
        $newPath = ($directory !== '' ? $directory.'/' : '').bin2hex(random_bytes(16)).'.'.$extension;

        $disk->copy($sourcePath, $newPath);

        return $newPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function ingredientAttributes(RecipeIngredient $ingredient): array
    {
        return [
            'section' => $ingredient->section,
            'position' => $ingredient->position,
            'name' => $ingredient->name,
            'quantity' => $ingredient->quantity,
            'unit' => $ingredient->unit,
            'raw_text' => $ingredient->raw_text,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepAttributes(RecipeStep $step): array
    {
        return [
            'section' => $step->section,
            'position' => $step->position,
            'body' => $step->body,
            'timer_minutes' => $step->timer_minutes,
        ];
    }

    /**
     * Map source-recipe tags onto tags the target user can own:
     * system tags pass through, user-owned tags are recreated for the target user.
     *
     * @return list<int>
     */
    private function resolveTagIds(Recipe $source, User $targetUser): array
    {
        $resolved = [];

        foreach ($source->tags as $tag) {
            if ($tag->is_system) {
                $resolved[] = $tag->id;

                continue;
            }

            $existing = Tag::query()
                ->where('user_id', $targetUser->id)
                ->where('group', $tag->group)
                ->where('slug', $tag->slug)
                ->first();

            if ($existing !== null) {
                $resolved[] = $existing->id;

                continue;
            }

            $finalSlug = $tag->slug;
            $i = 2;
            while (Tag::query()->where('group', $tag->group)->where('slug', $finalSlug)->exists()) {
                $finalSlug = $tag->slug.'-'.$i;
                $i++;
            }

            $created = Tag::create([
                'group' => $tag->group,
                'slug' => $finalSlug,
                'name' => $tag->name,
                'color' => $tag->color,
                'sort' => $tag->sort,
                'is_system' => false,
                'user_id' => $targetUser->id,
            ]);

            $resolved[] = $created->id;
        }

        return array_values(array_unique($resolved));
    }
}
