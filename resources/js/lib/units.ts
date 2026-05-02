import type { Unit } from '@/types/recipes';

const UNIT_LABELS: Record<NonNullable<Unit>, { singular: string; plural: string }> = {
    g: { singular: 'g', plural: 'g' },
    ml: { singular: 'ml', plural: 'ml' },
    tsp: { singular: 'tl', plural: 'tl' },
    tbsp: { singular: 'el', plural: 'el' },
    piece: { singular: 'stuk', plural: 'stuks' },
};

/**
 * Format a quantity with sensible precision per unit and the user's preferred
 * Dutch labels (tl/el/stuks). Returns the bare name when no quantity is set.
 */
export function formatQuantity(
    quantity: number | null,
    unit: Unit,
    multiplier = 1,
): string {
    if (quantity === null) {
        return '';
    }
    const value = quantity * multiplier;
    if (unit === null) {
        return formatNumber(value);
    }
    const { singular, plural } = UNIT_LABELS[unit];
    const label = unit === 'piece' && value === 1 ? singular : plural;
    if (unit === 'g' || unit === 'ml') {
        const display =
            value >= 100 ? Math.round(value).toString() : formatNumber(roundTo(value, 1));
        return `${display} ${label}`;
    }
    if (unit === 'tsp' || unit === 'tbsp') {
        return `${formatNumber(roundTo(value, 0.25))} ${label}`;
    }
    return `${formatNumber(roundTo(value, 0.5))} ${label}`;
}

function roundTo(value: number, increment: number): number {
    return Math.round(value / increment) * increment;
}

function formatNumber(value: number): string {
    if (Number.isInteger(value)) {
        return value.toString();
    }
    return value.toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
}
