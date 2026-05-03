<?php

namespace App\Http\Controllers;

use App\Actions\Recipes\SearchRecipes;
use App\Actions\Recipes\StoreRecipe;
use App\Actions\Recipes\ToggleRecipeStar;
use App\Actions\Recipes\UpdateRecipe;
use App\Http\Requests\RecipeStoreRequest;
use App\Http\Requests\RecipeUpdateRequest;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request, SearchRecipes $search): Response
    {
        $tagIds = self::parseTagIds($request->input('tags'));

        $filters = [
            'q' => $request->string('q')->toString(),
            'starred' => $request->boolean('starred'),
            'cooked' => $request->boolean('cooked'),
            'time' => $request->string('time')->toString() ?: null,
            'tag_ids' => $tagIds,
        ];

        $user = $request->user();

        return Inertia::render('recipes/Index', [
            'recipes' => Inertia::scroll(
                fn () => $search->handle($user, $filters)
                    ->through(fn (Recipe $r) => [
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
                    ]),
            ),
            'filters' => [
                'q' => $filters['q'],
                'starred' => $filters['starred'],
                'cooked' => $filters['cooked'],
                'time' => $filters['time'],
                'tag_ids' => $tagIds,
            ],
            'tags' => self::availableTags($user),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('recipes/Create', [
            'tags' => self::availableTags($request->user()),
        ]);
    }

    public function store(RecipeStoreRequest $request, StoreRecipe $action): RedirectResponse
    {
        $recipe = $action->handle(
            $request->user(),
            [
                'title' => (string) $request->string('title'),
                'source_url' => $request->input('source_url'),
                'servings' => $request->integer('servings'),
                'cook_time_minutes' => $request->filled('cook_time_minutes')
                    ? $request->integer('cook_time_minutes')
                    : null,
                'notes' => $request->input('notes'),
            ],
            $request->input('ingredients', []),
            $request->input('steps', []),
            array_map('intval', (array) $request->input('tag_ids', [])),
            $request->file('image'),
        );

        return redirect()->route('recipes.show', $recipe);
    }

    public function show(Request $request, Recipe $recipe): Response
    {
        $this->authorizeOwner($request, $recipe);

        $recipe->load(['ingredients', 'steps', 'tags']);

        return Inertia::render('recipes/Show', [
            'recipe' => [
                'id' => $recipe->id,
                'user_id' => $recipe->user_id,
                'title' => $recipe->title,
                'source_url' => $recipe->source_url,
                'image_path' => $recipe->image_path,
                'servings' => $recipe->servings,
                'cook_time_minutes' => $recipe->cook_time_minutes,
                'notes' => $recipe->notes,
                'is_starred' => $recipe->starred_at !== null,
                'cooked_count' => (int) $recipe->cooked_count,
                'last_cooked_at' => $recipe->last_cooked_at,
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

        $recipe->load(['ingredients', 'steps', 'tags']);

        return Inertia::render('recipes/Edit', [
            'recipe' => $recipe,
            'tags' => self::availableTags($request->user()),
        ]);
    }

    public function update(RecipeUpdateRequest $request, Recipe $recipe, UpdateRecipe $action): RedirectResponse
    {
        $action->handle(
            $recipe,
            [
                'title' => (string) $request->string('title'),
                'source_url' => $request->input('source_url'),
                'servings' => $request->integer('servings'),
                'cook_time_minutes' => $request->filled('cook_time_minutes')
                    ? $request->integer('cook_time_minutes')
                    : null,
                'notes' => $request->input('notes'),
            ],
            $request->input('ingredients', []),
            $request->input('steps', []),
            array_map('intval', (array) $request->input('tag_ids', [])),
            $request->file('image'),
        );

        return redirect()->route('recipes.show', $recipe);
    }

    public function destroy(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeOwner($request, $recipe);
        $recipe->delete();

        return redirect()->route('recipes.index');
    }

    public function toggleStar(Request $request, Recipe $recipe, ToggleRecipeStar $action): RedirectResponse
    {
        $this->authorizeOwner($request, $recipe);
        $action->handle($recipe);

        return back();
    }

    private function authorizeOwner(Request $request, Recipe $recipe): void
    {
        abort_if((int) $recipe->user_id !== (int) $request->user()->id, 403);
    }

    /**
     * @return list<int>
     */
    private static function parseTagIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $raw),
            fn ($v) => $v > 0,
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function availableTags(User $user): array
    {
        return Tag::query()
            ->availableTo($user)
            ->orderBy('group')
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'group', 'slug', 'name', 'color', 'is_system'])
            ->map(fn (Tag $t) => [
                'id' => $t->id,
                'group' => $t->group,
                'slug' => $t->slug,
                'name' => $t->name,
                'color' => $t->color,
                'is_system' => $t->is_system,
            ])
            ->all();
    }
}
