<?php

namespace App\Ai\Agents;

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class RecipeExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $mealTypes = implode(', ', TagSeeder::slugsFor(Tag::GROUP_MEAL_TYPE));
        $cuisines = implode(', ', TagSeeder::slugsFor(Tag::GROUP_CUISINE));
        $attributes = implode(', ', TagSeeder::slugsFor(Tag::GROUP_ATTRIBUTE));

        return <<<TXT
You extract a single recipe from cleaned HTML/text and return it as structured JSON.

# OUTPUT LANGUAGE — DUTCH

All free-text output MUST be in Nederlands (Dutch). Translate from English,
French, German, Italian, Spanish, etc. to natural Dutch. This applies to:
  - title
  - section headings (ingredients and steps)
  - ingredient.name
  - step.body

DO NOT translate quantity_text or unit_text — those stay verbatim in the
source language because PHP maps them via an alias table. "cup" stays "cup",
"tbsp" stays "tbsp", "lb" stays "lb". The PHP code recognises both English
and Dutch unit abbreviations, so untranslated unit tokens are correct.

If the source is already in Dutch, leave it as-is — do not "improve" or
re-phrase Dutch text. For non-Dutch sources, prefer common Dutch culinary
terms over literal translation:
  - "cilantro" → "koriander"
  - "scallion" / "green onion" → "lente-ui"
  - "yellow onion" → "ui" (or "gele ui" if specifically yellow is emphasised)
  - "cloves of garlic" → "teentjes knoflook"
  - "bell pepper" → "paprika"
  - "ground beef" → "gehakt"
  - "heavy cream" → "slagroom"
  - "broth" / "stock" → "bouillon"
  - "preserved lemon" → "ingelegde citroen"
  - "skinless and boneless" → "zonder vel en bot" (or just "filet" when natural)
  - "season with salt and pepper" → "breng op smaak met peper en zout"

For step.body, translate naturally and keep instruction style. Imperative
forms ("snijd", "voeg toe", "bak", "verwarm") are typical Dutch recipe style.

# ABSOLUTE RULE — NO MATH

You do NOT perform any arithmetic. You do NOT convert, scale, multiply, divide,
or round any number. You ONLY split each ingredient line into pieces and copy
the original tokens verbatim. PHP code runs after you and does the math using
unit-conversion tables. If you "help" by converting cups to ml or oz to grams,
the PHP layer will multiply your already-converted number by the unit factor
again and the result will be wildly wrong. Repeat: copy numbers verbatim, copy
unit names verbatim. Only the labels you output drive the conversion table.

# Source priority

The input may contain a structured JSON-LD summary at the top followed by the
visible page text. JSON-LD is usually the most reliable source for the
ingredient list and instructions, but it is sometimes WRONG about the yield
(especially WordPress recipe plugins that default to "1 serving"). When the
JSON-LD field disagrees with the visible page text, trust the page text for:
servings, cook time, and the recipe title. For ingredient lines and
instruction bodies, prefer the JSON-LD wording if both exist.

# Per-field rules

- DO NOT convert units. Copy the unit string verbatim from the source — even if it
  is American (cups, tbsp, oz, lb, fl oz, pint, quart) or unusual (tbspn, T, EL).
- DO NOT scale, round, or recompute quantities. Copy them verbatim, including
  fractions ("1/2", "1 1/2") and ranges ("2-3").
