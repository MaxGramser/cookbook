<?php

namespace App\Support\Units;

final class UnitParser
{
    private const UNICODE_FRACTIONS = [
        '½' => 0.5, '⅓' => 1 / 3, '⅔' => 2 / 3, '¼' => 0.25, '¾' => 0.75,
        '⅕' => 0.2, '⅖' => 0.4, '⅗' => 0.6, '⅘' => 0.8,
        '⅙' => 1 / 6, '⅚' => 5 / 6, '⅐' => 1 / 7, '⅛' => 0.125,
        '⅜' => 0.375, '⅝' => 0.625, '⅞' => 0.875, '⅑' => 1 / 9, '⅒' => 0.1,
    ];

    /**
     * Aliases for raw units. Keys are lowercased input, values are intermediate
     * canonical names that {@see UnitConverter} understands.
     *
     * @var array<string, string>
     */
    private const UNIT_ALIASES = [
        // grams
        'g' => 'g', 'gr' => 'g', 'gram' => 'g', 'grams' => 'g',
        'gramme' => 'g', 'grammes' => 'g',
        // kilograms
        'kg' => 'kg', 'kgs' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
        'kilogram' => 'kg', 'kilograms' => 'kg',
        // milligrams (rare, but)
        'mg' => 'mg', 'milligram' => 'mg', 'milligrams' => 'mg',
        // milliliters
        'ml' => 'ml', 'milliliter' => 'ml', 'milliliters' => 'ml',
        'millilitre' => 'ml', 'millilitres' => 'ml',
        // liters
        'l' => 'l', 'liter' => 'l', 'liters' => 'l', 'litre' => 'l', 'litres' => 'l',
        // deci/centi-liters
        'dl' => 'dl', 'deciliter' => 'dl', 'deciliters' => 'dl',
        'cl' => 'cl', 'centiliter' => 'cl', 'centiliters' => 'cl',
        // teaspoons (NL: theelepel)
        'tsp' => 'tsp', 'teaspoon' => 'tsp', 'teaspoons' => 'tsp',
        'tl' => 'tsp', 'theelepel' => 'tsp', 'theelepels' => 'tsp',
        // tablespoons (NL: eetlepel)
        'tbsp' => 'tbsp', 'tbs' => 'tbsp', 'tbspn' => 'tbsp',
        'tablespoon' => 'tbsp', 'tablespoons' => 'tbsp',
        'el' => 'tbsp', 'eetlepel' => 'tbsp', 'eetlepels' => 'tbsp',
        // cups
        'cup' => 'cup', 'cups' => 'cup',
        // ounces (weight)
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
        // pounds
        'lb' => 'lb', 'lbs' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
        // fluid ounces
        'fl oz' => 'fl_oz', 'floz' => 'fl_oz', 'fl. oz' => 'fl_oz',
        'fl. oz.' => 'fl_oz', 'fluid ounce' => 'fl_oz', 'fluid ounces' => 'fl_oz',
        // pints / quarts / gallons
        'pint' => 'pint', 'pints' => 'pint', 'pt' => 'pint',
        'quart' => 'quart', 'quarts' => 'quart', 'qt' => 'quart',
        'gallon' => 'gallon', 'gallons' => 'gallon', 'gal' => 'gallon',
        // pieces
        'piece' => 'piece', 'pieces' => 'piece', 'whole' => 'piece',
        'stuk' => 'piece', 'stuks' => 'piece', 'st' => 'piece',
        'st.' => 'piece', 'pcs' => 'piece', 'pc' => 'piece',
        // sticks (US butter convention: 1 stick = 113 g)
        'stick' => 'stick', 'sticks' => 'stick',
    ];

    /**
     * Parse a free-form quantity string into a numeric value. Supports
     * integers, decimals (with `.` or `,`), simple fractions ("1/2"),
     * mixed numbers ("1 1/2"), unicode fractions ("½", "1½"),
     * and ranges ("2-3" → midpoint).
     *
     * Returns null when no number could be parsed.
     */
    public static function parseQuantity(?string $input): ?float
    {
        if ($input === null) {
            return null;
        }

        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $input = self::normalizeUnicodeFractionsToInline($input);

        // Range: take midpoint. Prefer mixed-number interpretation when the
        // hyphen actually glues an integer to a fraction (e.g. "1-1/2") and
        // the "range" would otherwise descend (1 → 0.5).
        foreach (['–', '—', '-', ' to ', ' tot '] as $sep) {
            if (str_contains($input, $sep)) {
                $parts = array_map('trim', explode($sep, $input, 2));
                if (count($parts) === 2) {
                    $low = self::parseSingleQuantity($parts[0]);
                    $high = self::parseSingleQuantity($parts[1]);
                    if ($low !== null && $high !== null) {
                        if ($sep === '-' && $low > $high && self::looksLikeFraction($parts[1])) {
                            return $low + $high;
                        }

                        return ($low + $high) / 2;
                    }
                    if ($low !== null) {
                        return $low;
                    }
                }
            }
        }

        return self::parseSingleQuantity($input);
    }

    /**
     * Parse a non-range quantity. Handles mixed numbers and fractions.
     */
    private static function parseSingleQuantity(string $input): ?float
    {
        $input = trim(str_replace(',', '.', $input));
        if ($input === '') {
            return null;
        }

        // Mixed number: "1 1/2"
        if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)$/', $input, $m)) {
            $denominator = (int) $m[3];
            if ($denominator === 0) {
                return null;
            }

            return (float) $m[1] + ((float) $m[2] / $denominator);
        }

        // Pure fraction: "1/2"
        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $input, $m)) {
            $denominator = (int) $m[2];
            if ($denominator === 0) {
                return null;
            }

            return (float) $m[1] / $denominator;
        }

        // Plain number (with optional decimal)
        if (preg_match('/^\d+(?:\.\d+)?$/', $input)) {
            return (float) $input;
        }

        // Embedded number anywhere — last-resort fallback, e.g. "approx. 3 large"
        if (preg_match('/(\d+(?:\.\d+)?)/', $input, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private static function looksLikeFraction(string $candidate): bool
    {
        return (bool) preg_match('/^\d+\s*\/\s*\d+$/', trim($candidate));
    }

    /**
     * Replace inline unicode fractions with their decimal value. "1½" → "1.5".
     */
    private static function normalizeUnicodeFractionsToInline(string $input): string
    {
        foreach (self::UNICODE_FRACTIONS as $glyph => $value) {
            if (str_contains($input, $glyph)) {
                // glue to preceding digit: "1½" → "1.5", "½" → "0.5"
                $valueStr = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
                $input = preg_replace(
                    '/(\d)\s*'.preg_quote($glyph, '/').'/u',
                    '$1+'.$valueStr,
                    $input,
                );
                $input = str_replace($glyph, $valueStr, $input);
            }
        }

        // Collapse "1+0.5" produced above into "1.5"
        if (preg_match_all('/(\d+(?:\.\d+)?)\+(\d+(?:\.\d+)?)/', $input, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $sum = (float) $m[1] + (float) $m[2];
                $input = str_replace($m[0], (string) $sum, $input);
            }
        }

        return $input;
    }

    /**
     * Normalize a raw unit string (e.g. "tbsp", "EL", "cups") into an internal
     * canonical alias understood by {@see UnitConverter}. Returns null for
     * unrecognized units.
     */
    public static function normalizeUnit(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $key = strtolower(trim($raw));
        $key = rtrim($key, '.');
        $key = preg_replace('/\s+/', ' ', $key);

        if ($key === '') {
            return null;
        }

        return self::UNIT_ALIASES[$key] ?? null;
    }
}
