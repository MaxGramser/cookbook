<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\Shortlist;
use App\Models\ShortlistShare;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShortlistController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'in:lime,pink,sky,cream,accent,ink'],
            'recipe_id' => ['nullable', 'integer', 'exists:recipes,id'],
            'redirect' => ['nullable', 'in:back,show'],
        ]);

        $shortlist = Shortlist::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
        ]);

        if (! empty($data['recipe_id'])) {
            $recipe = Recipe::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['recipe_id']);

            $shortlist->recipes()->attach($recipe->id, [
                'position' => 0,
            ]);
        }

        $redirect = $data['redirect']
            ?? (! empty($data['recipe_id']) ? 'back' : 'show');

        if ($redirect === 'back') {
            return back();
        }

        return redirect()->route('shortlists.show', $shortlist);
    }

    public function show(Request $request, Shortlist $shortlist): Response
    {
        $this->authorizeOwner($request, $shortlist);

        $shortlist->load([
            'recipes' => function ($q) {
                $q->with('tags');
            },
        ]);

        $activeShare = $shortlist->shares()->valid()->latest()->first();

        return Inertia::render('shortlists/Show', [
            'shortlist' => [
                'id' => $shortlist->id,
                'name' => $shortlist->name,
                'color' => $shortlist->color,
                'active_share' => $activeShare ? [
                    'token' => $activeShare->token,
                    'url' => route('share.shortlist.show', $activeShare->token),
                    'expires_at' => $activeShare->expires_at?->toIso8601String(),
                ] : null,
                'recipes' => $shortlist->recipes->map(fn (Recipe $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'image_path' => $r->image_path,
                    'cook_time_minutes' => $r->cook_time_minutes,
                    'servings' => $r->servings,
                    'is_starred' => $r->starred_at !== null,
                    'cooked_count' => (int) $r->cooked_count,
                    'last_cooked_at' => $r->last_cooked_at,
                    'tags' => $r->tags->map(fn (Tag $t) => [
                        'id' => $t->id,
                        'group' => $t->group,
                        'slug' => $t->slug,
                        'name' => $t->name,
                        'color' => $t->color,
                    ])->values(),
                    'pivot' => [
                        'position' => (int) $r->pivot->position,
                        'note' => $r->pivot->note,
                    ],
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Shortlist $shortlist): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'in:lime,pink,sky,cream,accent,ink'],
        ]);

        $shortlist->update($data);

        return back();
    }

    public function destroy(Request $request, Shortlist $shortlist): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $shortlist->delete();

        return redirect()->route('dashboard');
    }

    public function attach(Request $request, Shortlist $shortlist): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $data = $request->validate([
            'recipe_id' => ['required', 'integer', 'exists:recipes,id'],
        ]);

        $recipe = Recipe::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($data['recipe_id']);

        $existing = $shortlist->recipes()->where('recipes.id', $recipe->id)->exists();

        if (! $existing) {
            $maxPosition = (int) $shortlist->recipes()->max('recipe_shortlist.position');

            $shortlist->recipes()->attach($recipe->id, [
                'position' => $maxPosition + 1,
            ]);
        }

        return back();
    }

    public function detach(Request $request, Shortlist $shortlist, Recipe $recipe): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $shortlist->recipes()->detach($recipe->id);

        return back();
    }

    public function reorder(Request $request, Shortlist $shortlist): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $data = $request->validate([
            'recipe_ids' => ['required', 'array'],
            'recipe_ids.*' => ['integer'],
        ]);

        $existingIds = $shortlist->recipes()->pluck('recipes.id')->all();
        $orderedIds = array_values(array_filter(
            $data['recipe_ids'],
            fn (int $id) => in_array($id, $existingIds, true),
        ));

        DB::transaction(function () use ($shortlist, $orderedIds) {
            foreach ($orderedIds as $position => $recipeId) {
                $shortlist->recipes()->updateExistingPivot($recipeId, [
                    'position' => $position,
                ]);
            }
        });

        return back();
    }

    public function updateRecipe(Request $request, Shortlist $shortlist, Recipe $recipe): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $shortlist->recipes()->updateExistingPivot($recipe->id, [
            'note' => $data['note'] ?? null,
        ]);

        return back();
    }

    public function share(Request $request, Shortlist $shortlist): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $shortlist->shares()->delete();

        ShortlistShare::create([
            'shortlist_id' => $shortlist->id,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
        ]);

        return back();
    }

    public function unshare(Request $request, Shortlist $shortlist): RedirectResponse
    {
        $this->authorizeOwner($request, $shortlist);

        $shortlist->shares()->delete();

        return back();
    }

    private function authorizeOwner(Request $request, Shortlist $shortlist): void
    {
        abort_if((int) $shortlist->user_id !== (int) $request->user()->id, 403);
    }
}
