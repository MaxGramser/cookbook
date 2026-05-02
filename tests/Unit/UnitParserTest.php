<?php

use App\Support\Units\UnitParser;

describe('parseQuantity', function () {
    test('plain integers', function () {
        expect(UnitParser::parseQuantity('200'))->toBe(200.0);
    });

    test('decimals with dot', function () {
        expect(UnitParser::parseQuantity('0.5'))->toBe(0.5);
    });

    test('decimals with comma (NL style)', function () {
        expect(UnitParser::parseQuantity('0,5'))->toBe(0.5);
    });

    test('simple fractions', function () {
        expect(UnitParser::parseQuantity('1/2'))->toBe(0.5)
            ->and(UnitParser::parseQuantity('3/4'))->toBe(0.75);
    });

    test('mixed numbers', function () {
        expect(UnitParser::parseQuantity('1 1/2'))->toBe(1.5)
            ->and(UnitParser::parseQuantity('2 3/4'))->toBe(2.75);
    });

    test('unicode fractions standalone', function () {
        expect(UnitParser::parseQuantity('½'))->toBe(0.5)
            ->and(UnitParser::parseQuantity('¼'))->toBe(0.25)
            ->and(UnitParser::parseQuantity('¾'))->toBe(0.75);
    });

    test('unicode fractions with leading integer', function () {
        expect(UnitParser::parseQuantity('1½'))->toBe(1.5)
            ->and(UnitParser::parseQuantity('2¾'))->toBe(2.75);
    });

    test('ranges return midpoint', function () {
        expect(UnitParser::parseQuantity('2-3'))->toBe(2.5)
            ->and(UnitParser::parseQuantity('2 to 4'))->toBe(3.0)
            ->and(UnitParser::parseQuantity('2 tot 4'))->toBe(3.0);
    });

    test('returns null for empty or unparseable input', function () {
        expect(UnitParser::parseQuantity(null))->toBeNull()
            ->and(UnitParser::parseQuantity(''))->toBeNull()
            ->and(UnitParser::parseQuantity('   '))->toBeNull()
            ->and(UnitParser::parseQuantity('snufje'))->toBeNull();
    });

    test('extracts leading number from messy input', function () {
        expect(UnitParser::parseQuantity('approx 3 large'))->toBe(3.0);
    });
});

describe('normalizeUnit', function () {
    test('NL abbreviations for tablespoon and teaspoon', function () {
        expect(UnitParser::normalizeUnit('el'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('eetlepel'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('eetlepels'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('tl'))->toBe('tsp')
            ->and(UnitParser::normalizeUnit('theelepel'))->toBe('tsp')
            ->and(UnitParser::normalizeUnit('theelepels'))->toBe('tsp');
    });

    test('US abbreviations and variants', function () {
        expect(UnitParser::normalizeUnit('tbsp'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('tbspn'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('tbs'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('tablespoon'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('tablespoons'))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('tsp'))->toBe('tsp')
            ->and(UnitParser::normalizeUnit('teaspoon'))->toBe('tsp');
    });

    test('cups, fl oz with various spacings', function () {
        expect(UnitParser::normalizeUnit('cup'))->toBe('cup')
            ->and(UnitParser::normalizeUnit('cups'))->toBe('cup')
            ->and(UnitParser::normalizeUnit('fl oz'))->toBe('fl_oz')
            ->and(UnitParser::normalizeUnit('floz'))->toBe('fl_oz')
            ->and(UnitParser::normalizeUnit('fl. oz.'))->toBe('fl_oz')
            ->and(UnitParser::normalizeUnit('fluid ounces'))->toBe('fl_oz');
    });

    test('weights', function () {
        expect(UnitParser::normalizeUnit('g'))->toBe('g')
            ->and(UnitParser::normalizeUnit('gr'))->toBe('g')
            ->and(UnitParser::normalizeUnit('grams'))->toBe('g')
            ->and(UnitParser::normalizeUnit('kg'))->toBe('kg')
            ->and(UnitParser::normalizeUnit('kilo'))->toBe('kg')
            ->and(UnitParser::normalizeUnit('oz'))->toBe('oz')
            ->and(UnitParser::normalizeUnit('lb'))->toBe('lb')
            ->and(UnitParser::normalizeUnit('pounds'))->toBe('lb');
    });

    test('volumes', function () {
        expect(UnitParser::normalizeUnit('ml'))->toBe('ml')
            ->and(UnitParser::normalizeUnit('l'))->toBe('l')
            ->and(UnitParser::normalizeUnit('liter'))->toBe('l')
            ->and(UnitParser::normalizeUnit('dl'))->toBe('dl')
            ->and(UnitParser::normalizeUnit('cl'))->toBe('cl')
            ->and(UnitParser::normalizeUnit('pint'))->toBe('pint')
            ->and(UnitParser::normalizeUnit('quart'))->toBe('quart')
            ->and(UnitParser::normalizeUnit('gallon'))->toBe('gallon');
    });

    test('pieces', function () {
        expect(UnitParser::normalizeUnit('stuks'))->toBe('piece')
            ->and(UnitParser::normalizeUnit('stuk'))->toBe('piece')
            ->and(UnitParser::normalizeUnit('st'))->toBe('piece')
            ->and(UnitParser::normalizeUnit('pieces'))->toBe('piece');
    });

    test('case insensitive and trims', function () {
        expect(UnitParser::normalizeUnit('  TBSP  '))->toBe('tbsp')
            ->and(UnitParser::normalizeUnit('Cups'))->toBe('cup');
    });

    test('returns null for unknown units', function () {
        expect(UnitParser::normalizeUnit('blops'))->toBeNull()
            ->and(UnitParser::normalizeUnit(''))->toBeNull()
            ->and(UnitParser::normalizeUnit(null))->toBeNull();
    });
});
