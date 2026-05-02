<?php

use App\Ai\Agents\RecipeExtractor;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('importing a recipe URL stores a metric recipe and downloads its image', function () {
    Storage::fake('public');

    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => 'Pancakes',
        'recipeYield' => '4',
        'image' => 'https://example.com/p.jpg',
        'recipeIngredient' => ['1 cup flour', '2 cups milk', '1 ui'],
        'recipeInstructions' => [
            ['@type' => 'HowToStep', 'text' => 'Mix.'],
            ['@type' => 'HowToStep', 'text' => 'Cook.'],
        ],
    ]);
    $html = "<html><body><script type=\"application/ld+json\">{$jsonLd}</script></body></html>";

    Http::fake([
        'example.com/recipe' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        'example.com/p.jpg' => Http::response('IMGBYTES', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    RecipeExtractor::fake([
        [
            'title' => 'Pancakes',
            'servings' => 4,
            'cook_time_minutes' => 20,
            'image_url' => 'https://example.com/p.jpg',
            'ingredients' => [
                ['quantity_text' => '1', 'unit_text' => 'cup', 'name' => 'flour'],
                ['quantity_text' => '2', 'unit_text' => 'cups', 'name' => 'milk'],
                ['quantity_text' => '1', 'unit_text' => '', 'name' => 'ui'],
            ],
            'steps' => ['Mix.', 'Cook.'],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->post('/recipes/import', [
        'url' => 'https://example.com/recipe',
    ]);

    $response->assertRedirect();

    $recipe = Recipe::where('source_url', 'https://example.com/recipe')->first();
    expect($recipe)->not->toBeNull();
    expect($recipe->title)->toBe('Pancakes');
    expect($recipe->servings)->toBe(4);
    expect($recipe->cook_time_minutes)->toBe(20);
    expect($recipe->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($recipe->image_path);

    $ingredients = $recipe->ingredients;
    expect($ingredients)->toHaveCount(3);
    expect($ingredients[0]->unit)->toBe('ml');
    expect($ingredients[0]->quantity)->toBe(237.0);
    expect($ingredients[1]->unit)->toBe('ml');
    expect($ingredients[1]->quantity)->toBe(473.0);
    expect($ingredients[2]->unit)->toBe('piece');
    expect($ingredients[2]->name)->toBe('ui');

    expect($recipe->steps)->toHaveCount(2);

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://example.com/recipe');
});

test('import fails gracefully when URL returns error', function () {
    Http::fake([
        'example.com/*' => Http::response('not found', 404),
    ]);
    RecipeExtractor::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->post('/recipes/import', [
        'url' => 'https://example.com/missing',
    ]);

    $response->assertSessionHasErrors('url');
    expect(Recipe::count())->toBe(0);
});