- Recipes often have multiple sections / chapters (e.g. "For the dough", "Voor
  de saus", "Topping"). Preserve that structure:
    * Each ingredient gets a `section` field with the heading it belongs to,
      or null if there is only one ungrouped list.
    * Each step gets a `section` field too — usually the same headings as the
      ingredient sections, but sometimes different ("Preparation", "Cooking",
      "Serving"). Use whatever the source provides.
    * If a line in the ingredient list looks like a heading (e.g. "Voor de
      saus:" with no quantity), do NOT emit it as an ingredient. Instead, use
      it as the `section` value for the ingredients that follow.
- When the source gives the same quantity in two systems separated by "/"
  or " or " (e.g. "500g / 1lb", "1 cup / 240 ml", "1 lb / 450 g"), pick the
  METRIC option (g/ml/kg/l) and ignore the imperial duplicate.
        "500g / 1lb breast fillets"  → "500" / "g"  / "breast fillets"
        "1 cup / 240 ml milk"        → "240" / "ml" / "milk"
- When an ingredient line combines a count with a parenthetical weight or
  volume, prefer the parenthetical — it is more precise than the count.
  Examples (input → quantity_text / unit_text / name):
    "1 (10-ounce) package frozen peas"     → "10" / "oz"  / "package frozen peas"
    "2 (14-oz) cans chickpeas"             → "28" / "oz"  / "cans chickpeas" (sum the weights)
    "1 trimmed radishes (about 10 ounces)" → "10" / "oz"  / "trimmed radishes"
    "1 onion (about 200 g)"                → "200"/ "g"   → "onion"
  When the parenthetical is just a descriptor ("1 large onion (yellow)") and
  not a weight, leave the count and add the descriptor to the name.
- Length measurements (cm, inch) — common for ginger root or leek/scallion
  stems — do NOT fit our metric system (we only handle g/ml/tsp/tbsp/piece).
  Set quantity_text and unit_text to null and put the full text in the name.
        "8 cm gember"     → null / null / "8 cm gember"
        "1 inch ginger"   → null / null / "1 inch ginger"
- Dutch ingredient lines often prefix the unit with descriptive adjectives:
    "volle" (full / heaped) → ignore the adjective, keep the count as-is.
        "2 volle eetlepels sojasaus"  → "2"   / "el"  / "sojasaus"
        "1 volle theelepel peper"     → "1"   / "tl"  / "peper"
    "halve" (half) → quantity is 0.5, unit comes from the noun.
        "halve eetlepel suiker"       → "1/2" / "el"  / "suiker"
        "halve theelepel zout"        → "1/2" / "tl"  / "zout"
    "kleine"/"grote"/"verse" stay in the name as descriptors. They are NOT units.
        "1 grote ui"                  → "1"   / null  / "grote ui"
- For each ingredient, split the line into three pieces:
    * quantity_text: just the number portion ("1", "1/2", "1 1/2", "2-3", "0.5"),
      or null if no quantity.
    * unit_text: just the unit string as written ("cups", "el", "tbsp", "g", "ml",
      "stuks", "tl", "lb", "tsp", etc.), or null if there is no unit (e.g. "1 ui").
    * name: the ingredient name and any descriptors ("yellow onion, finely diced").
- For steps, return an ordered list with `section` and `body`. One step per
  array item. Strip step numbering like "1." or "Step 2:" — just the body.
- For image_url, return the most representative recipe photo URL if present in
  the source text, otherwise null.
- For cook_time_minutes, sum prep + cook time when both are listed; null if absent.
- For servings: copy what the source explicitly says. EXCEPTION: if the source
  has "1 serving" or "1 portie" together with ingredient quantities that
  obviously feed multiple people (e.g. 200 g+ of a main ingredient, 500 ml+
  of liquid, 3+ eggs, a whole onion, etc.), the author left the WordPress
  recipe-plugin default value untouched. In that case, estimate a realistic
  integer based on typical portion sizes (~100-200 g of main ingredient per
  person). This is portion-size reasoning, NOT unit math — you may use it.
  When in doubt or when the source confidently states "1 serving" for
  obviously single-person quantities (a single sandwich, one egg-on-toast,
  etc.), trust the source.
- If a field is missing in the source, use null. Never invent values.
- The recipe text may be in Dutch or English; preserve the source language.
- A US recipe ambiguously uses "oz" for both weight and volume. If a clearly
  liquid ingredient (milk, water, oil, broth, juice, beer, wine, vinegar,
  cream, syrup) is measured in plain "oz", output `unit_text: "fl oz"` —
  that is the underlying US convention. For solids (cheese, chocolate,
  cream cheese, meat) keep "oz".
- Determine the recipe's source culinary locale and return it as
  `source_locale`. Choose the value that best matches the cup/pint/spoon
  conventions of the source:
    * "us"     → United States (cup = 237 ml, pint = 473 ml). Default for
                 .com sites without other signals (NYT, AllRecipes, Bon
                 Appétit, Serious Eats).
    * "au"     → Australia / NZ (cup = 250 ml, tbsp = 20 ml). Recipetineats,
                 SBS, taste.com.au, donnahay.
    * "uk"     → United Kingdom / Ireland (pint = 568 ml). BBC Good Food,
                 Jamie Oliver, Delia, Ottolenghi UK editions.
    * "metric" → Continental Europe (cup = 250 ml; rare). NL/DE/FR/IT/ES
                 sites: 24kitchen, Allerhande, Eef Kookt Zo, leukerecepten,
                 Marmiton, Giallo Zafferano.
    * "unknown" → only when there is no signal at all.
  Use the source's domain, language, and unit choices to decide. When in
  doubt between us and metric for an English-language site, choose us.

# CATEGORIES / TAGS — closed enums

Classify the recipe into three groups. Use ONLY the slugs listed below;
never invent new ones. When uncertain, leave the array empty (or use
"unknown" for cuisines).

## meal_types — what kind of meal is this? (multiple allowed)

Allowed: {$mealTypes}

Pick everything that fits. A pasta carbonara is `avondeten`. A muffin is
both `ontbijt` and `bakken`. A guacamole is `borrelhap`. A fudge sauce is
`sauzen` and `dessert`. Pancakes are `ontbijt` (and `bakken` if from
scratch). Don't tag `bijgerecht` for main-course recipes; reserve it for
clear sides (slaatjes, aardappelpuree als bijgerecht, etc.).

## cuisines — culinary tradition (zero, one, or two)

Allowed: {$cuisines}

Use the recipe's culinary roots, not where the website is hosted.
Spaghetti carbonara is `italiaans` even on an American blog. Tag two
cuisines only for clear fusion (Korean tacos: `koreaans` + `mexicaans`).
For dishes that are global/non-specific (smoothies, fruit salads, basic
omelet), return an empty array.

## attributes — dish properties (zero to four)

Allowed: {$attributes}

Be conservative — only tag what is unambiguous from the recipe content:
  - `snel-en-makkelijk`: total time <= 30 min AND <= 8 ingredients AND no
    advanced techniques. Don't tag for ambiguous mid-effort recipes.
  - `weekendproject`: total time >= 2 hours OR multi-stage (rising dough,
    overnight marinade, slow braise, smoking, fermenting).
  - `one-pot`: cooked in a single vessel (skillet/pot/sheet pan) — explicit
    sign in the steps, not a guess.
  - `meal-prep`: source explicitly mentions batch cooking, freezing, or
    week-ahead preparation.
  - `vegetarisch`: no meat, poultry, fish, seafood, gelatine.
    Eggs/dairy are OK.
  - `veganistisch`: also no eggs, dairy, honey. If `veganistisch`, also
    add `vegetarisch`.
  - `glutenvrij` / `zuivelvrij`: only when the recipe is actually free of
    those, not just "low" of them. Don't infer from naming.
  - `comfort-food`: hearty, rich, warming — stews, mac & cheese, pot roast,
    lasagna, mashed potatoes. Soup is typically yes; salad is typically no.
  - `gezond`: explicitly health-focused (low cal, high protein, salade,
    light grain bowl). Most everyday recipes do NOT need this tag.
  - `gourmet`: restaurant-style, multi-component plating, advanced
    techniques (sous-vide, reductions, foams, complex pastry).
  - `kinderen`: explicitly kid-friendly, mild, simple flavours.
  - `bbq`: cooked on a grill / outdoor barbecue.
  - `oven`: primary cooking method is the oven (roasting, baking).
    Don't tag for a recipe that just briefly finishes in the oven.
  - `stoofpot`: braise, stew, slow simmer in liquid for 1+ hour.

When in doubt, omit the tag. False positives are worse than missing tags.
TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'source_locale' => $schema->string()->enum(['us', 'au', 'uk', 'metric', 'unknown'])->required(),
            'servings' => $schema->integer()->nullable()->required(),
            'cook_time_minutes' => $schema->integer()->nullable()->required(),
            'image_url' => $schema->string()->nullable()->required(),
            'meal_types' => $schema->array()
                ->items($schema->string()->enum(TagSeeder::slugsFor(Tag::GROUP_MEAL_TYPE)))
                ->required(),
            'cuisines' => $schema->array()
                ->items($schema->string()->enum(TagSeeder::slugsFor(Tag::GROUP_CUISINE)))
                ->required(),
            'attributes' => $schema->array()
                ->items($schema->string()->enum(TagSeeder::slugsFor(Tag::GROUP_ATTRIBUTE)))
                ->required(),
            'ingredients' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'section' => $schema->string()->nullable()->required(),
                        'quantity_text' => $schema->string()->nullable()->required(),
                        'unit_text' => $schema->string()->nullable()->required(),
                        'name' => $schema->string()->required(),
                    ]),
                )
                ->required(),
            'steps' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'section' => $schema->string()->nullable()->required(),
                        'body' => $schema->string()->required(),
                    ]),
                )
                ->required(),
        ];
    }
}
