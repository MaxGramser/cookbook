<?php

namespace Database\Factories;

use App\Models\GrocerySession;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrocerySession>
 */
class GrocerySessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'user_id' => User::factory(),
            'phase' => GrocerySession::PHASE_HOME,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function shopping(): self
    {
        return $this->state(['phase' => GrocerySession::PHASE_SHOPPING]);
    }

    public function completed(): self
    {
        return $this->state(['completed_at' => now()]);
    }
}
