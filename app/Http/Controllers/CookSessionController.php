<?php

namespace App\Http\Controllers;

use App\Models\CookSession;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CookSessionController extends Controller
{
    public function store(Request $request, Recipe $recipe): RedirectResponse
    {
        abort_if((int) $recipe->user_id !== (int) $request->user()->id, 403);

        $session = $recipe->cookSessions()->create([
            'user_id' => $request->user()->id,
            'servings_multiplier' => 1,
            'started_at' => now(),
        ]);

        return redirect()->route('cook.show', $session);
    }

    public function show(Request $request, CookSession $session): Response
    {
        $this->authorizeOwner($request, $session);

        $session->load([
            'recipe.ingredients',
            'recipe.steps',
            'checkedIngredients:id',
            'checkedSteps:id',
        ]);

        return Inertia::render('cook/Show', [
            'session' => [
                'id' => $session->id,
                'servings_multiplier' => $session->servings_multiplier,
                'notes' => $session->notes,
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
                'recipe' => [
                    'id' => $session->recipe->id,
                    'title' => $session->recipe->title,
                    'image_path' => $session->recipe->image_path,
                    'servings' => $session->recipe->servings,
                    'cook_time_minutes' => $session->recipe->cook_time_minutes,
                    'ingredients' => $session->recipe->ingredients,
                    'steps' => $session->recipe->steps,
                ],
                'checked_ingredient_ids' => $session->checkedIngredients->pluck('id'),
                'checked_step_ids' => $session->checkedSteps->pluck('id'),
            ],
        ]);
    }

    public function update(Request $request, CookSession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $data = $request->validate([
            'servings_multiplier' => ['sometimes', 'numeric', 'min:0.25', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $session->update($data);

        return back();
    }

    public function complete(Request $request, CookSession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        if ($session->completed_at === null) {
            $session->update(['completed_at' => now()]);
        }

        return redirect()->route('recipes.show', $session->recipe_id);
    }

    public function destroy(Request $request, CookSession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $recipeId = $session->recipe_id;
        $session->delete();

        return redirect()->route('recipes.show', $recipeId);
    }

    public function toggleIngredient(Request $request, CookSession $session, RecipeIngredient $ingredient): RedirectResponse
    {
        $this->authorizeOwner($request, $session);
        abort_if((int) $ingredient->recipe_id !== (int) $session->recipe_id, 404);

        $data = $request->validate([
            'checked' => ['required', 'boolean'],
        ]);

        if ($data['checked']) {
            $session->checkedIngredients()->syncWithoutDetaching([
                $ingredient->id => ['checked_at' => now()],
            ]);
        } else {
            $session->checkedIngredients()->detach($ingredient->id);
        }

        return back();
    }

    public function toggleStep(Request $request, CookSession $session, RecipeStep $step): RedirectResponse
    {
        $this->authorizeOwner($request, $session);
        abort_if((int) $step->recipe_id !== (int) $session->recipe_id, 404);

        $data = $request->validate([
            'checked' => ['required', 'boolean'],
        ]);

        if ($data['checked']) {
            $session->checkedSteps()->syncWithoutDetaching([
                $step->id => ['checked_at' => now()],
            ]);
        } else {
            $session->checkedSteps()->detach($step->id);
        }

        return back();
    }

    private function authorizeOwner(Request $request, CookSession $session): void
    {
        abort_if((int) $session->user_id !== (int) $request->user()->id, 403);
    }
}
