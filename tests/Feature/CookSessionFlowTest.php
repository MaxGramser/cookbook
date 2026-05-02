<?php

use App\Models\CookSession;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('starting a cook session creates one', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();

    $this->actingAs($user)
        ->post("/recipes/{$recipe->id}/cook")
        ->assertRedirect();

    expect(CookSession::query()->where('recipe_id', $recipe->id)->count())->toBe(1);
});

test('toggling an ingredient check stores the pivot', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $ingredient = RecipeIngredient::factory()->for($recipe)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->post("/cook/{$session->id}/ingredients/{$ingredient->id}", ['checked' => true])
        ->assertRedirect();

    expect($session->checkedIngredients()->count())->toBe(1);

    $this->actingAs($user)
        ->post("/cook/{$session->id}/ingredients/{$ingredient->id}", ['checked' => false]);

    expect($session->checkedIngredients()->count())->toBe(0);
});

test('toggling a step persists state', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $step = RecipeStep::factory()->for($recipe)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->post("/cook/{$session->id}/steps/{$step->id}", ['checked' => true])
        ->assertRedirect();

    expect($session->checkedSteps()->count())->toBe(1);
});

test('session multiplier and notes can be patched', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->patch("/cook/{$session->id}", ['servings_multiplier' => 2, 'notes' => 'Te zout'])
        ->assertRedirect();

    $session->refresh();
    expect($session->servings_multiplier)->toBe(2.0);
    expect($session->notes)->toBe('Te zout');
});

test('pausing accumulates paused seconds when resumed', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create([
        'started_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user)->post("/cook/{$session->id}/pause")->assertRedirect();

    $session->refresh();
    expect($session->paused_at)->not->toBeNull();

    $this->travel(30)->seconds();
    $this->actingAs($user)->post("/cook/{$session->id}/resume")->assertRedirect();

    $session->refresh();
    expect($session->paused_at)->toBeNull();
    expect($session->paused_seconds)->toBeGreaterThanOrEqual(29);
    expect($session->paused_seconds)->toBeLessThanOrEqual(31);
});

test('completing while paused finalizes paused_seconds', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create([
        'started_at' => now()->subMinutes(10),
        'paused_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($user)->post("/cook/{$session->id}/complete");

    $session->refresh();
    expect($session->paused_at)->toBeNull();
    expect($session->paused_seconds)->toBeGreaterThanOrEqual(118);
    expect($session->completed_at)->not->toBeNull();
});

test('cannot pause a completed session', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->completed()->create();

    $this->actingAs($user)->post("/cook/{$session->id}/pause");
    expect($session->fresh()->paused_at)->toBeNull();
});

test('completing a session marks it as done', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->post("/cook/{$session->id}/complete")
        ->assertRedirect();

    expect($session->fresh()->completed_at)->not->toBeNull();
});

test('history shows completed sessions only', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    CookSession::factory()->for($recipe)->for($user)->completed()->create();
    CookSession::factory()->for($recipe)->for($user)->create();

    $response = $this->actingAs($user)->get('/history');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('History')->has('sessions', 1)
    );
});

test('history payload exposes started_at and completed_at for duration', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    CookSession::factory()->for($recipe)->for($user)->completed()->create([
        'started_at' => now()->subMinutes(45),
    ]);

    $this->actingAs($user)
        ->get('/history')
        ->assertInertia(fn ($page) => $page->has('sessions.0.started_at')
            ->has('sessions.0.completed_at')
        );
});

test('history payload exposes notes for editing', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    CookSession::factory()->for($recipe)->for($user)->completed()->create([
        'notes' => 'Lekker, maar te zout',
    ]);

    $this->actingAs($user)
        ->get('/history')
        ->assertInertia(fn ($page) => $page->where('sessions.0.notes', 'Lekker, maar te zout')
        );
});

test('deleting a completed session redirects to history', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->completed()->create();

    $this->actingAs($user)
        ->delete("/cook/{$session->id}")
        ->assertRedirect('/history');

    expect(CookSession::query()->find($session->id))->toBeNull();
});

test('deleting an in-progress session redirects to recipe', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $session = CookSession::factory()->for($recipe)->for($user)->create();

    $this->actingAs($user)
        ->delete("/cook/{$session->id}")
        ->assertRedirect("/recipes/{$recipe->id}");
});

test('user cannot access another users session', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($owner)->create();
    $session = CookSession::factory()->for($recipe)->for($owner)->create();

    $this->actingAs($other)->get("/cook/{$session->id}")->assertForbidden();
});
