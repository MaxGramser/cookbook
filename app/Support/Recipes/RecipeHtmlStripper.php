<?php

namespace App\Support\Recipes;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reduces a recipe page to just the content the LLM needs.
 *
 * Strategy:
 *   1. Look for JSON-LD `Recipe` schema (most major recipe sites publish this).
 *      If found, return it directly — the LLM hardly needs to interpret it.
 *   2. Otherwise, strip scripts/styles/nav/footer/aside and emit the textual
 *      content of the largest meaningful container, plus any obvious image URL.
 */
final class RecipeHtmlStripper
{
    /**
     * @return array{text: string, image_url: ?string, json_ld: ?array<string, mixed>}
     */
    public static function strip(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $jsonLd = self::findRecipeJsonLd($doc);
        if ($jsonLd !== null) {
            return [
                'text' => self::summarizeJsonLd($jsonLd),
                'image_url' => self::extractJsonLdImage($jsonLd),
                'json_ld' => $jsonLd,
            ];
        }

        return [
            'text' => self::extractReadableText($doc),
            'image_url' => self::extractFallbackImage($doc),
            'json_ld' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findRecipeJsonLd(DOMDocument $doc): ?array
    {
        $xpath = new DOMXPath($doc);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        if ($scripts === false) {
            return null;
        }

        foreach ($scripts as $script) {
            $payload = $script->textContent;
            $data = json_decode($payload, true);
            if (! is_array($data)) {
                continue;
            }

            $candidates = self::flattenLdGraph($data);
            foreach ($candidates as $node) {
                if (self::isRecipeNode($node)) {
                    return $node;
                }
            }
        }

        return null;
    }

    /**
     * Flatten any `@graph` arrays so each candidate node is inspected.
     *
     * @param  array<int|string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function flattenLdGraph(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return array_filter($data['@graph'], 'is_array');
        }

        if (array_is_list($data)) {
            $out = [];
            foreach ($data as $node) {
                if (is_array($node)) {
                    $out = array_merge($out, self::flattenLdGraph($node));
                }
            }

            return $out;
        }

        return [$data];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function isRecipeNode(array $node): bool
    {
        $type = $node['@type'] ?? null;
        if (is_string($type)) {
            return strcasecmp($type, 'Recipe') === 0;
        }
        if (is_array($type)) {
            foreach ($type as $entry) {
                if (is_string($entry) && strcasecmp($entry, 'Recipe') === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function summarizeJsonLd(array $node): string
    {
        $lines = [];

        if (! empty($node['name'])) {
            $lines[] = 'Title: '.self::stringify($node['name']);
        }
        if (! empty($node['recipeYield'])) {
            $lines[] = 'Yield: '.self::stringify($node['recipeYield']);
        }
        if (! empty($node['totalTime'])) {
            $lines[] = 'Total time: '.self::stringify($node['totalTime']);
        }
        if (! empty($node['cookTime'])) {
            $lines[] = 'Cook time: '.self::stringify($node['cookTime']);
        }
        if (! empty($node['prepTime'])) {
            $lines[] = 'Prep time: '.self::stringify($node['prepTime']);
        }

        if (isset($node['recipeIngredient']) && is_array($node['recipeIngredient'])) {
            $lines[] = '';
            $lines[] = 'Ingredients:';
            foreach ($node['recipeIngredient'] as $ingredient) {
                $lines[] = '- '.self::stringify($ingredient);
            }
        }

        if (isset($node['recipeInstructions'])) {
            $lines[] = '';
            $lines[] = 'Instructions:';
            foreach (self::flattenInstructions($node['recipeInstructions']) as $step) {
                if (is_array($step)) {
                    $lines[] = '## '.$step['section'];
                    foreach ($step['steps'] as $body) {
                        $lines[] = '- '.$body;
                    }
                } else {
                    $lines[] = '- '.$step;
                }
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Flatten a recipeInstructions tree into a list. HowToSection entries are
     * preserved as ['section' => string, 'steps' => string[]] so the prompt
     * receives the heading and can keep ingredients/steps grouped accordingly.
     *
     * @return list<string|array{section: string, steps: list<string>}>
     */
    private static function flattenInstructions(mixed $instructions): array
    {
        if (is_string($instructions)) {
            return array_values(array_filter(array_map('trim', preg_split('/\r?\n+/', $instructions) ?: [])));
        }
        if (! is_array($instructions)) {
            return [];
        }

        $out = [];
        foreach ($instructions as $entry) {
            if (is_string($entry)) {
                $out[] = trim($entry);

                continue;
            }
            if (! is_array($entry)) {
                continue;
            }
            $type = $entry['@type'] ?? null;
            if ($type === 'HowToSection' && isset($entry['itemListElement'])) {
                $sectionName = isset($entry['name']) ? trim(self::stringify($entry['name'])) : '';
                $childSteps = self::flattenInstructions($entry['itemListElement']);
                $childStrings = array_values(array_filter($childSteps, 'is_string'));
                if ($sectionName !== '' && $childStrings !== []) {
                    $out[] = ['section' => $sectionName, 'steps' => $childStrings];
                } else {
                    $out = array_merge($out, $childSteps);
                }

                continue;
            }
            if (isset($entry['text'])) {
                $out[] = trim(self::stringify($entry['text']));
            } elseif (isset($entry['name'])) {
                $out[] = trim(self::stringify($entry['name']));
            }
        }

        $cleaned = [];
        foreach ($out as $item) {
            if (is_string($item)) {
                if (trim($item) !== '') {
                    $cleaned[] = $item;
                }
            } else {
                $cleaned[] = $item;
            }
        }

        return $cleaned;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function extractJsonLdImage(array $node): ?string
    {
        $image = $node['image'] ?? null;
        if (is_string($image)) {
            return $image;
        }
        if (is_array($image)) {
            if (isset($image['url']) && is_string($image['url'])) {
                return $image['url'];
            }
            foreach ($image as $entry) {
                if (is_string($entry)) {
                    return $entry;
                }
                if (is_array($entry) && isset($entry['url']) && is_string($entry['url'])) {
                    return $entry['url'];
                }
            }
        }

        return null;
    }

    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value)));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function extractReadableText(DOMDocument $doc): string
    {
        $xpath = new DOMXPath($doc);
        foreach (['//script', '//style', '//nav', '//footer', '//aside', '//noscript', '//iframe'] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false) {
                continue;
            }
            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $main = $xpath->query('(//main | //article | //*[contains(@class, "recipe")])[1]')?->item(0);
        $text = $main instanceof DOMElement ? $main->textContent : ($doc->textContent ?? '');

        $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

        return trim($text);
    }

    private static function extractFallbackImage(DOMDocument $doc): ?string
    {
        $xpath = new DOMXPath($doc);

        $og = $xpath->query('//meta[@property="og:image"]/@content')?->item(0);
        if ($og !== null) {
            return $og->nodeValue;
        }

        $twitter = $xpath->query('//meta[@name="twitter:image"]/@content')?->item(0);
        if ($twitter !== null) {
            return $twitter->nodeValue;
        }

        $first = $xpath->query('//main//img/@src | //article//img/@src')?->item(0);
        if ($first !== null) {
            return $first->nodeValue;
        }

        return null;
    }
}
