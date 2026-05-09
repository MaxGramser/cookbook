<?php

namespace App\Http\Controllers;

use App\Actions\GrocerySessions\CompleteGrocerySession;
use App\Actions\GrocerySessions\StartGrocerySession;
use App\Actions\GrocerySessions\StartShortlistGrocerySession;
use App\Actions\GrocerySessions\ToggleGrocerySessionIngredient;
use App\Actions\GrocerySessions\UpdateGrocerySessionPhase;
use App\Models\GrocerySession;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Shortlist;
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

    public function storeForShortlist(Request $request, Shortlist $shortlist, StartShortlistGrocerySession $action): RedirectResponse
    {
        abort_if((int) $shortlist->user_id !== (int) $request->user()->id, 403);

        $session = $action->handle($request->user(), $shortlist);

        return redirect()->route('grocery.show', $session);
    }

    public function show(Request $request, GrocerySession $session): Response
    {
        $this->authorizeOwner($request, $session);

        if ($session->isForShortlist()) {
            $session->load([
                'shortlist.recipes' => function ($q) {
                    $q->with('ingredients');
                },
                'checkedIngredients:id',
            ]);

            $recipes = $session->shortlist->recipes->map(fn (Recipe $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'image_path' => $r->image_path,
                'servings' => $r->servings,
                'ingredients' => $r->ingredients,
            ])->values();
        } else {
            $session->load([
                'recipe.ingredients',
                'checkedIngredients:id',
            ]);

            $recipes = collect([
                [
                    'id' => $session->recipe->id,
                    'title' => $session->recipe->title,
                    'image_path' => $session->recipe->image_path,
                    'servings' => $session->recipe->servings,
                    'ingredients' => $session->recipe->ingredients,
                ],
            ]);
        }

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
                'subject_type' => $session->isForShortlist() ? 'shortlist' : 'recipe',
                'subject' => $session->isForShortlist()
                    ? [
                        'id' => $session->shortlist->id,
                        'title' => $session->shortlist->name,
                    ]
                    : [
                        'id' => $session->recipe->id,
                        'title' => $session->recipe->title,
                    ],
                'recipes' => $recipes,
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

        if ($session->isForShortlist()) {
            return redirect()->route('shortlists.show', $session->shortlist_id);
        }

        return redirect()->route('recipes.show', $session->recipe_id);
    }

    public function destroy(Request $request, GrocerySession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $isForShortlist = $session->isForShortlist();
        $recipeId = $session->recipe_id;
        $shortlistId = $session->shortlist_id;
        $session->delete();

        if ($isForShortlist) {
            return redirect()->route('shortlists.show', $shortlistId);
        }

        return redirect()->route('recipes.show', $recipeId);
    }

    public function toggleIngredient(Request $request, GrocerySession $session, RecipeIngredient $ingredient, ToggleGrocerySessionIngredient $action): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        if ($session->isForShortlist()) {
            $allowedRecipeIds = $session->shortlist->recipes()->pluck('recipes.id')->all();
            abort_unless(in_array((int) $ingredient->recipe_id, array_map('intval', $allowedRecipeIds), true), 404);
        } else {
            abort_if((int) $ingredient->recipe_id !== (int) $session->recipe_id, 404);
        }

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
