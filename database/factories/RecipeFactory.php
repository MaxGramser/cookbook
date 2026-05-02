<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'source_url' => null,
            'image_path' => null,
            'servings' => fake()->numberBetween(1, 6),
            'cook_time_minutes' => fake()->numberBetween(10, 120),
            'notes' => null,
        ];
    }
}
