<?php

use App\Ai\Agents\RecipeExtractor;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
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

test('importer preserves section headings on ingredients and steps', function () {
    Storage::fake('public');

    Http::fake([
        'example.com/sectioned' => Http::response(
            '<html><body><script type="application/ld+json">'.json_encode([
                '@type' => 'Recipe',
                'name' => 'Layered Cake',
                'recipeIngredient' => ['200 g flour', '50 g sugar'],
                'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Mix.']],
            ]).'</script></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    RecipeExtractor::fake([
        [
            'title' => 'Layered Cake',
            'servings' => 8,
            'ingredients' => [
                ['section' => 'For the dough', 'quantity_text' => '200', 'unit_text' => 'g', 'name' => 'flour'],
                ['section' => 'For the dough', 'quantity_text' => '2', 'unit_text' => '', 'name' => 'eggs'],
                ['section' => 'For the frosting', 'quantity_text' => '100', 'unit_text' => 'g', 'name' => 'butter'],
            ],
            'steps' => [
                ['section' => 'Dough', 'body' => 'Combine flour and eggs.'],
                ['section' => 'Frosting', 'body' => 'Whip butter.'],
                ['section' => 'Assemble', 'body' => 'Stack and frost.'],
            ],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->post('/recipes/import', ['url' => 'https://example.com/sectioned'])->assertRedirect();

    $recipe = Recipe::firstWhere('source_url', 'https://example.com/sectioned');
    $sections = $recipe->ingredients->pluck('section')->all();
    expect($sections)->toBe(['For the dough', 'For the dough', 'For the frosting']);
    expect($recipe->steps->pluck('section')->all())->toBe(['Dough', 'Frosting', 'Assemble']);
});

test('AU recipes apply 250ml cup via source_locale hint', function () {
    Storage::fake('public');

    Http::fake([
        'recipetineats.com/*' => Http::response('<html><body>Australian recipe</body></html>', 200),
    ]);

    RecipeExtractor::fake([
        [
            'title' => 'Aussie Bickies',
            'source_locale' => 'au',
            'servings' => 12,
            'ingredients' => [
                ['quantity_text' => '1', 'unit_text' => 'cup', 'name' => 'flour'],
                ['quantity_text' => '2', 'unit_text' => 'cups', 'name' => 'milk'],
            ],
            'steps' => [['body' => 'Mix.']],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->post('/recipes/import', [
        'url' => 'https://recipetineats.com/test',
    ])->assertRedirect();

    $recipe = Recipe::firstWhere('source_url', 'https://recipetineats.com/test');
    expect($recipe->ingredients[0]->quantity)->toBe(250.0); // AU cup = 250 ml, not US 237
    expect($recipe->ingredients[1]->quantity)->toBe(500.0);
});

test('US recipes still use 236.588 ml cup by default', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('<html><body>doc</body></html>', 200)]);

    RecipeExtractor::fake([
        [
            'title' => 'NY Pancakes',
            'source_locale' => 'us',
            'servings' => 4,
            'ingredients' => [['quantity_text' => '1', 'unit_text' => 'cup', 'name' => 'flour']],
            'steps' => [['body' => 'Mix.']],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->post('/recipes/import', ['url' => 'https://nytimes.com/r'])->assertRedirect();

    $recipe = Recipe::firstWhere('source_url', 'https://nytimes.com/r');
    expect($recipe->ingredients[0]->quantity)->toBe(237.0);
});

test('stick of butter converts to grams', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('<html><body>doc</body></html>', 200)]);

    RecipeExtractor::fake([
        [
            'title' => 'Cookies',
            'source_locale' => 'us',
            'servings' => 24,
            'ingredients' => [['quantity_text' => '1', 'unit_text' => 'stick', 'name' => 'unsalted butter']],
            'steps' => [['body' => 'Cream.']],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->post('/recipes/import', ['url' => 'https://example.com/c'])->assertRedirect();

    $recipe = Recipe::firstWhere('source_url', 'https://example.com/c');
    expect($recipe->ingredients[0]->unit)->toBe('g');
    expect($recipe->ingredients[0]->quantity)->toBe(113.0);
});

test('text import skips fetching and runs the agent on pasted text', function () {
    Storage::fake('public');

    $caption = <<<'TEXT'
Bananenbrood
4 personen, 60 min

200 g bloem
2 rijpe bananen
100 g suiker
75 g boter

1. Verwarm de oven voor op 180°C.
2. Mix de bloem en suiker.
3. Pureer de bananen erbij.
4. Bak 50 minuten.
TEXT;

    RecipeExtractor::fake([
        [
            'title' => 'Bananenbrood',
            'source_locale' => 'metric',
            'servings' => 4,
            'cook_time_minutes' => 60,
            'ingredients' => [
                ['quantity_text' => '200', 'unit_text' => 'g', 'name' => 'bloem'],
                ['quantity_text' => '2', 'unit_text' => '', 'name' => 'rijpe bananen'],
                ['quantity_text' => '100', 'unit_text' => 'g', 'name' => 'suiker'],
                ['quantity_text' => '75', 'unit_text' => 'g', 'name' => 'boter'],
            ],
            'steps' => [
                ['body' => 'Verwarm de oven voor op 180°C.'],
                ['body' => 'Mix de bloem en suiker.'],
                ['body' => 'Pureer de bananen erbij.'],
                ['body' => 'Bak 50 minuten.'],
            ],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $response = $this->actingAs($user)->post('/recipes/import/text', [
        'text' => $caption,
    ]);

    $response->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Bananenbrood');
    expect($recipe)->not->toBeNull();
    expect($recipe->source_url)->toBeNull();
    expect($recipe->servings)->toBe(4);
    expect($recipe->image_path)->toBeNull();
    expect($recipe->ingredients)->toHaveCount(4);
    expect($recipe->ingredients[1]->unit)->toBe('piece');
    expect($recipe->ingredients[1]->quantity)->toBe(2.0);
    expect($recipe->steps)->toHaveCount(4);
});

test('text import accepts an uploaded image', function () {
    Storage::fake('public');

    RecipeExtractor::fake([
        [
            'title' => 'Test',
            'servings' => 2,
            'ingredients' => [['quantity_text' => '1', 'unit_text' => 'g', 'name' => 'X']],
            'steps' => [['body' => 'Doe iets.']],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $image = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($user)->post('/recipes/import/text', [
        'text' => str_repeat('Test recept met genoeg tekst om validatie te doorstaan. ', 5),
        'image' => $image,
    ])->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Test');
    expect($recipe->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($recipe->image_path);
});

test('text import rejects too-short text', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/recipes/import/text', ['text' => 'kort'])
        ->assertSessionHasErrors('text');
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
