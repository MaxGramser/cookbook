<?php

use App\Models\GrocerySession;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Shortlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can create a shortlist via the sidebar plus button', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/shortlists', ['name' => 'Vrijdagavond', 'color' => 'lime'])
        ->assertRedirect();

    expect(Shortlist::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(Shortlist::query()->first()->name)->toBe('Vrijdagavond');
});

test('creating a shortlist with a recipe_id immediately attaches that recipe and redirects back', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->from("/recipes/{$recipe->id}")
        ->post('/shortlists', [
            'name' => 'Snel klaar',
            'color' => 'sky',
            'recipe_id' => $recipe->id,
        ])
        ->assertRedirect("/recipes/{$recipe->id}");

    $shortlist = Shortlist::query()->first();
    expect($shortlist->recipes()->count())->toBe(1);
    expect($shortlist->recipes->first()->id)->toBe($recipe->id);
});

test('dropping a recipe on the make-new sidebar slot creates a shortlist and navigates to it', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->from('/dashboard')
        ->post('/shortlists', [
            'name' => 'Nieuw',
            'color' => 'lime',
            'recipe_id' => $recipe->id,
            'redirect' => 'show',
        ]);

    $shortlist = Shortlist::query()->first();
    expect($shortlist->recipes()->count())->toBe(1);
    expect($shortlist->recipes->first()->id)->toBe($recipe->id);
});

test('attaching a recipe to a shortlist appends at the next position', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $first = Recipe::factory()->for($user)->create();
    $second = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->post("/shortlists/{$shortlist->id}/recipes", ['recipe_id' => $first->id])
        ->assertRedirect();

    $this->actingAs($user)
        ->post("/shortlists/{$shortlist->id}/recipes", ['recipe_id' => $second->id])
        ->assertRedirect();

    $positions = $shortlist->recipes()->pluck('recipe_shortlist.position', 'recipes.id');
    expect((int) $positions[$first->id])->toBeLessThan((int) $positions[$second->id]);
});

test('attaching the same recipe twice is idempotent', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->post("/shortlists/{$shortlist->id}/recipes", ['recipe_id' => $recipe->id]);
    $this->actingAs($user)
        ->post("/shortlists/{$shortlist->id}/recipes", ['recipe_id' => $recipe->id]);

    expect($shortlist->recipes()->count())->toBe(1);
});

test('reordering rewrites pivot positions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $a = Recipe::factory()->for($user)->create();
    $b = Recipe::factory()->for($user)->create();
    $c = Recipe::factory()->for($user)->create();

    $shortlist->recipes()->attach([
        $a->id => ['position' => 0],
        $b->id => ['position' => 1],
        $c->id => ['position' => 2],
    ]);

    $this->actingAs($user)
        ->post("/shortlists/{$shortlist->id}/reorder", [
            'recipe_ids' => [$c->id, $a->id, $b->id],
        ])
        ->assertRedirect();

    $ordered = $shortlist->recipes()
        ->orderBy('recipe_shortlist.position')
        ->pluck('recipes.id')
        ->all();

    expect($ordered)->toBe([$c->id, $a->id, $b->id]);
});

test('detaching removes the pivot row', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    $this->actingAs($user)
        ->delete("/shortlists/{$shortlist->id}/recipes/{$recipe->id}")
        ->assertRedirect();

    expect($shortlist->recipes()->count())->toBe(0);
});

test('updating a recipe note within a shortlist persists', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    $this->actingAs($user)
        ->patch("/shortlists/{$shortlist->id}/recipes/{$recipe->id}", [
            'note' => 'Voorgerecht — halveren',
        ])
        ->assertRedirect();

    $note = $shortlist->recipes()->first()->pivot->note;
    expect($note)->toBe('Voorgerecht — halveren');
});

test('user cannot access another users shortlist', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($owner)->create();

    $this->actingAs($other)->get("/shortlists/{$shortlist->id}")->assertForbidden();
});

test('starting a grocery session for a shortlist redirects to the session', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    $this->actingAs($user)
        ->post("/shortlists/{$shortlist->id}/grocery")
        ->assertRedirect();

    $session = GrocerySession::query()->latest()->first();
    expect($session->shortlist_id)->toBe($shortlist->id);
    expect($session->recipe_id)->toBeNull();
    expect($session->phase)->toBe(GrocerySession::PHASE_HOME);
});

test('shortlist grocery session renders all recipes ingredients with subject info', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create(['name' => 'Menu']);
    $first = Recipe::factory()->for($user)->create();
    $second = Recipe::factory()->for($user)->create();
    RecipeIngredient::factory()->for($first)->create();
    RecipeIngredient::factory()->for($first)->create();
    RecipeIngredient::factory()->for($second)->create();
    $shortlist->recipes()->attach([
        $first->id => ['position' => 0],
        $second->id => ['position' => 1],
    ]);

    $session = GrocerySession::factory()->for($user)->create([
        'recipe_id' => null,
        'shortlist_id' => $shortlist->id,
    ]);

    $this->actingAs($user)
        ->get("/grocery/{$session->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('session.subject_type', 'shortlist')
            ->where('session.subject.title', 'Menu')
            ->has('session.recipes', 2)
        );
});

test('toggling an ingredient from any recipe in the shortlist works', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();
    $ingredient = RecipeIngredient::factory()->for($recipe)->create();
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    $session = GrocerySession::factory()->for($user)->create([
        'recipe_id' => null,
        'shortlist_id' => $shortlist->id,
    ]);

    $this->actingAs($user)
        ->post("/grocery/{$session->id}/ingredients/{$ingredient->id}", ['checked' => true])
        ->assertRedirect();

    expect($session->checkedIngredients()->count())->toBe(1);
});

test('shortlist sidebar items are shared on every page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Shortlist::factory()->for($user)->create(['name' => 'Weeklijst']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('shortlists', 1)
            ->where('shortlists.0.name', 'Weeklijst')
        );
});

test('completing a shortlist grocery session redirects to the shortlist', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $session = GrocerySession::factory()->for($user)->shopping()->create([
        'recipe_id' => null,
        'shortlist_id' => $shortlist->id,
    ]);

    $this->actingAs($user)
        ->post("/grocery/{$session->id}/complete")
        ->assertRedirect("/shortlists/{$shortlist->id}");
});

test('deleting a shortlist cascades the pivot but keeps recipes intact', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($user)->create();
    $recipe = Recipe::factory()->for($user)->create();
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    $this->actingAs($user)
        ->delete("/shortlists/{$shortlist->id}")
        ->assertRedirect();

    expect(Shortlist::query()->where('id', $shortlist->id)->exists())->toBeFalse();
    expect(Recipe::query()->where('id', $recipe->id)->exists())->toBeTrue();
});
