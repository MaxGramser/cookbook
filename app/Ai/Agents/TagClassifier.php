<?php

namespace App\Ai\Agents;

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class TagClassifier implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $mealTypes = implode(', ', TagSeeder::slugsFor(Tag::GROUP_MEAL_TYPE));
        $cuisines = implode(', ', TagSeeder::slugsFor(Tag::GROUP_CUISINE));
        $attributes = implode(', ', TagSeeder::slugsFor(Tag::GROUP_ATTRIBUTE));

        return <<<TXT
You classify an existing recipe (Dutch title + ingredient list + steps) into
three closed-enum tag groups. You do NOT extract or transform the recipe;
you only emit slugs from the lists below. Never invent new slugs.

# meal_types — what kind of meal is this? (zero or more)

Allowed: {$mealTypes}

Pick everything that fits. A pasta carbonara is `avondeten`. A muffin is
both `ontbijt` and `bakken`. A guacamole is `borrelhap`. A fudge sauce is
`sauzen` and `dessert`. Pancakes are `ontbijt` (and `bakken` if from
scratch). Don't tag `bijgerecht` for main-course recipes.

# cuisines — culinary tradition (zero, one, or two)

Allowed: {$cuisines}

Use the recipe's culinary roots, not the source. Spaghetti carbonara is
`italiaans` even on an American blog. Tag two cuisines only for clear
fusion (Korean tacos: `koreaans` + `mexicaans`). For dishes that are
global/non-specific (smoothies, fruit salads, basic omelet), return [].

# attributes — dish properties (zero to four)

Allowed: {$attributes}

Be conservative — only tag what is unambiguous from the recipe content:
  - `snel-en-makkelijk`: <= 30 min AND <= 8 ingredients AND no advanced
    techniques.
  - `weekendproject`: >= 2 hours OR multi-stage (rising dough, overnight
    marinade, slow braise, smoking, fermenting).
  - `one-pot`: cooked in a single vessel — explicit sign in the steps.
  - `meal-prep`: source explicitly mentions batch / freeze / week-ahead.
  - `vegetarisch`: no meat, poultry, fish, seafood, gelatine.
  - `veganistisch`: also no eggs, dairy, honey. Imply vegetarisch too.
  - `glutenvrij` / `zuivelvrij`: only when recipe is actually free.
  - `comfort-food`: hearty, rich, warming.
  - `gezond`: explicitly health-focused.
  - `gourmet`: restaurant-style, advanced techniques.
  - `kinderen`: explicitly kid-friendly.
  - `bbq`: cooked on a grill / outdoor barbecue.
  - `oven`: primary cooking method is the oven (roasting, baking).
  - `stoofpot`: braise, stew, slow simmer 1+ hour.

When in doubt, omit. False positives are worse than missing tags.
TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'meal_types' => $schema->array()
                ->items($schema->string()->enum(TagSeeder::slugsFor(Tag::GROUP_MEAL_TYPE)))
                ->required(),
            'cuisines' => $schema->array()
                ->items($schema->string()->enum(TagSeeder::slugsFor(Tag::GROUP_CUISINE)))
                ->required(),
            'attributes' => $schema->array()
                ->items($schema->string()->enum(TagSeeder::slugsFor(Tag::GROUP_ATTRIBUTE)))
                ->required(),
        ];
    }
}
