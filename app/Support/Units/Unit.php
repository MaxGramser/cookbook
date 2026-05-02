<?php

namespace App\Support\Units;

final class Unit
{
    public const GRAM = 'g';

    public const MILLILITER = 'ml';

    public const TEASPOON = 'tsp';

    public const TABLESPOON = 'tbsp';

    public const PIECE = 'piece';

    /**
     * Canonical units that are valid for storage. `null` is also valid (for
     * unitless ingredients like "snufje zout").
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::GRAM, self::MILLILITER, self::TEASPOON, self::TABLESPOON, self::PIECE];
    }
}
