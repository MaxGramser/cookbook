<?php

use App\Support\Recipes\RecipeHtmlStripper;

test('extracts JSON-LD recipe schema when present', function () {
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => 'Pannenkoeken',
        'recipeYield' => '4',
        'cookTime' => 'PT20M',
        'image' => 'https://example.com/p.jpg',
        'recipeIngredient' => ['200 g bloem', '1 l melk', '2 eieren'],
        'recipeInstructions' => [
            ['@type' => 'HowToStep', 'text' => 'Meng bloem en melk.'],
            ['@type' => 'HowToStep', 'text' => 'Bak in de pan.'],
        ],
    ]);

    $html = "<html><head><script type=\"application/ld+json\">{$jsonLd}</script></head><body>noise</body></html>";

    $result = RecipeHtmlStripper::strip($html);

    expect($result['json_ld'])->not->toBeNull();
    expect($result['image_url'])->toBe('https://example.com/p.jpg');
    expect($result['text'])->toContain('Title: Pannenkoeken');
    expect($result['text'])->toContain('200 g bloem');
    expect($result['text'])->toContain('Meng bloem en melk.');
});

test('falls back to readable text when no JSON-LD', function () {
    $html = <<<'HTML'
<html>
<head>
  <meta property="og:image" content="https://example.com/og.jpg">
  <style>body { color: red; }</style>
</head>
<body>
  <nav>navigation</nav>
  <main>
    <h1>Pancakes</h1>
    <p>1 cup flour</p>
    <p>Mix.</p>
  </main>
  <footer>footer noise</footer>
</body>
</html>
HTML;

    $result = RecipeHtmlStripper::strip($html);

    expect($result['json_ld'])->toBeNull();
    expect($result['image_url'])->toBe('https://example.com/og.jpg');
    expect($result['text'])->toContain('Pancakes');
    expect($result['text'])->toContain('1 cup flour');
    expect($result['text'])->not->toContain('navigation');
    expect($result['text'])->not->toContain('footer noise');
});

test('handles HowToSection nested instructions', function () {
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => 'Soep',
        'recipeIngredient' => ['1 ui'],
        'recipeInstructions' => [
            [
                '@type' => 'HowToSection',
                'name' => 'Voorbereiden',
                'itemListElement' => [
                    ['@type' => 'HowToStep', 'text' => 'Snij de ui.'],
                ],
            ],
        ],
    ]);
    $html = "<html><body><script type=\"application/ld+json\">{$jsonLd}</script></body></html>";

    $result = RecipeHtmlStripper::strip($html);

    expect($result['text'])->toContain('Snij de ui.');
});
