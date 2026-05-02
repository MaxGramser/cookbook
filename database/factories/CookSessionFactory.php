<?php

namespace Database\Factories;

use App\Models\CookSession;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CookSession>
 */
class CookSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'user_id' => User::factory(),
            'servings_multiplier' => 1,
            'notes' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(['completed_at' => now()]);
    }
}
