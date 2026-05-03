<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;

final class SyncRecipeTags
{
    /**
     * Sync tags onto a recipe, restricted to system tags or tags owned
     * by the given user. Cross-user / unknown IDs are silently dropped.
     *
     * @param  array<int, int|string>  $tagIds
     */
    public function handle(Recipe $recipe, User $user, array $tagIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $tagIds)));

        if ($ids === []) {
            $recipe->tags()->sync([]);

            return;
        }

        $allowed = Tag::query()
            ->whereIn('id', $ids)
            ->where(function ($q) use ($user) {
                $q->where('is_system', true)->orWhere('user_id', $user->id);
            })
            ->pluck('id')
            ->all();

        $recipe->tags()->sync($allowed);
    }
}
