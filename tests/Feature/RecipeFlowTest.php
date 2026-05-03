<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can store a recipe with metric ingredients', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->post('/recipes', [
        'title' => 'Pannenkoeken',
        'servings' => 2,
        'cook_time_minutes' => 20,
        'ingredients' => [
            ['quantity_text' => '200', 'unit_text' => 'g', 'name' => 'bloem'],
            ['quantity_text' => '1', 'unit_text' => 'l', 'name' => 'melk'],
            ['quantity_text' => '2', 'unit_text' => '', 'name' => 'eieren'],
            ['quantity_text' => '1', 'unit_text' => 'el', 'name' => 'suiker'],
        ],
        'steps' => [
            ['body' => 'Meng bloem en melk.'],
            ['body' => 'Bak de pannenkoeken.'],
        ],
    ]);

    $response->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Pannenkoeken');
    expect($recipe)->not->toBeNull();
    expect($recipe->ingredients)->toHaveCount(4);
    expect($recipe->ingredients[0]->unit)->toBe('g');
    expect($recipe->ingredients[0]->quantity)->toBe(200.0);
    expect($recipe->ingredients[1]->unit)->toBe('ml');
    expect($recipe->ingredients[1]->quantity)->toBe(1000.0);
    expect($recipe->ingredients[2]->unit)->toBe('piece');
    expect($recipe->ingredients[3]->unit)->toBe('tbsp');
    expect($recipe->steps)->toHaveCount(2);
});

test('explicit step timer is persisted, otherwise inferred from body', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post('/recipes', [
        'title' => 'Stoofpot',
        'servings' => 4,
        'ingredients' => [['quantity_text' => '1', 'unit_text' => 'kg', 'name' => 'rundvlees']],
        'steps' => [
            ['body' => 'Aanbraden.', 'timer_minutes' => 7],          // explicit wins
            ['body' => 'Sudderen op laag vuur, 2 uur.'],              // inferred from body
            ['body' => 'Wacht een kwartier voor je serveert.'],       // fixed phrase
            ['body' => 'Serveer warm.'],                              // no timer at all
        ],
    ])->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Stoofpot');
    expect($recipe->steps[0]->timer_minutes)->toBe(7)
        ->and($recipe->steps[1]->timer_minutes)->toBe(120)
        ->and($recipe->steps[2]->timer_minutes)->toBe(15)
        ->and($recipe->steps[3]->timer_minutes)->toBeNull();
});

test('US units are converted to metric on store', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post('/recipes', [
        'title' => 'Pancakes',
        'servings' => 2,
        'ingredients' => [
            ['quantity_text' => '1', 'unit_text' => 'cup', 'name' => 'flour'],
            ['quantity_text' => '1/2', 'unit_text' => 'lb', 'name' => 'butter'],
        ],
        'steps' => [['body' => 'Mix.']],
    ])->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Pancakes');
    expect($recipe->ingredients[0]->unit)->toBe('ml');
    expect($recipe->ingredients[0]->quantity)->toBe(237.0);
    expect($recipe->ingredients[1]->unit)->toBe('g');
    expect($recipe->ingredients[1]->quantity)->toBe(227.0);
});

test('user cannot view another users recipe', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($owner)->create();

    $this->actingAs($other)->get("/recipes/{$recipe->id}")->assertForbidden();
});
