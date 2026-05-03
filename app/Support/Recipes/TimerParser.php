<?php

namespace App\Support\Recipes;

/**
 * Best-effort extraction of a cooking timer (in minutes) from a free-form step
 * body. Designed to handle the wide variety of ways recipes phrase a duration:
 * digits + unit words across NL/EN/DE/FR/ES/IT, plus fixed phrases like
 * "half uur", "kwartier", "anderhalf uur", "1 uur 30 minuten", ranges (lower
 * bound is used), and joined forms like "5min".
 */
final class TimerParser
{
    /** @var int Min/max timer minutes accepted. Anything outside is treated as not-a-timer. */
    private const MIN_MINUTES = 1;

    private const MAX_MINUTES = 240;

    /** Minute-word alternation. Long forms first so the regex prefers them over short ones. */
    private const MIN_WORDS = 'minuten|minutter|minuutjes|minuutje|minutes|minuti|minutos|minutas|minute|minuto|minuut|mins|min';

    /** Hour-word alternation. */
    private const HOUR_WORDS = 'stunden|stunde|hours|heures|horas|hour|uren|ore|uur|hora|ora|heure|hrs|hr|std|h';

    public static function extractMinutes(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }
        $haystack = mb_strtolower(trim($text));
        if ($haystack === '') {
            return null;
        }

        foreach (self::patterns() as $matcher) {
            $minutes = $matcher($haystack);
            if ($minutes !== null && $minutes >= self::MIN_MINUTES && $minutes <= self::MAX_MINUTES) {
                return $minutes;
            }
        }

        return null;
    }

    /**
     * @return array<int, callable(string): ?int>
     */
    private static function patterns(): array
    {
        $minWords = self::MIN_WORDS;
        $hourWords = self::HOUR_WORDS;

        return [
            // "1 uur 30 minuten", "1h30", "2 hours 15 minutes"
            static function (string $s) use ($hourWords, $minWords): ?int {
                $pattern = '/(?<![\w-])(\d{1,2})\s*(?:'.$hourWords.')\b[\s.,]*(\d{1,3})\s*(?:'.$minWords.')\b/iu';
                if (preg_match($pattern, $s, $m) === 1) {
                    return ((int) $m[1]) * 60 + (int) $m[2];
                }

                return null;
            },

            // "anderhalf uur", "1,5 uur", "1.5 hours", "one and a half hours"
            static function (string $s) use ($hourWords): ?int {
                $pattern = '/(?<![\w-])(?:anderhalf|1[,.]5|one\s+and\s+a\s+half|eineinhalb|anderhalve)\s*(?:'.$hourWords.')\b/iu';
                if (preg_match($pattern, $s) === 1) {
                    return 90;
                }

                return null;
            },

            // "half uur", "halve uur", "een half uur", "half an hour", "halbe Stunde", "media hora", "mezza ora", "demi-heure"
            static function (string $s) use ($hourWords): ?int {
                $pattern = '/(?<![\w-])(?:een\s+|a\s+|an\s+|eine\s+|une\s+|un[ao]\s+)?(?:halve|half|halbe|halb|media|mezza|mezz[oa]|demie?)\b[\s-]*(?:an?\s+|d[\'e]\s*)?(?:'.$hourWords.')\b/iu';
                if (preg_match($pattern, $s) === 1) {
                    return 30;
                }

                return null;
            },

            // "kwartier", "kwartiertje", "een kwartier", "quarter (of an) hour", "viertel Stunde", "cuarto de hora", "quarto d'ora"
            static function (string $s): ?int {
                $pattern = '/(?<![\w-])(?:'
                    .'kwartiertje|kwartier'
                    .'|quarter(?:\s+of\s+an?)?\s+hour'
                    .'|viertel(?:\s*-?\s*)?stunde'
                    .'|cuarto\s+de\s+hora'
                    .'|quarto\s+d[\'o]?\s*ora'
                    .'|quart\s+d[\'e]?\s*heure'
                    .')\b/iu';
                if (preg_match($pattern, $s) === 1) {
                    return 15;
                }

                return null;
            },

            // Bare hours: "2 uur", "1 hour", "3 hours", "2 ore", "1 stunde". Range "1-2 uur" → lower.
            static function (string $s) use ($hourWords): ?int {
                $pattern = '/(?<![\w-])(\d{1,2})(?:\s*[-–—]\s*\d{1,2})?\s*(?:'.$hourWords.')\b/iu';
                if (preg_match($pattern, $s, $m) === 1) {
                    return ((int) $m[1]) * 60;
                }

                return null;
            },

            // Bare minutes: "5 minuten", "10 mins", "20 Minuten", "30min". Range "10-15 min" → lower.
            static function (string $s) use ($minWords): ?int {
                $pattern = '/(?<![\w-])(\d{1,3})(?:\s*[-–—]\s*\d{1,3})?\s*(?:'.$minWords.')\b/iu';
                if (preg_match($pattern, $s, $m) === 1) {
                    return (int) $m[1];
                }

                return null;
            },
        ];
    }
}
