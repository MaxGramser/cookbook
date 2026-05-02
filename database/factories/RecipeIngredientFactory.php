<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'section' => null,
            'position' => 1,
            'name' => fake()->word(),
            'quantity' => fake()->numberBetween(1, 500),
            'unit' => fake()->randomElement(['g', 'ml', 'tsp', 'tbsp', 'piece']),
            'raw_text' => null,
        ];
    }
}
