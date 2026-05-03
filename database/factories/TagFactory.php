<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'group' => fake()->randomElement(Tag::GROUPS),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'name' => ucfirst($name),
            'color' => fake()->randomElement(['cream', 'lime', 'pink', 'sky', 'accent', 'ink']),
            'sort' => fake()->numberBetween(1, 999),
            'is_system' => false,
            'user_id' => null,
        ];
    }

    public function system(): self
    {
        return $this->state(fn () => ['is_system' => true, 'user_id' => null]);
    }

    public function group(string $group): self
    {
        return $this->state(fn () => ['group' => $group]);
    }
}
