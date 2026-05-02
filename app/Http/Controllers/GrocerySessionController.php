<?php

namespace App\Http\Controllers;

use App\Actions\GrocerySessions\CompleteGrocerySession;
use App\Actions\GrocerySessions\StartGrocerySession;
use App\Actions\GrocerySessions\ToggleGrocerySessionIngredient;
use App\Actions\GrocerySessions\UpdateGrocerySessionPhase;
use App\Models\GrocerySession;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrocerySessionController extends Controller
{
    public function store(Request $request, Recipe $recipe, StartGrocerySession $action): RedirectResponse
    {
        abort_if((int) $recipe->user_id !== (int) $request->user()->id, 403);

        $session = $action->handle($request->user(), $recipe);

        return redirect()->route('grocery.show', $session);
    }

    public function show(Request $request, GrocerySession $session): Response
    {
        $this->authorizeOwner($request, $session);

        $session->load([
            'recipe.ingredients',
            'checkedIngredients:id',
        ]);

        $checks = $session->checkedIngredients->map(fn ($ingredient) => [
            'id' => $ingredient->id,
            'phase' => $ingredient->pivot->checked_in_phase,
        ])->values();

        return Inertia::render('grocery/Show', [
            'session' => [
                'id' => $session->id,
                'phase' => $session->phase,
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
                'recipe' => [
                    'id' => $session->recipe->id,
                    'title' => $session->recipe->title,
                    'image_path' => $session->recipe->image_path,
                    'servings' => $session->recipe->servings,
                    'ingredients' => $session->recipe->ingredients,
                ],
                'checks' => $checks,
            ],
        ]);
    }

    public function phase(Request $request, GrocerySession $session, UpdateGrocerySessionPhase $action): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $data = $request->validate([
            'phase' => ['required', 'in:home,shopping'],
        ]);

        $action->handle($session, $data['phase']);

        return back();
    }

    public function complete(Request $request, GrocerySession $session, CompleteGrocerySession $action): RedirectResponse
    {
        $this->authorizeOwner($request, $session);
        $action->handle($session);

        return redirect()->route('recipes.show', $session->recipe_id);
    }

    public function destroy(Request $request, GrocerySession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $recipeId = $session->recipe_id;
        $session->delete();

        return redirect()->route('recipes.show', $recipeId);
    }

    public function toggleIngredient(Request $request, GrocerySession $session, RecipeIngredient $ingredient, ToggleGrocerySessionIngredient $action): RedirectResponse
    {
        $this->authorizeOwner($request, $session);
        abort_if((int) $ingredient->recipe_id !== (int) $session->recipe_id, 404);

        $data = $request->validate([
            'checked' => ['required', 'boolean'],
        ]);

        $action->handle($session, $ingredient, (bool) $data['checked']);

        return back();
    }

    private function authorizeOwner(Request $request, GrocerySession $session): void
    {
        abort_if((int) $session->user_id !== (int) $request->user()->id, 403);
    }
}
