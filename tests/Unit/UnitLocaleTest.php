<?php

use App\Support\Units\IngredientNormalizer;
use App\Support\Units\UnitConverter;

test('US cup is 236.588 ml', function () {
    expect(UnitConverter::toMetric(1.0, 'cup', UnitConverter::LOCALE_US))
        ->toBe(['quantity' => 237.0, 'unit' => 'ml']);
});

test('AU cup is 250 ml', function () {
    expect(UnitConverter::toMetric(1.0, 'cup', UnitConverter::LOCALE_AU))
        ->toBe(['quantity' => 250.0, 'unit' => 'ml']);
});

test('UK cup is 284 ml', function () {
    expect(UnitConverter::toMetric(1.0, 'cup', UnitConverter::LOCALE_UK))
        ->toBe(['quantity' => 284.0, 'unit' => 'ml']);
});

test('metric cup is 250 ml', function () {
    expect(UnitConverter::toMetric(1.0, 'cup', UnitConverter::LOCALE_METRIC))
        ->toBe(['quantity' => 250.0, 'unit' => 'ml']);
});

test('UK and AU pints are 568 ml, US is 473 ml', function () {
    expect(UnitConverter::toMetric(1.0, 'pint', UnitConverter::LOCALE_US)['quantity'])->toBe(473.0);
    expect(UnitConverter::toMetric(1.0, 'pint', UnitConverter::LOCALE_UK)['quantity'])->toBe(568.0);
    expect(UnitConverter::toMetric(1.0, 'pint', UnitConverter::LOCALE_AU)['quantity'])->toBe(568.0);
});

test('weights are locale-independent', function () {
    foreach ([UnitConverter::LOCALE_US, UnitConverter::LOCALE_UK, UnitConverter::LOCALE_AU, UnitConverter::LOCALE_METRIC] as $locale) {
        expect(UnitConverter::toMetric(1.0, 'lb', $locale)['quantity'])->toBe(454.0);
        expect(UnitConverter::toMetric(1.0, 'oz', $locale)['quantity'])->toBe(28.0);
    }
});

test('1 stick of butter is 113 g', function () {
    expect(UnitConverter::toMetric(1.0, 'stick'))->toBe(['quantity' => 113.0, 'unit' => 'g']);
    expect(UnitConverter::toMetric(2.0, 'stick'))->toBe(['quantity' => 226.0, 'unit' => 'g']);
    expect(UnitConverter::toMetric(0.5, 'stick'))->toBe(['quantity' => 57.0, 'unit' => 'g']);
});

test('IngredientNormalizer accepts locale', function () {
    $au = IngredientNormalizer::fromParts('1', 'cup', 'flour', null, UnitConverter::LOCALE_AU);
    $us = IngredientNormalizer::fromParts('1', 'cup', 'flour', null, UnitConverter::LOCALE_US);
    expect($au['quantity'])->toBe(250.0);
    expect($us['quantity'])->toBe(237.0);
});

test('1 stick butter via normalizer', function () {
    $r = IngredientNormalizer::fromParts('1', 'stick', 'butter');
    expect($r['unit'])->toBe('g');
    expect($r['quantity'])->toBe(113.0);
});

test('isLocale validates known values', function () {
    expect(UnitConverter::isLocale('us'))->toBeTrue();
    expect(UnitConverter::isLocale('au'))->toBeTrue();
    expect(UnitConverter::isLocale('uk'))->toBeTrue();
    expect(UnitConverter::isLocale('metric'))->toBeTrue();
    expect(UnitConverter::isLocale('whatever'))->toBeFalse();
});
