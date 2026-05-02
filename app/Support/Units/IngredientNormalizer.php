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
     * default to "piece" (e.g. "1 ui" → 1 piece onion). The optional
     * `$locale` controls how volumetric units are converted (cup/pint differ
     * between us/au/uk/metric).
     *
     * @return array{quantity: ?float, unit: ?string, name: string, raw_text: ?string}
     */
    public static function fromParts(
        ?string $quantityText,
        ?string $unitText,
        string $name,
        ?string $rawText = null,
        string $locale = UnitConverter::LOCALE_US,
    ): array {
        $quantity = UnitParser::parseQuantity($quantityText);
        $unit = UnitParser::normalizeUnit($unitText);

        if ($unit === null && $quantity !== null && $name !== '') {
            $unit = 'piece';
        }

        $converted = UnitConverter::toMetric($quantity, $unit, $locale);

        return [
            'quantity' => $converted['quantity'],
            'unit' => $converted['unit'],
            'name' => trim($name),
            'raw_text' => $rawText,
        ];
    }
}
