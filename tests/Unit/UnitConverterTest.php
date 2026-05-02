<?php

use App\Support\Units\UnitConverter;

test('grams pass through unchanged', function () {
    expect(UnitConverter::toMetric(200.0, 'g'))->toBe(['quantity' => 200.0, 'unit' => 'g']);
});

test('kilograms convert to grams', function () {
    expect(UnitConverter::toMetric(1.0, 'kg'))->toBe(['quantity' => 1000.0, 'unit' => 'g'])
        ->and(UnitConverter::toMetric(0.5, 'kg'))->toBe(['quantity' => 500.0, 'unit' => 'g']);
});

test('liter family converts to ml', function () {
    expect(UnitConverter::toMetric(1.0, 'l'))->toBe(['quantity' => 1000.0, 'unit' => 'ml'])
        ->and(UnitConverter::toMetric(2.5, 'dl'))->toBe(['quantity' => 250.0, 'unit' => 'ml'])
        ->and(UnitConverter::toMetric(5.0, 'cl'))->toBe(['quantity' => 50.0, 'unit' => 'ml']);
});

test('cups convert to ml and round', function () {
    $result = UnitConverter::toMetric(1.0, 'cup');
    expect($result['unit'])->toBe('ml')
        ->and($result['quantity'])->toBe(237.0);

    $result = UnitConverter::toMetric(0.5, 'cup');
    expect($result['unit'])->toBe('ml')
        ->and($result['quantity'])->toBe(118.0);
});

test('ounces (weight) convert to grams', function () {
    $result = UnitConverter::toMetric(1.0, 'oz');
    expect($result['unit'])->toBe('g')
        ->and($result['quantity'])->toBe(28.0);

    $result = UnitConverter::toMetric(8.0, 'oz');
    expect($result['unit'])->toBe('g')
        ->and($result['quantity'])->toBe(227.0);
});

test('pounds convert to grams', function () {
    expect(UnitConverter::toMetric(1.0, 'lb'))->toBe(['quantity' => 454.0, 'unit' => 'g'])
        ->and(UnitConverter::toMetric(2.0, 'lb'))->toBe(['quantity' => 907.0, 'unit' => 'g']);
});

test('fluid ounces convert to ml', function () {
    expect(UnitConverter::toMetric(1.0, 'fl_oz'))->toBe(['quantity' => 30.0, 'unit' => 'ml'])
        ->and(UnitConverter::toMetric(8.0, 'fl_oz'))->toBe(['quantity' => 237.0, 'unit' => 'ml']);
});

test('pint quart gallon convert to ml', function () {
    expect(UnitConverter::toMetric(1.0, 'pint'))->toBe(['quantity' => 473.0, 'unit' => 'ml'])
        ->and(UnitConverter::toMetric(1.0, 'quart'))->toBe(['quantity' => 946.0, 'unit' => 'ml'])
        ->and(UnitConverter::toMetric(1.0, 'gallon'))->toBe(['quantity' => 3785.0, 'unit' => 'ml']);
});

test('tablespoons and teaspoons stay as-is', function () {
    expect(UnitConverter::toMetric(1.0, 'tbsp'))->toBe(['quantity' => 1.0, 'unit' => 'tbsp'])
        ->and(UnitConverter::toMetric(2.0, 'tsp'))->toBe(['quantity' => 2.0, 'unit' => 'tsp'])
        ->and(UnitConverter::toMetric(0.5, 'tbsp'))->toBe(['quantity' => 0.5, 'unit' => 'tbsp']);
});

test('pieces stay as pieces', function () {
    expect(UnitConverter::toMetric(2.0, 'piece'))->toBe(['quantity' => 2.0, 'unit' => 'piece']);
});

test('null unit passes through', function () {
    expect(UnitConverter::toMetric(null, null))->toBe(['quantity' => null, 'unit' => null])
        ->and(UnitConverter::toMetric(1.0, null))->toBe(['quantity' => 1.0, 'unit' => null]);
});

test('rounds small ml values to one decimal', function () {
    $result = UnitConverter::toMetric(0.25, 'fl_oz');
    expect($result['unit'])->toBe('ml')
        ->and($result['quantity'])->toBe(7.4);
});
