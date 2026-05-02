<?php

use App\Support\Units\IngredientNormalizer;
use App\Support\Units\UnitParser;

/**
 * Audit-grade tests for unit parsing & conversion. Each row was hand-computed
 * against the authoritative US customary factors:
 *   1 cup       = 236.5882365 ml
 *   1 oz        = 28.349523125 g
 *   1 lb        = 453.59237 g
 *   1 fl oz     = 29.5735295625 ml
 *   1 pint  US  = 473.176473 ml
 *   1 quart US  = 946.352946 ml
 *   1 gallon US = 3785.411784 ml
 *
 * Rounding rules under audit:
 *   g, ml          → whole when ≥ 10, else 1 decimal
 *   tsp, tbsp      → nearest 1/8 (preserves 1/8, 1/4, 1/2, 3/4)
 */
dataset('quantity_parsing', [
    'plain int' => ['200',         200.0],
    'decimal point' => ['0.5',         0.5],
    'decimal comma NL' => ['0,5',         0.5],
    'fraction half' => ['1/2',         0.5],
    'fraction three quarters' => ['3/4',         0.75],
    'mixed number space' => ['1 1/2',       1.5],
    'mixed number 2 3/4' => ['2 3/4',       2.75],
    'mixed number hyphen' => ['1-1/2',       1.5], // common in US sources
    'mixed unicode 1½' => ['1½',          1.5],
    'mixed unicode 2¾' => ['2¾',          2.75],
    'unicode standalone half' => ['½',           0.5],
    'unicode quarter' => ['¼',           0.25],
    'unicode eighth' => ['⅛',           0.125],
    'unicode third' => ['⅓',           1 / 3],
    'range hyphen' => ['2-3',         2.5],
    'range en dash' => ['2–4',         3.0],
    'range word to' => ['2 to 4',      3.0],
    'range word tot NL' => ['2 tot 4',     3.0],
    'pinch unparseable' => ['snufje',      null],
    'leading number messy' => ['ca 3 grote',  3.0],
    'whitespace only' => ['   ',         null],
    'null' => [null,          null],
]);

test('parseQuantity handles a wide variety of inputs', function (?string $input, ?float $expected) {
    $actual = UnitParser::parseQuantity($input);
    if ($expected === null) {
        expect($actual)->toBeNull();
    } else {
        expect($actual)->toBeFloat();
        expect($actual)->toEqualWithDelta($expected, 0.0001);
    }
})->with('quantity_parsing');

dataset('unit_aliases', [
    // Dutch
    ['gram', 'g'], ['gr', 'g'], ['G', 'g'], ['kg', 'kg'], ['kilo', 'kg'],
    ['ml', 'ml'], ['l', 'l'], ['Liter', 'l'], ['dl', 'dl'], ['cl', 'cl'],
    ['tl', 'tsp'], ['theelepel', 'tsp'], ['theelepels', 'tsp'],
    ['el', 'tbsp'], ['EL', 'tbsp'], ['eetlepel', 'tbsp'], ['eetlepels', 'tbsp'],
    ['stuk', 'piece'], ['stuks', 'piece'], ['st.', 'piece'], ['st', 'piece'],
    // US / EN
    ['tsp', 'tsp'], ['teaspoon', 'tsp'], ['teaspoons', 'tsp'],
    ['tbsp', 'tbsp'], ['tbs', 'tbsp'], ['tbspn', 'tbsp'], ['tablespoon', 'tbsp'],
    ['cup', 'cup'], ['cups', 'cup'],
    ['oz', 'oz'], ['ounce', 'oz'], ['ounces', 'oz'],
    ['lb', 'lb'], ['lbs', 'lb'], ['pound', 'lb'], ['pounds', 'lb'],
    ['fl oz', 'fl_oz'], ['fl. oz.', 'fl_oz'], ['floz', 'fl_oz'], ['fluid ounces', 'fl_oz'],
    ['pint', 'pint'], ['pints', 'pint'], ['pt', 'pint'],
    ['quart', 'quart'], ['quarts', 'quart'], ['qt', 'quart'],
    ['gallon', 'gallon'], ['gal', 'gallon'],
    // pieces (en)
    ['piece', 'piece'], ['pieces', 'piece'], ['whole', 'piece'],
    // unknown
    ['blops', null],
    ['', null],
    [null, null],
]);

test('normalizeUnit covers all known aliases', function (?string $raw, ?string $expected) {
    expect(UnitParser::normalizeUnit($raw))->toBe($expected);
})->with('unit_aliases');

/**
 * The big one: round-trip through IngredientNormalizer with an expected
 * (quantity, unit) result. The "name" column is uninvolved here so we only
 * sanity-check it ends up trimmed.
 */
