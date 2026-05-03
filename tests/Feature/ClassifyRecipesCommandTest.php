<?php

use App\Ai\Agents\TagClassifier;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TagSeeder::class);
});

test('classify command attaches LLM tags to untagged recipes', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $recipe = Recipe::factory()->for($user)->create(['title' => 'Pasta Carbonara']);
    $recipe->ingredients()->create(['position' => 1, 'name' => 'spaghetti', 'quantity' => 200, 'unit' => 'g']);
    $recipe->steps()->create(['position' => 1, 'body' => 'Kook pasta.']);

    TagClassifier::fake([
        [
            'meal_types' => ['avondeten'],
            'cuisines' => ['italiaans'],
            'attributes' => ['snel-en-makkelijk'],
        ],
    ]);

    $this->artisan('recipes:classify')->assertSuccessful();

    expect($recipe->fresh()->tags->pluck('slug')->all())
        ->toEqualCanonicalizing(['avondeten', 'italiaans', 'snel-en-makkelijk']);
});

test('classify command skips already-tagged recipes by default', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $tagged = Recipe::factory()->for($user)->create(['title' => 'Has tags']);
    $tagged->ingredients()->create(['position' => 1, 'name' => 'foo', 'quantity' => 1, 'unit' => 'g']);
    $tagged->steps()->create(['position' => 1, 'body' => 'doe iets']);
    $existingTag = Tag::query()->where('slug', 'lunch')->firstOrFail();
    $tagged->tags()->attach($existingTag->id);

    $untagged = Recipe::factory()->for($user)->create(['title' => 'No tags']);
    $untagged->ingredients()->create(['position' => 1, 'name' => 'bar', 'quantity' => 1, 'unit' => 'g']);
    $untagged->steps()->create(['position' => 1, 'body' => 'doe iets']);

    TagClassifier::fake([
        [
            'meal_types' => ['avondeten'],
            'cuisines' => [],
            'attributes' => [],
        ],
    ]);

    $this->artisan('recipes:classify')->assertSuccessful();

    expect($tagged->fresh()->tags->pluck('slug')->all())->toBe(['lunch']);
    expect($untagged->fresh()->tags->pluck('slug')->all())->toBe(['avondeten']);
});

test('classify --user limits to one user', function () {
    $alice = User::factory()->create(['email_verified_at' => now(), 'email' => 'alice@example.com']);
    $bob = User::factory()->create(['email_verified_at' => now(), 'email' => 'bob@example.com']);

    $aliceRecipe = Recipe::factory()->for($alice)->create();
    $aliceRecipe->ingredients()->create(['position' => 1, 'name' => 'a', 'quantity' => 1, 'unit' => 'g']);
    $aliceRecipe->steps()->create(['position' => 1, 'body' => 'a']);

    $bobRecipe = Recipe::factory()->for($bob)->create();
    $bobRecipe->ingredients()->create(['position' => 1, 'name' => 'b', 'quantity' => 1, 'unit' => 'g']);
    $bobRecipe->steps()->create(['position' => 1, 'body' => 'b']);

    TagClassifier::fake([
        ['meal_types' => ['lunch'], 'cuisines' => [], 'attributes' => []],
    ]);

    $this->artisan('recipes:classify', ['--user' => 'alice@example.com'])->assertSuccessful();

    expect($aliceRecipe->fresh()->tags)->toHaveCount(1);
    expect($bobRecipe->fresh()->tags)->toHaveCount(0);
});

test('classify ignores unknown slugs from the LLM', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    $recipe->ingredients()->create(['position' => 1, 'name' => 'x', 'quantity' => 1, 'unit' => 'g']);
    $recipe->steps()->create(['position' => 1, 'body' => 'doe']);

    TagClassifier::fake([
        [
            'meal_types' => ['avondeten', 'verzonnen-slug'],
            'cuisines' => ['italiaans', 'klingons'],
            'attributes' => [],
        ],
    ]);

    $this->artisan('recipes:classify')->assertSuccessful();

    expect($recipe->fresh()->tags->pluck('slug')->all())
        ->toEqualCanonicalizing(['avondeten', 'italiaans']);
});
