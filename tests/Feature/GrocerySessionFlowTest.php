<?php

use App\Models\GrocerySession;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('starting a grocery session creates one in the home phase', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->post("/recipes/{$recipe->id}/grocery")
        ->assertRedirect();

    $session = GrocerySession::query()->where('recipe_id', $recipe->id)->first();
    expect($session)->not->toBeNull();
    expect($session->phase)->toBe(GrocerySession::PHASE_HOME);
    expect($session->completed_at)->toBeNull();
});

test('toggling an ingredient stores the pivot', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $ingredient = RecipeIngredient::factory()->for($recipe)->create();
    $session = GrocerySession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->post("/grocery/{$session->id}/ingredients/{$ingredient->id}", ['checked' => true])
        ->assertRedirect();

    expect($session->checkedIngredients()->count())->toBe(1);

    $this->actingAs($user)
        ->post("/grocery/{$session->id}/ingredients/{$ingredient->id}", ['checked' => false]);

    expect($session->checkedIngredients()->count())->toBe(0);
});

test('phase advance from home to shopping works', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = GrocerySession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->post("/grocery/{$session->id}/phase", ['phase' => 'shopping'])
        ->assertRedirect();

    expect($session->fresh()->phase)->toBe(GrocerySession::PHASE_SHOPPING);
});

test('completing a grocery session marks it done and redirects to recipe', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = GrocerySession::factory()->for($recipe)->for($user)->shopping()->create();

    $this->actingAs($user)
        ->post("/grocery/{$session->id}/complete")
        ->assertRedirect("/recipes/{$recipe->id}");

    expect($session->fresh()->completed_at)->not->toBeNull();
});

test('user cannot access another users grocery session', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($owner)->create();
    $session = GrocerySession::factory()->for($recipe)->for($owner)->create();

    $this->actingAs($other)->get("/grocery/{$session->id}")->assertForbidden();
});

test('history exposes both cook and grocery sessions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    GrocerySession::factory()->for($recipe)->for($user)->completed()->create();

    $this->actingAs($user)
        ->get('/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('History')
            ->has('grocerySessions', 1)
        );
});

test('active grocery session is shared via inertia props', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    GrocerySession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('activeGrocerySession.id')
            ->where('activeGrocerySession.phase', 'home')
        );
});

test('destroying a grocery session removes it', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = GrocerySession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->delete("/grocery/{$session->id}")
        ->assertRedirect();

    expect(GrocerySession::query()->where('id', $session->id)->exists())->toBeFalse();
});
