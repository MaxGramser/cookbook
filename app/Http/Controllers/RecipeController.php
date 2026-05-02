<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeStoreRequest;
use App\Http\Requests\RecipeUpdateRequest;
use App\Models\Recipe;
use App\Support\Units\IngredientNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $recipes = $request->user()->recipes()
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'image_path', 'cook_time_minutes', 'servings']);

        return Inertia::render('recipes/Index', [
            'recipes' => $recipes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('recipes/Create');
    }

    public function store(RecipeStoreRequest $request): RedirectResponse
    {
        $recipe = DB::transaction(function () use ($request) {
            $recipe = $request->user()->recipes()->create([
                'title' => $request->string('title'),
                'source_url' => $request->input('source_url'),
                'servings' => $request->integer('servings'),
                'cook_time_minutes' => $request->filled('cook_time_minutes')
                    ? $request->integer('cook_time_minutes')
                    : null,
                'notes' => $request->input('notes'),
                'image_path' => $request->hasFile('image')
                    ? $request->file('image')->store('recipes', 'public')
                    : null,
            ]);

            $this->syncIngredientsAndSteps($recipe, $request->input('ingredients', []), $request->input('steps', []));

            return $recipe;
        });

        return redirect()->route('recipes.show', $recipe);
    }

    public function show(Request $request, Recipe $recipe): Response
    {
        $this->authorizeOwner($request, $recipe);

        $recipe->load(['ingredients', 'steps']);

        return Inertia::render('recipes/Show', [
            'recipe' => $recipe,
            'recentSessions' => $recipe->cookSessions()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('started_at')
                ->limit(5)
                ->get(['id', 'started_at', 'completed_at', 'servings_multiplier']),
        ]);
    }

    public function edit(Request $request, Recipe $recipe): Response
    {
        $this->authorizeOwner($request, $recipe);

        $recipe->load(['ingredients', 'steps']);

        return Inertia::render('recipes/Edit', [
            'recipe' => $recipe,
        ]);
    }

    public function update(RecipeUpdateRequest $request, Recipe $recipe): RedirectResponse
    {
        DB::transaction(function () use ($request, $recipe) {
            $recipe->update([
                'title' => $request->string('title'),
                'source_url' => $request->input('source_url'),
                'servings' => $request->integer('servings'),
                'cook_time_minutes' => $request->filled('cook_time_minutes')
                    ? $request->integer('cook_time_minutes')
                    : null,
                'notes' => $request->input('notes'),
                ...($request->hasFile('image')
                    ? ['image_path' => $request->file('image')->store('recipes', 'public')]
                    : []),
            ]);

            $recipe->ingredients()->delete();
            $recipe->steps()->delete();
            $this->syncIngredientsAndSteps($recipe, $request->input('ingredients', []), $request->input('steps', []));
        });

        return redirect()->route('recipes.show', $recipe);
    }

    public function destroy(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeOwner($request, $recipe);
        $recipe->delete();

        return redirect()->route('recipes.index');
    }

    /**
     * @param  array<int, array{quantity_text?: ?string, unit_text?: ?string, name: string, raw_text?: ?string}>  $ingredients
     * @param  array<int, array{body: string}>  $steps
     */
    private function syncIngredientsAndSteps(Recipe $recipe, array $ingredients, array $steps): void
    {
        foreach (array_values($ingredients) as $i => $row) {
            $normalized = IngredientNormalizer::fromParts(
                $row['quantity_text'] ?? null,
                $row['unit_text'] ?? null,
                $row['name'],
                $row['raw_text'] ?? null,
            );

            $recipe->ingredients()->create([
                'position' => $i + 1,
                'name' => $normalized['name'],
                'quantity' => $normalized['quantity'],
                'unit' => $normalized['unit'],
                'raw_text' => $normalized['raw_text'],
            ]);
        }

        foreach (array_values($steps) as $i => $row) {
            $recipe->steps()->create([
                'position' => $i + 1,
                'body' => $row['body'],
            ]);
        }
    }

    private function authorizeOwner(Request $request, Recipe $recipe): void
    {
        abort_if((int) $recipe->user_id !== (int) $request->user()->id, 403);
    }
}
