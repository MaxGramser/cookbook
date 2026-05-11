<?php

namespace App\Support\Sharing;

use App\Models\Recipe;
use App\Models\Shortlist;
use Illuminate\Support\Str;

final class ShareMeta
{
    public const SITE_NAME = 'CookBook';

    /**
     * @return array{title: string, description: string, image: ?string, url: string, type: string, site_name: string}
     */
    public static function forRecipe(Recipe $recipe, string $url): array
    {
        return [
            'title' => $recipe->title,
            'description' => self::recipeDescription($recipe),
            'image' => self::imageUrl($recipe->image_path),
            'url' => $url,
            'type' => 'article',
            'site_name' => self::SITE_NAME,
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, url: string, type: string, site_name: string}
     */
    public static function forShortlist(Shortlist $shortlist, string $url): array
    {
        $count = $shortlist->recipes->count();
        $cover = $shortlist->recipes->firstWhere(fn (Recipe $r) => $r->image_path !== null);

        $description = $count === 0
            ? 'Een shortlist gedeeld via '.self::SITE_NAME.'.'
            : sprintf(
                'Shortlist met %d %s — gedeeld via %s.',
                $count,
                $count === 1 ? 'recept' : 'recepten',
                self::SITE_NAME,
            );

        return [
            'title' => $shortlist->name,
            'description' => $description,
            'image' => $cover ? self::imageUrl($cover->image_path) : null,
            'url' => $url,
            'type' => 'website',
            'site_name' => self::SITE_NAME,
        ];
    }

    private static function recipeDescription(Recipe $recipe): string
    {
        $facts = [];

        if ($recipe->cook_time_minutes) {
            $facts[] = $recipe->cook_time_minutes.' min';
        }

        if ($recipe->servings) {
            $facts[] = $recipe->servings.' personen';
        }

        $factsLine = implode(' · ', $facts);

        if ($recipe->notes) {
            $snippet = Str::limit(trim(preg_replace('/\s+/', ' ', $recipe->notes) ?? ''), 140);

            return $factsLine === '' ? $snippet : $factsLine.' — '.$snippet;
        }

        return $factsLine === ''
            ? 'Recept gedeeld via '.self::SITE_NAME.'.'
            : $factsLine.' — recept via '.self::SITE_NAME.'.';
    }

    private static function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