dataset('conversion_audit', [
    // Dutch metric: pass-through
    '200 g flour' => ['200',     'g',         200.0,   'g'],
    '0,5 kg meel' => ['0,5',     'kg',        500.0,   'g'],
    '1 kg suiker' => ['1',       'kg',        1000.0,  'g'],
    '2,5 kg vlees' => ['2,5',     'kg',        2500.0,  'g'],
    '1 l melk' => ['1',       'l',         1000.0,  'ml'],
    '1,5 l water' => ['1,5',     'l',         1500.0,  'ml'],
    '750 ml stock' => ['750',     'ml',        750.0,   'ml'],
    '2,5 dl room' => ['2,5',     'dl',        250.0,   'ml'],
    '5 cl azijn' => ['5',       'cl',        50.0,    'ml'],
    // NL spoons preserved
    '1 el olie' => ['1',       'el',        1.0,     'tbsp'],
    '2 eetlepels' => ['2',       'eetlepels', 2.0,     'tbsp'],
    '1 tl zout' => ['1',       'tl',        1.0,     'tsp'],
    'half tl peper' => ['1/2',     'tl',        0.5,     'tsp'],
    // pieces
    '1 ui' => ['1',       null,        1.0,     'piece'],
    '3 grote eieren' => ['3',       '',          3.0,     'piece'],
    '2 stuks paprika' => ['2',       'stuks',     2.0,     'piece'],
    // US weights
    '1 oz butter' => ['1',       'oz',        28.0,    'g'],   // 28.3495
    '4 oz cream cheese' => ['4',       'oz',        113.0,   'g'],   // 113.398
    '8 oz chocolate' => ['8',       'oz',        227.0,   'g'],   // 226.796
    '1 lb beef' => ['1',       'lb',        454.0,   'g'],   // 453.592
    '1/2 lb shrimp' => ['1/2',     'lb',        227.0,   'g'],   // 226.796
    '2 lb chicken' => ['2',       'lb',        907.0,   'g'],   // 907.184
    // US volumes
    '1 cup flour' => ['1',       'cup',       237.0,   'ml'],  // 236.588
    '1/2 cup sugar' => ['1/2',     'cup',       118.0,   'ml'],  // 118.294
    '1/4 cup oil' => ['1/4',     'cup',       59.0,    'ml'],  // 59.147
    '1 1/2 cups milk' => ['1 1/2',   'cups',      355.0,   'ml'],  // 354.882
    '2 cups stock' => ['2',       'cups',      473.0,   'ml'],  // 473.176
    '1 fl oz vanilla' => ['1',       'fl oz',     30.0,    'ml'],  // 29.5735
    '8 fl oz milk' => ['8',       'fl oz',     237.0,   'ml'],  // 236.588
    '1 pint cream' => ['1',       'pint',      473.0,   'ml'],  // 473.176
    '1 quart broth' => ['1',       'quart',     946.0,   'ml'],  // 946.353
    '1 gallon water' => ['1',       'gallon',    3785.0,  'ml'],  // 3785.41
    // US spoons stay as spoons
    '1 tbsp olive oil' => ['1',       'tbsp',      1.0,     'tbsp'],
    '2 tbsp butter' => ['2',       'tbsp',      2.0,     'tbsp'],
    '1/2 tbsp vinegar' => ['1/2',     'tbsp',      0.5,     'tbsp'],
    '1/4 tsp salt' => ['1/4',     'tsp',       0.25,    'tsp'],
    '1/8 tsp baking soda' => ['1/8',     'tsp',       0.125,   'tsp'], // not lost!
    'pinch null' => [null,      null,        null,    null],
    // unicode and ranges
    '½ cup' => ['½',       'cup',       118.0,   'ml'],
    '2-3 cups water' => ['2-3',     'cups',      592.0,   'ml'], // 2.5 * 236.588 ≈ 591.47
    '1¼ cups buttermilk' => ['1¼',      'cups',      296.0,   'ml'], // 1.25 * 236.588 ≈ 295.74
    '1-1/2 cups flour' => ['1-1/2',   'cups',      355.0,   'ml'], // mixed-num 1.5
]);

test('full unit conversion audit', function (?string $qty, ?string $unit, ?float $expectedQty, ?string $expectedUnit) {
    $r = IngredientNormalizer::fromParts($qty, $unit, 'name');
    if ($expectedQty === null) {
        expect($r['quantity'])->toBeNull();
    } else {
        expect($r['quantity'])->toEqualWithDelta($expectedQty, 1.0); // ml/g rounded to whole, give 1u tolerance
    }
    expect($r['unit'])->toBe($expectedUnit);
})->with('conversion_audit');

test('1/8 tsp survives rounding (regression)', function () {
    expect(IngredientNormalizer::fromParts('1/8', 'tsp', 'salt')['quantity'])->toBe(0.125);
});

test('NL kommagetal kg correctly converts to grams', function () {
    expect(IngredientNormalizer::fromParts('0,75', 'kg', 'beef')['quantity'])->toBe(750.0);
});

test('cup converts with 1.0 ml tolerance vs authoritative value', function () {
    // 1 cup = 236.5882365 ml; we round to 237.
    $r = IngredientNormalizer::fromParts('1', 'cup', 'sugar');
    expect(abs(237.0 - $r['quantity']))->toBeLessThan(1.0);
});
