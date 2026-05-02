<?php

namespace App\Actions\Recipes;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchRecipes
{
    /**
     * @param  array{q?: ?string, starred?: ?bool, cooked?: ?bool, time?: ?string}  $filters
     */
    public function handle(User $user, array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = $user->recipes()->select([
            'id', 'title', 'image_path', 'cook_time_minutes', 'servings',
            'starred_at', 'last_cooked_at', 'cooked_count', 'created_at',
        ]);

        $term = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('ingredients', fn ($iq) => $iq->where('name', 'like', $like));
            });
        }

        if (! empty($filters['starred'])) {
            $query->whereNotNull('starred_at');
        }

        if (! empty($filters['cooked'])) {
            $query->where('cooked_count', '>', 0);
        }

        $bucket = $filters['time'] ?? null;
        if ($bucket === 'quick') {
            $query->where('cook_time_minutes', '<=', 20);
        } elseif ($bucket === 'medium') {
            $query->whereBetween('cook_time_minutes', [21, 45]);
        } elseif ($bucket === 'long') {
            $query->where('cook_time_minutes', '>', 45);
        }

        return $query
            ->orderByRaw('starred_at IS NULL')
            ->orderByDesc('starred_at')
            ->orderByDesc('cooked_count')
            ->orderByRaw('last_cooked_at IS NULL')
            ->orderByDesc('last_cooked_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
