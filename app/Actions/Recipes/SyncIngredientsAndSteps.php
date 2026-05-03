<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Support\Recipes\TimerParser;
use App\Support\Units\IngredientNormalizer;

final class SyncIngredientsAndSteps
{
    /**
     * @param  array<int, array{section?: ?string, quantity_text?: ?string, unit_text?: ?string, name: string, raw_text?: ?string}>  $ingredients
     * @param  array<int, array{section?: ?string, body: string, timer_minutes?: int|string|null}>  $steps
     */
    public function handle(Recipe $recipe, array $ingredients, array $steps): void
    {
        foreach (array_values($ingredients) as $i => $row) {
            $normalized = IngredientNormalizer::fromParts(
                $row['quantity_text'] ?? null,
                $row['unit_text'] ?? null,
                $row['name'],
                $row['raw_text'] ?? null,
            );

            $recipe->ingredients()->create([
                'section' => self::normalizeSection($row['section'] ?? null),
                'position' => $i + 1,
                'name' => $normalized['name'],
                'quantity' => $normalized['quantity'],
                'unit' => $normalized['unit'],
                'raw_text' => $normalized['raw_text'],
            ]);
        }

        foreach (array_values($steps) as $i => $row) {
            $explicit = self::normalizeTimerMinutes($row['timer_minutes'] ?? null);
            $recipe->steps()->create([
                'section' => self::normalizeSection($row['section'] ?? null),
                'position' => $i + 1,
                'body' => $row['body'],
                'timer_minutes' => $explicit ?? TimerParser::extractMinutes($row['body']),
            ]);
        }
    }

    private static function normalizeTimerMinutes(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $minutes = (int) $value;

        return $minutes >= 1 ? $minutes : null;
    }

    private static function normalizeSection(?string $section): ?string
    {
        if ($section === null) {
            return null;
        }
        $trimmed = trim($section);

        return $trimmed === '' ? null : $trimmed;
    }
}
