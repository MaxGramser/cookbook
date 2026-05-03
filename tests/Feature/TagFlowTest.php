<?php

use App\Ai\Agents\RecipeExtractor;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TagSeeder::class);
});

test('store accepts tag_ids and attaches them to the recipe', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $lunch = Tag::query()->where('group', 'meal_type')->where('slug', 'lunch')->firstOrFail();
    $italian = Tag::query()->where('group', 'cuisine')->where('slug', 'italiaans')->firstOrFail();

    $this->actingAs($user)->post('/recipes', [
        'title' => 'Pasta',
        'servings' => 2,
        'ingredients' => [['quantity_text' => '200', 'unit_text' => 'g', 'name' => 'pasta']],
        'steps' => [['body' => 'Kook pasta.']],
        'tag_ids' => [$lunch->id, $italian->id],
    ])->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Pasta');
    expect($recipe->tags->pluck('slug')->all())->toEqualCanonicalizing(['lunch', 'italiaans']);
});

test('store rejects tag_ids that do not exist', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->post('/recipes', [
        'title' => 'Pasta',
        'servings' => 2,
        'ingredients' => [['quantity_text' => '200', 'unit_text' => 'g', 'name' => 'pasta']],
        'steps' => [['body' => 'Kook.']],
        'tag_ids' => [999_999],
    ])->assertSessionHasErrors('tag_ids.0');
});

test('store strips other users private tags from sync', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $alien = Tag::factory()->state(['user_id' => $other->id, 'is_system' => false, 'group' => 'attribute'])->create();
    $system = Tag::query()->where('group', 'meal_type')->where('slug', 'lunch')->firstOrFail();

    $this->actingAs($owner)->post('/recipes', [
        'title' => 'Pasta',
        'servings' => 2,
        'ingredients' => [['quantity_text' => '200', 'unit_text' => 'g', 'name' => 'pasta']],
        'steps' => [['body' => 'Kook.']],
        'tag_ids' => [$system->id, $alien->id],
    ])->assertRedirect();

    $recipe = Recipe::firstWhere('title', 'Pasta');
    expect($recipe->tags->pluck('id')->all())->toBe([$system->id]);
});

test('user can create a private tag and attach it', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->postJson('/tags', [
        'group' => 'attribute',
        'name' => 'Verjaardagstaart',
    ]);

    $response->assertSuccessful();
    $tag = $response->json('tag');
    expect($tag['slug'])->toBe('verjaardagstaart')
        ->and($tag['is_system'])->toBeFalse();

    $created = Tag::query()->find($tag['id']);
    expect($created->user_id)->toBe($user->id);
});

test('tag listing returns system + own tags but not other users tags', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);

    $own = Tag::factory()->state(['user_id' => $user->id, 'group' => 'attribute', 'is_system' => false])->create(['name' => 'Mijn tag', 'slug' => 'mijn-tag-x']);
    $alien = Tag::factory()->state(['user_id' => $other->id, 'group' => 'attribute', 'is_system' => false])->create(['name' => 'Ander', 'slug' => 'ander-x']);

    $response = $this->actingAs($user)->getJson('/tags');
    $ids = collect($response->json('tags'))->pluck('id')->all();

    expect($ids)->toContain($own->id)->not->toContain($alien->id);
});

test('search filter combines AND across groups, OR within group', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $lunch = Tag::query()->where('slug', 'lunch')->firstOrFail();
    $avond = Tag::query()->where('slug', 'avondeten')->firstOrFail();
    $italiaans = Tag::query()->where('slug', 'italiaans')->firstOrFail();
    $frans = Tag::query()->where('slug', 'frans')->firstOrFail();

    $italianLunch = Recipe::factory()->for($user)->create(['title' => 'It Lunch']);
    $italianLunch->tags()->attach([$lunch->id, $italiaans->id]);

    $italianDinner = Recipe::factory()->for($user)->create(['title' => 'It Dinner']);
    $italianDinner->tags()->attach([$avond->id, $italiaans->id]);

    $frenchDinner = Recipe::factory()->for($user)->create(['title' => 'Fr Dinner']);
    $frenchDinner->tags()->attach([$avond->id, $frans->id]);

    // Filter: meal IN (lunch, avondeten) AND cuisine = italiaans
    // Should return: italianLunch + italianDinner, NOT frenchDinner.
    $response = $this->actingAs($user)->get('/recipes?tags='.$lunch->id.','.$avond->id.','.$italiaans->id);
    $response->assertSuccessful();

    $titles = collect($response->viewData('page')['props']['recipes']['data'])->pluck('title')->all();
    expect($titles)->toContain('It Lunch', 'It Dinner')->not->toContain('Fr Dinner');
});

test('LLM-extracted tag slugs are attached to imported recipe', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('<html><body>doc</body></html>', 200)]);

    RecipeExtractor::fake([
        [
            'title' => 'Pasta Carbonara',
            'source_locale' => 'metric',
            'servings' => 2,
            'cook_time_minutes' => 25,
            'meal_types' => ['avondeten'],
            'cuisines' => ['italiaans'],
            'attributes' => ['snel-en-makkelijk', 'comfort-food', 'unknown-slug'],
            'ingredients' => [
                ['quantity_text' => '200', 'unit_text' => 'g', 'name' => 'spaghetti'],
            ],
            'steps' => [['body' => 'Kook pasta.']],
        ],
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->post('/recipes/import', ['url' => 'https://example.com/c'])->assertRedirect();

    $recipe = Recipe::firstWhere('source_url', 'https://example.com/c');
    expect($recipe->tags->pluck('slug')->all())
        ->toEqualCanonicalizing(['avondeten', 'italiaans', 'snel-en-makkelijk', 'comfort-food']);
});
