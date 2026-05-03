<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach (self::definitions() as $group => $rows) {
            foreach ($rows as $row) {
                $sort++;
                Tag::query()->updateOrCreate(
                    ['group' => $group, 'slug' => $row['slug']],
                    [
                        'name' => $row['name'],
                        'color' => $row['color'] ?? 'cream',
                        'sort' => $sort,
                        'is_system' => true,
                        'user_id' => null,
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, list<array{slug: string, name: string, color?: string}>>
     */
    public static function definitions(): array
    {
        return [
            Tag::GROUP_MEAL_TYPE => [
                ['slug' => 'ontbijt', 'name' => 'Ontbijt', 'color' => 'cream'],
                ['slug' => 'lunch', 'name' => 'Lunch', 'color' => 'cream'],
                ['slug' => 'avondeten', 'name' => 'Avondeten', 'color' => 'cream'],
                ['slug' => 'snack', 'name' => 'Snack', 'color' => 'cream'],
                ['slug' => 'borrelhap', 'name' => 'Borrelhap', 'color' => 'cream'],
                ['slug' => 'bijgerecht', 'name' => 'Bijgerecht', 'color' => 'cream'],
                ['slug' => 'dessert', 'name' => 'Dessert', 'color' => 'pink'],
                ['slug' => 'bakken', 'name' => 'Bakken', 'color' => 'pink'],
                ['slug' => 'sauzen', 'name' => 'Sauzen', 'color' => 'cream'],
                ['slug' => 'drank', 'name' => 'Drank', 'color' => 'sky'],
            ],
            Tag::GROUP_CUISINE => [
                ['slug' => 'nederlands', 'name' => 'Nederlands'],
                ['slug' => 'italiaans', 'name' => 'Italiaans'],
                ['slug' => 'frans', 'name' => 'Frans'],
                ['slug' => 'spaans', 'name' => 'Spaans'],
                ['slug' => 'grieks', 'name' => 'Grieks'],
                ['slug' => 'marokkaans', 'name' => 'Marokkaans'],
                ['slug' => 'libanees', 'name' => 'Libanees'],
                ['slug' => 'turks', 'name' => 'Turks'],
                ['slug' => 'indiaas', 'name' => 'Indiaas'],
                ['slug' => 'thais', 'name' => 'Thais'],
                ['slug' => 'vietnamees', 'name' => 'Vietnamees'],
                ['slug' => 'chinees', 'name' => 'Chinees'],
                ['slug' => 'japans', 'name' => 'Japans'],
                ['slug' => 'koreaans', 'name' => 'Koreaans'],
                ['slug' => 'mexicaans', 'name' => 'Mexicaans'],
                ['slug' => 'amerikaans', 'name' => 'Amerikaans'],
                ['slug' => 'brits', 'name' => 'Brits'],
                ['slug' => 'duits', 'name' => 'Duits'],
                ['slug' => 'scandinavisch', 'name' => 'Scandinavisch'],
            ],
            Tag::GROUP_ATTRIBUTE => [
                ['slug' => 'snel-en-makkelijk', 'name' => 'Snel & makkelijk', 'color' => 'lime'],
                ['slug' => 'weekendproject', 'name' => 'Weekendproject', 'color' => 'pink'],
                ['slug' => 'one-pot', 'name' => 'One-pot', 'color' => 'sky'],
                ['slug' => 'meal-prep', 'name' => 'Meal-prep', 'color' => 'sky'],
                ['slug' => 'vegetarisch', 'name' => 'Vegetarisch', 'color' => 'lime'],
                ['slug' => 'veganistisch', 'name' => 'Veganistisch', 'color' => 'lime'],
                ['slug' => 'glutenvrij', 'name' => 'Glutenvrij', 'color' => 'cream'],
                ['slug' => 'zuivelvrij', 'name' => 'Zuivelvrij', 'color' => 'cream'],
                ['slug' => 'comfort-food', 'name' => 'Comfort food', 'color' => 'accent'],
                ['slug' => 'gezond', 'name' => 'Gezond', 'color' => 'lime'],
                ['slug' => 'gourmet', 'name' => 'Gourmet', 'color' => 'ink'],
                ['slug' => 'kinderen', 'name' => 'Voor kinderen', 'color' => 'pink'],
                ['slug' => 'bbq', 'name' => 'BBQ', 'color' => 'accent'],
                ['slug' => 'oven', 'name' => 'Oven', 'color' => 'cream'],
                ['slug' => 'stoofpot', 'name' => 'Stoofpot', 'color' => 'accent'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugsFor(string $group): array
    {
        return array_map(
            fn (array $row): string => $row['slug'],
            self::definitions()[$group] ?? [],
        );
    }
}
