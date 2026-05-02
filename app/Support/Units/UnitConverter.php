<?php

namespace App\Support\Units;

final class UnitConverter
{
    public const LOCALE_US = 'us';

    public const LOCALE_UK = 'uk';

    public const LOCALE_AU = 'au';

    public const LOCALE_METRIC = 'metric';

    /**
     * Conversion factors by locale. Locales only differ for volumetric units
     * because cup / pint / quart / gallon / fl-oz vary by country. Weights
     * (oz, lb) are imperial avoirdupois worldwide.
     *
     * @var array<string, array<string, array{factor: float, target: string}>>
     */
    private const CONVERSIONS = [
        self::LOCALE_US => [
            'cup' => ['factor' => 236.588, 'target' => Unit::MILLILITER],
            'fl_oz' => ['factor' => 29.5735, 'target' => Unit::MILLILITER],
            'pint' => ['factor' => 473.176, 'target' => Unit::MILLILITER],
            'quart' => ['factor' => 946.353, 'target' => Unit::MILLILITER],
            'gallon' => ['factor' => 3785.41, 'target' => Unit::MILLILITER],
        ],
        self::LOCALE_UK => [
            'cup' => ['factor' => 284.131, 'target' => Unit::MILLILITER],
            'fl_oz' => ['factor' => 28.4131, 'target' => Unit::MILLILITER],
            'pint' => ['factor' => 568.261, 'target' => Unit::MILLILITER],
            'quart' => ['factor' => 1136.52, 'target' => Unit::MILLILITER],
            'gallon' => ['factor' => 4546.09, 'target' => Unit::MILLILITER],
        ],
        self::LOCALE_AU => [
            'cup' => ['factor' => 250.0, 'target' => Unit::MILLILITER],
            'fl_oz' => ['factor' => 28.4131, 'target' => Unit::MILLILITER],
            'pint' => ['factor' => 568.261, 'target' => Unit::MILLILITER],
            'quart' => ['factor' => 1136.52, 'target' => Unit::MILLILITER],
            'gallon' => ['factor' => 4546.09, 'target' => Unit::MILLILITER],
        ],
        self::LOCALE_METRIC => [
            'cup' => ['factor' => 250.0, 'target' => Unit::MILLILITER],
            'fl_oz' => ['factor' => 29.5735, 'target' => Unit::MILLILITER],
            'pint' => ['factor' => 500.0, 'target' => Unit::MILLILITER],
            'quart' => ['factor' => 1000.0, 'target' => Unit::MILLILITER],
            'gallon' => ['factor' => 4000.0, 'target' => Unit::MILLILITER],
        ],
    ];

    /**
     * Locale-independent conversions (weights, metric SI, spoons, sticks).
     *
     * @var array<string, array{factor: float, target: string}>
     */
    private const SHARED_CONVERSIONS = [
        'g' => ['factor' => 1.0, 'target' => Unit::GRAM],
        'kg' => ['factor' => 1000.0, 'target' => Unit::GRAM],
        'mg' => ['factor' => 0.001, 'target' => Unit::GRAM],
        'ml' => ['factor' => 1.0, 'target' => Unit::MILLILITER],
        'l' => ['factor' => 1000.0, 'target' => Unit::MILLILITER],
        'dl' => ['factor' => 100.0, 'target' => Unit::MILLILITER],
        'cl' => ['factor' => 10.0, 'target' => Unit::MILLILITER],
        'tsp' => ['factor' => 1.0, 'target' => Unit::TEASPOON],
        'tbsp' => ['factor' => 1.0, 'target' => Unit::TABLESPOON],
        'oz' => ['factor' => 28.3495, 'target' => Unit::GRAM],
        'lb' => ['factor' => 453.592, 'target' => Unit::GRAM],
        'piece' => ['factor' => 1.0, 'target' => Unit::PIECE],
        'stick' => ['factor' => 113.0, 'target' => Unit::GRAM], // 1 US butter stick = 4 oz ≈ 113.4 g
    ];

    /**
     * Convert (quantity, normalizedUnit) into canonical metric storage.
     * `locale` only changes volumetric (cup/pint/quart/gallon/fl_oz) factors;
     * weight units are locale-independent.
     *
     * @return array{quantity: ?float, unit: ?string}
     */
    public static function toMetric(?float $quantity, ?string $normalizedUnit, string $locale = self::LOCALE_US): array
    {
        if ($quantity === null && $normalizedUnit === null) {
            return ['quantity' => null, 'unit' => null];
        }

        if ($normalizedUnit === null) {
            return ['quantity' => $quantity, 'unit' => null];
        }

        $rule = self::CONVERSIONS[$locale][$normalizedUnit]
            ?? self::SHARED_CONVERSIONS[$normalizedUnit]
            ?? null;

        if ($rule === null) {
            return ['quantity' => $quantity, 'unit' => $normalizedUnit];
        }

        $value = $quantity === null ? null : self::roundForUnit($quantity * $rule['factor'], $rule['target']);

        return ['quantity' => $value, 'unit' => $rule['target']];
    }

    public static function isLocale(string $value): bool
    {
        return in_array($value, [self::LOCALE_US, self::LOCALE_UK, self::LOCALE_AU, self::LOCALE_METRIC], true);
    }

    private static function roundForUnit(float $value, string $canonicalUnit): float
    {
        return match ($canonicalUnit) {
            Unit::GRAM, Unit::MILLILITER => $value >= 10 ? round($value) : round($value, 1),
            Unit::TEASPOON, Unit::TABLESPOON => round($value * 8) / 8,
            Unit::PIECE => $value >= 1 ? round($value) : round($value * 4) / 4, // keep ¼-precision for fractional pieces (e.g. ¼ ui)
            default => $value,
        };
    }
}
