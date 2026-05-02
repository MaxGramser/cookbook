<?php

namespace App\Actions\Recipes;

use App\Ai\Agents\RecipeExtractor;
use App\Models\Recipe;
use App\Models\User;
use App\Support\Recipes\RecipeHtmlStripper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ImportRecipeFromUrl
{
    public function __construct(
        private RecipeExtractor $extractor,
        private PersistExtractedRecipe $persister,
    ) {}

    public function handle(User $user, string $url): Recipe
    {
        $html = $this->fetchHtml($url);
        $stripped = RecipeHtmlStripper::strip($html);

        if ($stripped['text'] === '') {
            throw new RuntimeException('Geen recept content gevonden op deze pagina.');
        }

        $extracted = $this->extractor->prompt($stripped['text']);
        $imageUrl = $extracted['image_url'] ?? $stripped['image_url'];
        $imagePath = $imageUrl ? $this->downloadImage($imageUrl) : null;

        return $this->persister->handle($user, $extracted, $url, $imagePath);
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
}
