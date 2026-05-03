<?php

namespace App\Actions\Recipes;

use App\Ai\Agents\TagClassifier;
use App\Models\Recipe;
use App\Models\Tag;

final class ClassifyRecipeTags
{
    public function __construct(private TagClassifier $classifier) {}

    /**
     * Run the LLM classifier on a recipe and attach the resolved system
     * tags. Existing tags are preserved (attachWithoutDuplicates).
     *
     * @return list<int> the IDs of newly attached tags
     */
    public function handle(Recipe $recipe): array
    {
        $recipe->loadMissing(['ingredients', 'steps']);

        $extracted = $this->classifier->prompt(self::buildPayload($recipe));

        $slugsByGroup = [
            Tag::GROUP_MEAL_TYPE => self::cleanSlugs($extracted['meal_types'] ?? []),
            Tag::GROUP_CUISINE => self::cleanSlugs($extracted['cuisines'] ?? []),
            Tag::GROUP_ATTRIBUTE => self::cleanSlugs($extracted['attributes'] ?? []),
        ];

        $newIds = [];
        foreach ($slugsByGroup as $group => $slugs) {
            if ($slugs === []) {
                continue;
            }
            $ids = Tag::query()
                ->where('group', $group)
                ->where('is_system', true)
                ->whereIn('slug', $slugs)
                ->pluck('id')
                ->all();
            $newIds = array_merge($newIds, $ids);
        }

        $newIds = array_values(array_unique(array_map('intval', $newIds)));

        if ($newIds === []) {
            return [];
        }

        $existing = $recipe->tags()->pluck('tags.id')->all();
        $toAttach = array_values(array_diff($newIds, $existing));

        if ($toAttach !== []) {
            $recipe->tags()->attach($toAttach);
        }

        return $toAttach;
    }

    private static function buildPayload(Recipe $recipe): string
    {
        $lines = [];
        $lines[] = 'TITEL: '.$recipe->title;
        if ($recipe->cook_time_minutes !== null) {
            $lines[] = 'KOOKTIJD: '.$recipe->cook_time_minutes.' min';
        }
        if ($recipe->servings > 0) {
            $lines[] = 'PERSONEN: '.$recipe->servings;
        }

        $lines[] = '';
        $lines[] = 'INGREDIËNTEN:';
        $currentSection = null;
        foreach ($recipe->ingredients as $ingredient) {
            if ($ingredient->section !== $currentSection) {
                $currentSection = $ingredient->section;
                if ($currentSection !== null && $currentSection !== '') {
                    $lines[] = '['.$currentSection.']';
                }
            }
            $qty = $ingredient->raw_text
                ?? trim(($ingredient->quantity ?? '').' '.($ingredient->unit ?? ''));
            $lines[] = '- '.trim($qty.' '.$ingredient->name);
        }

        $lines[] = '';
        $lines[] = 'STAPPEN:';
        $currentSection = null;
        $i = 0;
        foreach ($recipe->steps as $step) {
            if ($step->section !== $currentSection) {
                $currentSection = $step->section;
                if ($currentSection !== null && $currentSection !== '') {
                    $lines[] = '['.$currentSection.']';
                }
            }
            $i++;
            $lines[] = $i.'. '.$step->body;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function cleanSlugs(mixed $slugs): array
    {
        if (! is_array($slugs)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($s) => is_string($s) ? trim($s) : '', $slugs),
            fn ($s) => $s !== '',
        )));
    }
}
