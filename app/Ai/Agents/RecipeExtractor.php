<?php

namespace App\Ai\Agents;

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
        return <<<'TXT'
You extract a single recipe from cleaned HTML/text and return it as structured JSON.

# ABSOLUTE RULE — NO MATH

You do NOT perform any arithmetic. You do NOT convert, scale, multiply, divide,
or round any number. You ONLY split each ingredient line into pieces and copy
the original tokens verbatim. PHP code runs after you and does the math using
unit-conversion tables. If you "help" by converting cups to ml or oz to grams,
the PHP layer will multiply your already-converted number by the unit factor
again and the result will be wildly wrong. Repeat: copy numbers verbatim, copy
unit names verbatim. Only the labels you output drive the conversion table.

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
TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'source_locale' => $schema->string()->enum(['us', 'au', 'uk', 'metric', 'unknown']),
            'servings' => $schema->integer(),
            'cook_time_minutes' => $schema->integer(),
            'image_url' => $schema->string(),
            'ingredients' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'section' => $schema->string(),
                        'quantity_text' => $schema->string(),
                        'unit_text' => $schema->string(),
                        'name' => $schema->string()->required(),
                    ]),
                )
                ->required(),
            'steps' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'section' => $schema->string(),
                        'body' => $schema->string()->required(),
                    ]),
                )
                ->required(),
        ];
    }
}
