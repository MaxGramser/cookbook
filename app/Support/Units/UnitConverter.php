<?php

namespace App\Support\Units;

final class UnitConverter
{
    /**
     * @var array<string, array{factor: float, target: string}>
     */
    private const CONVERSIONS = [
        'g' => ['factor' => 1.0, 'target' => Unit::GRAM],
        'kg' => ['factor' => 1000.0, 'target' => Unit::GRAM],
        'mg' => ['factor' => 0.001, 'target' => Unit::GRAM],
        'ml' => ['factor' => 1.0, 'target' => Unit::MILLILITER],
        'l' => ['factor' => 1000.0, 'target' => Unit::MILLILITER],
        'dl' => ['factor' => 100.0, 'target' => Unit::MILLILITER],
        'cl' => ['factor' => 10.0, 'target' => Unit::MILLILITER],
        'tsp' => ['factor' => 1.0, 'target' => Unit::TEASPOON],
        'tbsp' => ['factor' => 1.0, 'target' => Unit::TABLESPOON],
        'cup' => ['factor' => 236.588, 'target' => Unit::MILLILITER],
        'oz' => ['factor' => 28.3495, 'target' => Unit::GRAM],
        'lb' => ['factor' => 453.592, 'target' => Unit::GRAM],
        'fl_oz' => ['factor' => 29.5735, 'target' => Unit::MILLILITER],
        'pint' => ['factor' => 473.176, 'target' => Unit::MILLILITER],
        'quart' => ['factor' => 946.353, 'target' => Unit::MILLILITER],
        'gallon' => ['factor' => 3785.41, 'target' => Unit::MILLILITER],
        'piece' => ['factor' => 1.0, 'target' => Unit::PIECE],
    ];

    /**
     * Convert (quantity, normalizedUnit) into canonical metric storage.
     *
     * - Volumes other than tsp/tbsp normalize to ml.
     * - Weights normalize to g.
     * - Tsp / tbsp / piece are preserved.
     * - Unknown units pass through unchanged.
     *
     * @return array{quantity: ?float, unit: ?string}
     */
    public static function toMetric(?float $quantity, ?string $normalizedUnit): array
    {
        if ($quantity === null && $normalizedUnit === null) {
            return ['quantity' => null, 'unit' => null];
        }

        if ($normalizedUnit === null || ! isset(self::CONVERSIONS[$normalizedUnit])) {
            return ['quantity' => $quantity, 'unit' => $normalizedUnit];
        }

        $rule = self::CONVERSIONS[$normalizedUnit];
        $value = $quantity === null ? null : self::roundForUnit($quantity * $rule['factor'], $rule['target']);

        return ['quantity' => $value, 'unit' => $rule['target']];
    }

    /**
     * Round to a sensible precision per unit, so "1 cup" → 237 ml (not 236.588).
     */
    private static function roundForUnit(float $value, string $canonicalUnit): float
    {
        return match ($canonicalUnit) {
            Unit::GRAM, Unit::MILLILITER => $value >= 10 ? round($value) : round($value, 1),
            Unit::TEASPOON, Unit::TABLESPOON => round($value * 8) / 8, // nearest 1/8
            Unit::PIECE => round($value, 2),
            default => $value,
        };
    }
}
