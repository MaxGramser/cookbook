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

Critical rules:
- DO NOT convert units. Copy the unit string verbatim from the source — even if it
  is American (cups, tbsp, oz, lb, fl oz, pint, quart) or unusual (tbspn, T, EL).
  Conversion to metric happens later in PHP code; if you alter the units the
  conversion will be wrong.
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
TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
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
