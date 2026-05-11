<?php

use App\Http\Controllers\PublicRecipeController;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeShare;
use App\Models\RecipeStep;
use App\Models\Shortlist;
use App\Models\ShortlistShare;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('generating a recipe share link creates a fresh token with a 30 day expiry and revokes old shares', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();

    $stale = RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'old-recipe-token',
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post("/recipes/{$recipe->id}/share")
        ->assertRedirect();

    expect(RecipeShare::query()->where('id', $stale->id)->exists())->toBeFalse();
    $fresh = RecipeShare::query()->where('recipe_id', $recipe->id)->first();
    expect($fresh)->not->toBeNull();
    expect($fresh->token)->not->toBe('old-recipe-token');
    expect($fresh->expires_at->between(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});

test('the recipe show page exposes the active share', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'shown-token',
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('recipe.active_share.token', 'shown-token')
        );
});

test('explicit unshare removes all recipe shares', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'gone',
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->delete("/recipes/{$recipe->id}/share")
        ->assertRedirect();

    expect($recipe->shares()->count())->toBe(0);
});

test('a guest can view a shared recipe via its token', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create(['title' => 'Carbonara']);
    RecipeIngredient::factory()->for($recipe)->create();
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'view-token',
        'expires_at' => now()->addDays(30),
    ]);

    $this->get('/share/recipe/view-token')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('share/PublicRecipe')
            ->where('recipe.title', 'Carbonara')
            ->has('recipe.ingredients', 1)
        );
});

test('an expired recipe share renders the expired page with a 410 status', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($user)->create();
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'expired-token',
        'expires_at' => now()->subMinute(),
    ]);

    $this->get('/share/recipe/expired-token')
        ->assertStatus(410)
        ->assertInertia(fn ($page) => $page->component('share/Expired'));
});

test('an authenticated user can copy a shared recipe into their own cookbook', function () {
    Storage::fake('public');
    Storage::disk('public')->put('recipes/source.webp', 'fake-image-bytes');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);

    $recipe = Recipe::factory()->for($owner)->create([
        'title' => 'Risotto',
        'image_path' => 'recipes/source.webp',
        'servings' => 4,
        'cook_time_minutes' => 35,
        'notes' => 'voorzichtig roeren',
    ]);
    RecipeIngredient::factory()->for($recipe)->create(['name' => 'Arborio rijst', 'position' => 1]);
    RecipeStep::factory()->for($recipe)->create(['body' => 'Snipper de ui', 'position' => 1]);

    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'copy-token',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($recipient)
        ->post('/share/recipe/copy-token/copy');

    $copy = Recipe::query()
        ->where('user_id', $recipient->id)
        ->where('id', '!=', $recipe->id)
        ->first();

    expect($copy)->not->toBeNull();
    $response->assertRedirect("/recipes/{$copy->id}");

    expect($copy->title)->toBe('Risotto');
    expect($copy->servings)->toBe(4);
    expect($copy->cook_time_minutes)->toBe(35);
    expect($copy->notes)->toBe('voorzichtig roeren');
    expect($copy->ingredients()->pluck('name'))->toContain('Arborio rijst');
    expect($copy->steps()->pluck('body'))->toContain('Snipper de ui');

    expect($copy->image_path)->not->toBeNull();
    expect($copy->image_path)->not->toBe('recipes/source.webp');
    Storage::disk('public')->assertExists($copy->image_path);
});

test('a guest who clicks copy is sent to register and the token is stored in the session', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $recipe = Recipe::factory()->for($owner)->create();
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'pending-token',
        'expires_at' => now()->addDays(30),
    ]);

    $this->post('/share/recipe/pending-token/copy')
        ->assertRedirect('/register')
        ->assertSessionHas(PublicRecipeController::PENDING_SESSION_KEY, 'pending-token');
});

test('the claim route completes a pending copy after the user authenticates', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);

    $recipe = Recipe::factory()->for($owner)->create(['title' => 'Tarte tatin']);
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'pending-token',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($recipient)
        ->withSession([PublicRecipeController::PENDING_SESSION_KEY => 'pending-token'])
        ->get('/share/recipe/claim/pending');

    $copy = Recipe::query()
        ->where('user_id', $recipient->id)
        ->where('id', '!=', $recipe->id)
        ->first();

    expect($copy)->not->toBeNull();
    expect($copy->title)->toBe('Tarte tatin');
    $response->assertRedirect("/recipes/{$copy->id}");

    $this->assertFalse(session()->has(PublicRecipeController::PENDING_SESSION_KEY));
});

test('the claim route falls back to the dashboard when there is no pending token', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/share/recipe/claim/pending')
        ->assertRedirect('/dashboard');
});

test('duplicating a recipe maps system tags directly and recreates user tags for the recipient', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);

    $systemTag = Tag::create([
        'group' => Tag::GROUP_CUISINE,
        'slug' => 'italiaans',
        'name' => 'Italiaans',
        'color' => 'lime',
        'sort' => 0,
        'is_system' => true,
    ]);

    $ownerTag = Tag::create([
        'group' => Tag::GROUP_ATTRIBUTE,
        'slug' => 'snel',
        'name' => 'Snel',
        'color' => 'sky',
        'sort' => 0,
        'is_system' => false,
        'user_id' => $owner->id,
    ]);

    $recipe = Recipe::factory()->for($owner)->create();
    $recipe->tags()->sync([$systemTag->id, $ownerTag->id]);
    RecipeShare::create([
        'recipe_id' => $recipe->id,
        'token' => 'tag-token',
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($recipient)
        ->post('/share/recipe/tag-token/copy');

    $copy = Recipe::query()
        ->where('user_id', $recipient->id)
        ->where('id', '!=', $recipe->id)
        ->firstOrFail();

    $copyTagIds = $copy->tags()->pluck('tags.id')->all();
    expect($copyTagIds)->toContain($systemTag->id);
    expect($copyTagIds)->not->toContain($ownerTag->id);

    $recreated = Tag::query()
        ->where('user_id', $recipient->id)
        ->where('name', 'Snel')
        ->first();

    expect($recreated)->not->toBeNull();
    expect($recreated->is_system)->toBeFalse();
    expect($copyTagIds)->toContain($recreated->id);
});

test('copying a recipe from a shared shortlist works for an authenticated user', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($owner)->create();
    $recipe = Recipe::factory()->for($owner)->create(['title' => 'Lasagne']);
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    ShortlistShare::create([
        'shortlist_id' => $shortlist->id,
        'token' => 'sl-token',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($recipient)
        ->post("/share/sl-token/recipes/{$recipe->id}/copy");

    $copy = Recipe::query()
        ->where('user_id', $recipient->id)
        ->where('id', '!=', $recipe->id)
        ->firstOrFail();

    expect($copy->title)->toBe('Lasagne');
    $response->assertRedirect("/recipes/{$copy->id}");
});

test('copying a recipe from a shared shortlist as a guest creates a transient share and redirects to register', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $shortlist = Shortlist::factory()->for($owner)->create();
    $recipe = Recipe::factory()->for($owner)->create();
    $shortlist->recipes()->attach($recipe->id, ['position' => 0]);

    ShortlistShare::create([
        'shortlist_id' => $shortlist->id,
        'token' => 'sl-guest-token',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->post("/share/sl-guest-token/recipes/{$recipe->id}/copy");

    $response->assertRedirect('/register');

    $token = session(PublicRecipeController::PENDING_SESSION_KEY);
    expect($token)->toBeString();
    expect(RecipeShare::query()->where('token', $token)->where('recipe_id', $recipe->id)->exists())->toBeTrue();
});
