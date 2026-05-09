<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DeveloperLoginController extends Controller
{
    private const DEV_EMAIL = 'dev@dev.test';

    private const DEV_PASSWORD = 'password';

    public function __invoke(Request $request): RedirectResponse|Response
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $user = User::query()->where('email', self::DEV_EMAIL)->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => 'Developer',
                'email' => self::DEV_EMAIL,
                'password' => Hash::make(self::DEV_PASSWORD),
                'email_verified_at' => now(),
            ]);

            $this->seedRecipes($user);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function seedRecipes(User $user): void
    {
        if (Tag::query()->where('is_system', true)->doesntExist()) {
            (new TagSeeder)->run();
        }

        DB::transaction(function () use ($user): void {
            $recipes = $this->recipeBlueprints();

            foreach ($recipes as $blueprint) {
                $recipe = Recipe::query()->create([
                    'user_id' => $user->id,
                    'title' => $blueprint['title'],
                    'source_url' => $blueprint['source_url'] ?? null,
                    'image_path' => $this->createPlaceholderImage($blueprint['title'], $blueprint['emoji'] ?? '🍳', $blueprint['color'] ?? '#F2EDE2'),
                    'servings' => $blueprint['servings'],
                    'cook_time_minutes' => $blueprint['cook_time_minutes'],
                    'notes' => $blueprint['notes'] ?? null,
                ]);

                foreach ($blueprint['ingredients'] as $position => $ingredient) {
                    RecipeIngredient::query()->create([
                        'recipe_id' => $recipe->id,
                        'section' => $ingredient['section'] ?? null,
                        'position' => $position + 1,
                        'name' => $ingredient['name'],
                        'quantity' => $ingredient['quantity'] ?? null,
                        'unit' => $ingredient['unit'] ?? null,
                        'raw_text' => null,
                    ]);
                }

                foreach ($blueprint['steps'] as $position => $step) {
                    RecipeStep::query()->create([
                        'recipe_id' => $recipe->id,
                        'section' => null,
                        'position' => $position + 1,
                        'body' => is_array($step) ? $step['body'] : $step,
                        'timer_minutes' => is_array($step) ? ($step['timer_minutes'] ?? null) : null,
                    ]);
                }

                if (! empty($blueprint['tags'])) {
                    $tagIds = Tag::query()
                        ->whereIn('slug', $blueprint['tags'])
                        ->pluck('id')
                        ->all();
                    if (! empty($tagIds)) {
                        $recipe->tags()->sync($tagIds);
                    }
                }
            }
        });
    }

    private function createPlaceholderImage(string $title, string $emoji, string $color): string
    {
        $slug = Str::slug($title);
        $relativePath = "recipes/dev/{$slug}.svg";

        $safeEmoji = htmlspecialchars($emoji, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeColor = htmlspecialchars($color, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600">
  <rect width="800" height="600" fill="{$safeColor}"/>
  <text x="400" y="380" text-anchor="middle" font-size="240" font-family="'Apple Color Emoji','Segoe UI Emoji',sans-serif">{$safeEmoji}</text>
</svg>
SVG;

        Storage::disk('public')->put($relativePath, $svg);

        return $relativePath;
    }

    /**
     * @return list<array{
     *     title: string,
     *     servings: int,
     *     cook_time_minutes: int,
     *     source_url?: string,
     *     notes?: string,
     *     emoji?: string,
     *     color?: string,
     *     ingredients: list<array{name: string, quantity?: float|int, unit?: string, section?: string}>,
     *     steps: list<string|array{body: string, timer_minutes?: int}>,
     *     tags?: list<string>
     * }>
     */
    private function recipeBlueprints(): array
    {
        return [
            [
                'title' => 'Spaghetti Carbonara',
                'servings' => 2,
                'cook_time_minutes' => 25,
                'emoji' => '🍝',
                'color' => '#F2EDE2',
                'ingredients' => [
                    ['name' => 'spaghetti', 'quantity' => 200, 'unit' => 'g'],
                    ['name' => 'guanciale', 'quantity' => 100, 'unit' => 'g'],
                    ['name' => 'eieren', 'quantity' => 2, 'unit' => 'piece'],
                    ['name' => 'pecorino', 'quantity' => 50, 'unit' => 'g'],
                    ['name' => 'zwarte peper', 'quantity' => 1, 'unit' => 'tsp'],
                ],
                'steps' => [
                    ['body' => 'Kook de spaghetti in goed gezouten water al dente.', 'timer_minutes' => 9],
                    'Bak intussen de guanciale knapperig in een droge pan.',
                    'Klop de eieren los met de geraspte pecorino en veel peper.',
                    'Meng de afgegoten pasta met de guanciale, haal van het vuur en roer het eimengsel erdoor.',
                    'Serveer direct met extra pecorino en peper.',
                ],
                'tags' => ['avondeten', 'italiaans', 'snel-en-makkelijk'],
            ],
            [
                'title' => 'Thaise groene curry met kip',
                'servings' => 4,
                'cook_time_minutes' => 35,
                'emoji' => '🍛',
                'color' => '#C5E04A',
                'ingredients' => [
                    ['name' => 'kipfilet', 'quantity' => 500, 'unit' => 'g'],
                    ['name' => 'groene currypasta', 'quantity' => 3, 'unit' => 'tbsp'],
                    ['name' => 'kokosmelk', 'quantity' => 400, 'unit' => 'ml'],
                    ['name' => 'aubergine', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'thaise basilicum', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'vissaus', 'quantity' => 1, 'unit' => 'tbsp'],
                    ['name' => 'jasmijnrijst', 'quantity' => 250, 'unit' => 'g'],
                ],
                'steps' => [
                    'Bak de currypasta kort aan met een scheut kokosmelk tot het geurig is.',
                    'Voeg de kip toe en bak rondom dicht.',
                    'Schenk de rest van de kokosmelk erbij en voeg de aubergine toe.',
                    ['body' => 'Laat zachtjes pruttelen tot de kip gaar is.', 'timer_minutes' => 15],
                    'Breng op smaak met vissaus en werk af met thaise basilicum. Serveer met rijst.',
                ],
                'tags' => ['avondeten', 'thais', 'comfort-food'],
            ],
            [
                'title' => 'Klassieke pannenkoeken',
                'servings' => 4,
                'cook_time_minutes' => 20,
                'emoji' => '🥞',
                'color' => '#F4D9C9',
                'ingredients' => [
                    ['name' => 'bloem', 'quantity' => 250, 'unit' => 'g'],
                    ['name' => 'melk', 'quantity' => 500, 'unit' => 'ml'],
                    ['name' => 'eieren', 'quantity' => 2, 'unit' => 'piece'],
                    ['name' => 'snufje zout', 'quantity' => 1, 'unit' => 'tsp'],
                    ['name' => 'boter', 'quantity' => 30, 'unit' => 'g'],
                ],
                'steps' => [
                    'Klop de bloem, eieren, melk en zout tot een glad beslag.',
                    'Verhit een beetje boter in een pan op middelhoog vuur.',
                    'Schep een soeplepel beslag in de pan en bak tot de bovenkant droog is.',
                    'Draai de pannenkoek om en bak nog een minuut.',
                    'Stapel op en serveer met stroop, suiker of spek.',
                ],
                'tags' => ['ontbijt', 'nederlands', 'kinderen'],
            ],
            [
                'title' => 'Geroosterde tomatensoep',
                'servings' => 4,
                'cook_time_minutes' => 45,
                'emoji' => '🍅',
                'color' => '#E0673E',
                'ingredients' => [
                    ['name' => 'rijpe tomaten', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'rode ui', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'knoflook', 'quantity' => 4, 'unit' => 'piece'],
                    ['name' => 'olijfolie', 'quantity' => 3, 'unit' => 'tbsp'],
                    ['name' => 'groentebouillon', 'quantity' => 500, 'unit' => 'ml'],
                    ['name' => 'verse basilicum', 'quantity' => 1, 'unit' => 'piece'],
                ],
                'steps' => [
                    'Verwarm de oven voor op 200°C.',
                    ['body' => 'Rooster de tomaten, ui en knoflook met olijfolie tot ze geblakerd zijn.', 'timer_minutes' => 30],
                    'Pureer alles met de bouillon tot een gladde soep.',
                    'Breng op smaak met zout, peper en basilicum.',
                ],
                'tags' => ['lunch', 'vegetarisch', 'gezond'],
            ],
            [
                'title' => 'Boerenomelet',
                'servings' => 2,
                'cook_time_minutes' => 15,
                'emoji' => '🍳',
                'color' => '#F2EDE2',
                'ingredients' => [
                    ['name' => 'eieren', 'quantity' => 6, 'unit' => 'piece'],
                    ['name' => 'spek blokjes', 'quantity' => 100, 'unit' => 'g'],
                    ['name' => 'aardappel gekookt', 'quantity' => 2, 'unit' => 'piece'],
                    ['name' => 'paprika', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'ui', 'quantity' => 1, 'unit' => 'piece'],
                ],
                'steps' => [
                    'Bak de spekjes uit en voeg ui, paprika en aardappel toe.',
                    'Klop de eieren los en schenk over de groenten.',
                    ['body' => 'Bak op laag vuur tot de bovenkant gestold is.', 'timer_minutes' => 6],
                    'Schuif op een bord en serveer met brood.',
                ],
                'tags' => ['ontbijt', 'nederlands', 'snel-en-makkelijk'],
            ],
            [
                'title' => 'Marokkaanse couscous met kip',
                'servings' => 4,
                'cook_time_minutes' => 50,
                'emoji' => '🥘',
                'color' => '#F4D9C9',
                'ingredients' => [
                    ['name' => 'kippendijen', 'quantity' => 600, 'unit' => 'g'],
                    ['name' => 'couscous', 'quantity' => 250, 'unit' => 'g'],
                    ['name' => 'wortel', 'quantity' => 2, 'unit' => 'piece'],
                    ['name' => 'courgette', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'kikkererwten', 'quantity' => 400, 'unit' => 'g'],
                    ['name' => 'ras el hanout', 'quantity' => 2, 'unit' => 'tbsp'],
                    ['name' => 'kippenbouillon', 'quantity' => 500, 'unit' => 'ml'],
                ],
                'steps' => [
                    'Bak de kippendijen rondom bruin en schep eruit.',
                    'Fruit ui, wortel en courgette aan en voeg de specerijen toe.',
                    ['body' => 'Doe de kip terug in de pan, voeg bouillon en kikkererwten toe en stoof.', 'timer_minutes' => 25],
                    'Bereid de couscous volgens de aanwijzingen op de verpakking.',
                    'Serveer de stoofpot op een bedje van couscous.',
                ],
                'tags' => ['avondeten', 'marokkaans', 'meal-prep'],
            ],
            [
                'title' => 'Chocolate chip cookies',
                'servings' => 12,
                'cook_time_minutes' => 25,
                'emoji' => '🍪',
                'color' => '#F7C2D8',
                'ingredients' => [
                    ['name' => 'boter', 'quantity' => 150, 'unit' => 'g'],
                    ['name' => 'bruine suiker', 'quantity' => 150, 'unit' => 'g'],
                    ['name' => 'witte suiker', 'quantity' => 50, 'unit' => 'g'],
                    ['name' => 'ei', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'bloem', 'quantity' => 220, 'unit' => 'g'],
                    ['name' => 'baking soda', 'quantity' => 1, 'unit' => 'tsp'],
                    ['name' => 'pure chocolade chunks', 'quantity' => 200, 'unit' => 'g'],
                ],
                'steps' => [
                    'Verwarm de oven voor op 180°C.',
                    'Klop boter en suiker romig en mix het ei erdoor.',
                    'Spatel bloem, baking soda en chocolade erdoor.',
                    ['body' => 'Schep bolletjes op bakpapier en bak tot de randen goudbruin zijn.', 'timer_minutes' => 12],
                    'Laat 5 minuten afkoelen op de bakplaat voor je ze verplaatst.',
                ],
                'tags' => ['dessert', 'bakken', 'kinderen'],
            ],
            [
                'title' => 'Caesar salade met krokante kip',
                'servings' => 2,
                'cook_time_minutes' => 20,
                'emoji' => '🥗',
                'color' => '#A6D8F1',
                'ingredients' => [
                    ['name' => 'romaine sla', 'quantity' => 1, 'unit' => 'piece'],
                    ['name' => 'kipfilet', 'quantity' => 300, 'unit' => 'g'],
                    ['name' => 'parmezaan', 'quantity' => 50, 'unit' => 'g'],
                    ['name' => 'croutons', 'quantity' => 80, 'unit' => 'g'],
                    ['name' => 'caesardressing', 'quantity' => 4, 'unit' => 'tbsp'],
                ],
                'steps' => [
                    'Kruid en bak de kip in een grillpan tot gaar.',
                    'Snijd de sla en meng met dressing en croutons.',
                    'Snijd de kip in reepjes en leg op de salade.',
                    'Werk af met geschaafde parmezaan.',
                ],
                'tags' => ['lunch', 'amerikaans', 'snel-en-makkelijk'],
            ],
        ];
    }
}
