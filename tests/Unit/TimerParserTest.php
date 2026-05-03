<?php

use App\Support\Recipes\TimerParser;

it('extracts minutes from common Dutch and English phrasing', function (string $body, ?int $expected) {
    expect(TimerParser::extractMinutes($body))->toBe($expected);
})->with([
    // Plain digit + minute words
    'NL minuten' => ['Bak 5 minuten in de pan', 5],
    'NL minuutje' => ['Wacht een minuutje, oops Bak 3 minuutjes nog', 3],
    'NL min' => ['Laat 30 min staan', 30],
    'EN minutes' => ['Cook for 10 minutes', 10],
    'EN mins' => ['Bake for 45 mins', 45],
    'EN no space' => ['Boil 8min then drain', 8],
    'DE Minuten' => ['Backen Sie 20 Minuten lang', 20],
    'ES minutos' => ['Cocer durante 15 minutos', 15],
    'IT minuti' => ['Cuocere per 8 minuti', 8],

    // Hour-only
    'NL 2 uur' => ['Sudderen op laag vuur, 2 uur', 120],
    'EN 1 hour' => ['Rest for 1 hour', 60],
    'DE 1 stunde' => ['1 Stunde ruhen lassen', 60],

    // Combined hour + minute
    'NL 1 uur 30 minuten' => ['Pruttelen, 1 uur 30 minuten', 90],
    'EN 2 hours 15 minutes' => ['Bake 2 hours 15 minutes', 135],

    // Fixed phrases
    'NL half uur' => ['Wacht een half uur', 30],
    'NL halve uur' => ['Een halve uur op laag vuur', 30],
    'EN half an hour' => ['Let it rest half an hour', 30],
    'DE halbe stunde' => ['Eine halbe Stunde ruhen', 30],
    'ES media hora' => ['Reposar media hora', 30],
    'IT mezza ora' => ['Cuocere mezza ora', 30],
    'NL kwartier' => ['Een kwartier laten staan', 15],
    'NL kwartiertje' => ['Nog een kwartiertje', 15],
    'EN quarter of an hour' => ['Bake for a quarter of an hour', 15],
    'DE viertelstunde' => ['Eine Viertelstunde rasten', 15],

    // 1.5 hours
    'NL anderhalf' => ['Anderhalf uur op laag vuur', 90],
    'NL 1,5 uur' => ['1,5 uur in de oven', 90],
    'EN 1.5 hours' => ['Cook for 1.5 hours', 90],

    // Ranges (lower bound)
    'NL range minuten' => ['10-15 minuten roeren', 10],
    'EN range mins' => ['Bake for 20–25 mins', 20],
    'NL range uur' => ['1-2 uur', 60],

    // No false positives on words containing "min"
    'vitamin' => ['Voeg vitamine A toe', null],
    'terminus' => ['terminus station', null],
    'bare m' => ['op 5 m van het vuur', null],

    // Empty / null
    'empty' => ['', null],
    'no time' => ['Roer goed door', null],
]);

it('returns null for null input', function () {
    expect(TimerParser::extractMinutes(null))->toBeNull();
});

it('clamps absurd values to null', function () {
    // 999 hours is clearly bogus
    expect(TimerParser::extractMinutes('999 uur'))->toBeNull();
    // 0 minutes is not a usable timer
    expect(TimerParser::extractMinutes('0 minuten'))->toBeNull();
});
