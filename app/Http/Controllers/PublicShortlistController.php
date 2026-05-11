<?php

namespace App\Http\Controllers;

use App\Actions\Recipes\DuplicateRecipe;
use App\Models\Recipe;
use App\Models\RecipeShare;
use App\Models\ShortlistShare;
use App\Models\Tag;
use App\Support\Sharing\ShareMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PublicShortlistController extends Controller
{
    public function show(string $token)
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            return Inertia::render('share/Expired')->toResponse(request())->setStatusCode(410);
        }

        $shortlist = $share->shortlist;
        $shortlist->load([
            'recipes' => function ($q) {
                $q->with('tags');
            },
        ]);

        return Inertia::render('share/Shortlist', [
            'token' => $share->token,
            'expiresAt' => $share->expires_at?->toIso8601String(),
            'meta' => ShareMeta::forShortlist($shortlist, route('share.shortlist.show', $share->token)),
            'shortlist' => [
                'id' => $shortlist->id,
                'name' => $shortlist->name,
                'color' => $shortlist->color,
                'recipes' => $shortlist->recipes->map(fn (Recipe $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'image_path' => $r->image_path,
                    'cook_time_minutes' => $r->cook_time_minutes,
                    'servings' => $r->servings,
                    'is_starred' => false,
                    'cooked_count' => 0,
                    'last_cooked_at' => null,
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

    public function showRecipe(string $token, Recipe $recipe)
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            return Inertia::render('share/Expired')->toResponse(request())->setStatusCode(410);
        }

        $belongsToShortlist = $share->shortlist
            ->recipes()
            ->where('recipes.id', $recipe->id)
            ->exists();

        abort_unless($belongsToShortlist, 404);

        $recipe->load(['ingredients', 'steps', 'tags']);

        $pivotNote = $share->shortlist->recipes()
            ->where('recipes.id', $recipe->id)
            ->first()
            ?->pivot->note;

        return Inertia::render('share/Recipe', [
            'token' => $share->token,
            'expiresAt' => $share->expires_at?->toIso8601String(),
            'meta' => ShareMeta::forRecipe(
                $recipe,
                route('share.shortlist.recipe', ['token' => $share->token, 'recipe' => $recipe->id]),
            ),
            'shortlist' => [
                'id' => $share->shortlist->id,
                'name' => $share->shortlist->name,
                'color' => $share->shortlist->color,
            ],
            'note' => $pivotNote,
            'recipe' => [
                'id' => $recipe->id,
                'title' => $recipe->title,
                'source_url' => $recipe->source_url,
                'image_path' => $recipe->image_path,
                'servings' => $recipe->servings,
                'cook_time_minutes' => $recipe->cook_time_minutes,
                'notes' => $recipe->notes,
                'ingredients' => $recipe->ingredients,
                'steps' => $recipe->steps,
                'tags' => $recipe->tags->map(fn (Tag $t) => [
                    'id' => $t->id,
                    'group' => $t->group,
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'color' => $t->color,
                ])->values(),
            ],
        ]);
    }

    public function copyRecipe(Request $request, string $token, Recipe $recipe, DuplicateRecipe $duplicate): RedirectResponse
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            return redirect()->route('share.shortlist.show', $token);
        }

        $belongsToShortlist = $share->shortlist
            ->recipes()
            ->where('recipes.id', $recipe->id)
            ->exists();

        abort_unless($belongsToShortlist, 404);

        $user = $request->user();

        if ($user === null) {
            $recipeShare = RecipeShare::create([
                'recipe_id' => $recipe->id,
                'token' => Str::random(40),
                'expires_at' => now()->addDays(30),
            ]);

            $request->session()->put(PublicRecipeController::PENDING_SESSION_KEY, $recipeShare->token);

            return redirect()->route('register');
        }

        $copy = $duplicate->handle($recipe, $user);

        return redirect()->route('recipes.show', $copy)
            ->with('status', 'Recept toegevoegd aan je CookBook.');
    }

    private function resolveShare(string $token): ?ShortlistShare
    {
        return ShortlistShare::query()
            ->valid()
            ->where('token', $token)
            ->with('shortlist')
            ->first();
    }
}
