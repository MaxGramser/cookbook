<?php

namespace App\Actions\Recipes;

use App\Ai\Agents\RecipeExtractor;
use App\Models\Recipe;
use App\Models\User;
use App\Support\Recipes\RecipeHtmlStripper;
use App\Support\Units\IngredientNormalizer;
use App\Support\Units\UnitConverter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ImportRecipeFromUrl
{
    public function __construct(private RecipeExtractor $extractor) {}

    public function handle(User $user, string $url): Recipe
    {
        $html = $this->fetchHtml($url);
        $stripped = RecipeHtmlStripper::strip($html);

        if ($stripped['text'] === '') {
            throw new RuntimeException('Geen recept content gevonden op deze pagina.');
        }

        $extracted = $this->extractor->prompt($stripped['text']);
        $imageUrl = $extracted['image_url'] ?? $stripped['image_url'];
        $locale = self::resolveLocale($extracted['source_locale'] ?? null);

        return DB::transaction(function () use ($user, $url, $extracted, $imageUrl, $locale) {
            $recipe = $user->recipes()->create([
                'title' => $extracted['title'],
                'source_url' => $url,
                'servings' => max(1, (int) ($extracted['servings'] ?? 0) ?: 1),
                'cook_time_minutes' => isset($extracted['cook_time_minutes']) && (int) $extracted['cook_time_minutes'] > 0
                    ? (int) $extracted['cook_time_minutes']
                    : null,
                'image_path' => $imageUrl ? $this->downloadImage($imageUrl) : null,
            ]);

            $position = 0;
            foreach ((array) ($extracted['ingredients'] ?? []) as $row) {
                $position++;
                $normalized = IngredientNormalizer::fromParts(
                    $row['quantity_text'] ?? null,
                    $row['unit_text'] ?? null,
                    (string) ($row['name'] ?? ''),
                    self::buildRawText($row),
                    $locale,
                );
                if ($normalized['name'] === '') {
                    continue;
                }
                $recipe->ingredients()->create([
                    'section' => self::cleanSection($row['section'] ?? null),
                    'position' => $position,
                    'name' => $normalized['name'],
                    'quantity' => $normalized['quantity'],
                    'unit' => $normalized['unit'],
                    'raw_text' => $normalized['raw_text'],
                ]);
            }

            $position = 0;
            foreach ((array) ($extracted['steps'] ?? []) as $row) {
                $body = is_string($row)
                    ? trim($row)
                    : trim((string) ($row['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $position++;
                $recipe->steps()->create([
                    'section' => is_array($row) ? self::cleanSection($row['section'] ?? null) : null,
                    'position' => $position,
                    'body' => $body,
                ]);
            }

            return $recipe;
        });
    }

    private static function cleanSection(mixed $section): ?string
    {
        if (! is_string($section)) {
            return null;
        }
        $trimmed = trim($section);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function resolveLocale(mixed $value): string
    {
        if (is_string($value) && UnitConverter::isLocale($value)) {
            return $value;
        }

        return UnitConverter::LOCALE_US;
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'nl-NL,nl;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Kon de URL niet ophalen: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException("URL gaf status {$response->status()}.");
        }

        return $response->body();
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $extension = match ($response->header('Content-Type')) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $filename = 'recipes/'.bin2hex(random_bytes(16)).'.'.$extension;
        Storage::disk('public')->put($filename, $response->body());

        return $filename;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function buildRawText(array $row): ?string
    {
        $parts = array_filter([
            isset($row['quantity_text']) ? (string) $row['quantity_text'] : null,
            isset($row['unit_text']) ? (string) $row['unit_text'] : null,
            isset($row['name']) ? (string) $row['name'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }
}
