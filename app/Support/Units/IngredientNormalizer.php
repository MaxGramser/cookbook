<?php

namespace App\Support\Units;

/**
 * Combines parsing + conversion. Given a structured ingredient (as the LLM
 * returns it) or a raw line, produce a normalized record ready for storage.
 */
final class IngredientNormalizer
{
    /**
     * Normalize from already-split parts (typical LLM output).
     *
     * The `name` is inspected to decide whether a unit-less quantity should
     * default to "piece" (e.g. "1 ui" → 1 piece onion).
     *
     * @return array{quantity: ?float, unit: ?string, name: string, raw_text: ?string}
     */
    public static function fromParts(?string $quantityText, ?string $unitText, string $name, ?string $rawText = null): array
    {
        $quantity = UnitParser::parseQuantity($quantityText);
        $unit = UnitParser::normalizeUnit($unitText);

        // No explicit unit, but a quantity is given → treat as "piece"
        // (covers "1 ui", "2 eggs", "3 large apples", etc.)
        if ($unit === null && $quantity !== null && $name !== '') {
            $unit = 'piece';
        }

        $converted = UnitConverter::toMetric($quantity, $unit);

        return [
            'quantity' => $converted['quantity'],
            'unit' => $converted['unit'],
            'name' => trim($name),
            'raw_text' => $rawText,
        ];
    }
}
