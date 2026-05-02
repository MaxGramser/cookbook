<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\User;
use App\Support\Units\IngredientNormalizer;
use App\Support\Units\UnitConverter;
use Illuminate\Support\Facades\DB;

/**
 * Save the LLM's extracted-recipe payload to the database. Shared by both
 * URL-based imports and free-text/paste imports.
 */
final class PersistExtractedRecipe
{
    /**
     * The `$extracted` payload is whatever the AI SDK's `prompt()` returns —
     * either a plain array or an array-accessible response object. We access
     * fields with `[]` and `??` so both shapes work.
     *
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>  $extracted
     */
    public function handle(
        User $user,
        mixed $extracted,
        ?string $sourceUrl = null,
        ?string $imagePath = null,
    ): Recipe {
        $locale = self::resolveLocale($extracted['source_locale'] ?? null);

        return DB::transaction(function () use ($user, $extracted, $sourceUrl, $imagePath, $locale) {
            $recipe = $user->recipes()->create([
                'title' => $extracted['title'] ?? 'Recept zonder titel',
                'source_url' => $sourceUrl,
                'servings' => max(1, (int) ($extracted['servings'] ?? 0) ?: 1),
                'cook_time_minutes' => isset($extracted['cook_time_minutes']) && (int) $extracted['cook_time_minutes'] > 0
                    ? (int) $extracted['cook_time_minutes']
                    : null,
                'image_path' => $imagePath,
            ]);

            $position = 0;
            foreach ((array) ($extracted['ingredients'] ?? []) as $row) {
                $position++;
                $normalized = IngredientNormalizer::fromParts(
                    $row['quantity_text'] ?? null,
                    $row['unit_text'] ?? null,
                    (string) ($row['name'] ?? ''),
                    self::buildRawText($row),
                    $locale,
                );
                if ($normalized['name'] === '') {
                    continue;
                }
                $recipe->ingredients()->create([
                    'section' => self::cleanSection($row['section'] ?? null),
                    'position' => $position,
                    'name' => $normalized['name'],
                    'quantity' => $normalized['quantity'],
                    'unit' => $normalized['unit'],
                    'raw_text' => $normalized['raw_text'],
                ]);
            }

            $position = 0;
            foreach ((array) ($extracted['steps'] ?? []) as $row) {
                $body = is_string($row)
                    ? trim($row)
                    : trim((string) ($row['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $position++;
                $recipe->steps()->create([
                    'section' => is_array($row) ? self::cleanSection($row['section'] ?? null) : null,
                    'position' => $position,
                    'body' => $body,
                ]);
            }

            return $recipe;
        });
    }

    private static function cleanSection(mixed $section): ?string
    {
        if (! is_string($section)) {
            return null;
        }
        $trimmed = trim($section);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function resolveLocale(mixed $value): string
    {
        if (is_string($value) && UnitConverter::isLocale($value)) {
            return $value;
        }

        return UnitConverter::LOCALE_US;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function buildRawText(array $row): ?string
    {
        $parts = array_filter([
            isset($row['quantity_text']) ? (string) $row['quantity_text'] : null,
            isset($row['unit_text']) ? (string) $row['unit_text'] : null,
            isset($row['name']) ? (string) $row['name'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }
}
