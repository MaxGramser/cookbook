<?php

use App\Support\Units\IngredientNormalizer;

test('keeps grams as grams', function () {
    $r = IngredientNormalizer::fromParts('200', 'g', 'bloem');
    expect($r['quantity'])->toBe(200.0)
        ->and($r['unit'])->toBe('g')
        ->and($r['name'])->toBe('bloem');
});

test('NL eetlepel becomes tbsp', function () {
    $r = IngredientNormalizer::fromParts('2', 'el', 'olijfolie');
    expect($r['quantity'])->toBe(2.0)
        ->and($r['unit'])->toBe('tbsp');
});

test('NL theelepel becomes tsp', function () {
    $r = IngredientNormalizer::fromParts('1', 'tl', 'zout');
    expect($r['quantity'])->toBe(1.0)
        ->and($r['unit'])->toBe('tsp');
});

test('US cup converts to ml', function () {
    $r = IngredientNormalizer::fromParts('1', 'cup', 'flour');
    expect($r['quantity'])->toBe(237.0)
        ->and($r['unit'])->toBe('ml');
});

test('mixed number with cups converts to ml', function () {
    $r = IngredientNormalizer::fromParts('1 1/2', 'cups', 'milk');
    expect($r['unit'])->toBe('ml')
        ->and($r['quantity'])->toBe(355.0);
});

test('unicode fraction in quantity', function () {
    $r = IngredientNormalizer::fromParts('½', 'cup', 'sugar');
    expect($r['unit'])->toBe('ml')
        ->and($r['quantity'])->toBe(118.0);
});

test('1 ui without unit becomes piece', function () {
    $r = IngredientNormalizer::fromParts('1', null, 'ui');
    expect($r['quantity'])->toBe(1.0)
        ->and($r['unit'])->toBe('piece')
        ->and($r['name'])->toBe('ui');
});

test('2 eggs without unit becomes piece', function () {
    $r = IngredientNormalizer::fromParts('2', '', 'large eggs');
    expect($r['quantity'])->toBe(2.0)
        ->and($r['unit'])->toBe('piece');
});

test('explicit stuks normalizes to piece', function () {
    $r = IngredientNormalizer::fromParts('3', 'stuks', 'paprika');
    expect($r['quantity'])->toBe(3.0)
        ->and($r['unit'])->toBe('piece');
});

test('snufje zout has null quantity and null unit', function () {
    $r = IngredientNormalizer::fromParts(null, null, 'snufje zout');
    expect($r['quantity'])->toBeNull()
        ->and($r['unit'])->toBeNull()
        ->and($r['name'])->toBe('snufje zout');
});

test('range of cups uses midpoint', function () {
    $r = IngredientNormalizer::fromParts('2-3', 'cups', 'water');
    expect($r['unit'])->toBe('ml')
        ->and($r['quantity'])->toBe(round(2.5 * 236.588));
});

test('preserves raw_text', function () {
    $r = IngredientNormalizer::fromParts('1', 'cup', 'flour', '1 cup all-purpose flour');
    expect($r['raw_text'])->toBe('1 cup all-purpose flour');
});

test('comma decimal in quantity (NL style)', function () {
    $r = IngredientNormalizer::fromParts('0,5', 'liter', 'melk');
    expect($r['unit'])->toBe('ml')
        ->and($r['quantity'])->toBe(500.0);
});

test('pounds convert to grams', function () {
    $r = IngredientNormalizer::fromParts('1', 'lb', 'beef');
    expect($r['unit'])->toBe('g')
        ->and($r['quantity'])->toBe(454.0);
});
