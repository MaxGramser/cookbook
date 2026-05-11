<?php

namespace App\Http\Controllers;

use App\Actions\Recipes\DuplicateRecipe;
use App\Models\RecipeShare;
use App\Models\Tag;
use App\Support\Sharing\ShareMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicRecipeController extends Controller
{
    public const PENDING_SESSION_KEY = 'pending_recipe_share_token';

    public function show(string $token)
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            return Inertia::render('share/Expired')->toResponse(request())->setStatusCode(410);
        }

        $recipe = $share->recipe;
        $recipe->load(['ingredients', 'steps', 'tags']);

        return Inertia::render('share/PublicRecipe', [
            'token' => $share->token,
            'expiresAt' => $share->expires_at?->toIso8601String(),
            'meta' => ShareMeta::forRecipe($recipe, route('share.recipe.show', $share->token)),
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

    public function copy(Request $request, string $token, DuplicateRecipe $duplicate): RedirectResponse
    {
        $share = $this->resolveShare($token);

        if ($share === null) {
            return redirect()->route('share.recipe.show', $token);
        }

        $user = $request->user();

        if ($user === null) {
            $request->session()->put(self::PENDING_SESSION_KEY, $share->token);

            return redirect()->route('register');
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        $copy = $duplicate->handle($share->recipe, $user);

        return redirect()->route('recipes.show', $copy)
            ->with('status', 'Recept toegevoegd aan je CookBook.');
    }

    public function claim(Request $request, DuplicateRecipe $duplicate): RedirectResponse
    {
        $token = $request->session()->pull(self::PENDING_SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return redirect()->route('dashboard');
        }

        $share = $this->resolveShare($token);

        if ($share === null) {
            return redirect()->route('dashboard');
        }

        $copy = $duplicate->handle($share->recipe, $request->user());

        return redirect()->route('recipes.show', $copy)
            ->with('status', 'Recept toegevoegd aan je CookBook.');
    }

    private function resolveShare(string $token): ?RecipeShare
    {
        return RecipeShare::query()
            ->valid()
            ->where('token', $token)
            ->with('recipe')
            ->first();
    }
}
