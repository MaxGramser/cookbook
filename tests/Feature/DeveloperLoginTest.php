<?php

use App\Models\Recipe;
use App\Models\User;

it('creates a developer user, seeds recipes, and logs in on first call', function () {
    expect(User::query()->where('email', 'dev@dev.test')->exists())->toBeFalse();

    $response = $this->post('/dev/login');

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'dev@dev.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);

    expect(Recipe::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0);
});

it('reuses an existing developer user without seeding extra recipes', function () {
    $this->post('/dev/login');
    $user = User::query()->where('email', 'dev@dev.test')->firstOrFail();
    $initialCount = Recipe::query()->where('user_id', $user->id)->count();

    auth()->logout();

    $this->post('/dev/login')->assertRedirect(route('dashboard'));

    expect(Recipe::query()->where('user_id', $user->id)->count())->toBe($initialCount);
});

it('returns 404 outside the local environment', function () {
    app()['env'] = 'production';

    $this->withoutMiddleware()
        ->post('/dev/login')
        ->assertNotFound();
});
