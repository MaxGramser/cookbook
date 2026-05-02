<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;
use Illuminate\Support\Facades\DB;

final class CompleteCookSession
{
    public function handle(CookSession $session): CookSession
    {
        if ($session->completed_at !== null) {
            return $session;
        }

        DB::transaction(function () use ($session) {
            $now = now();

            $session->update([
                'completed_at' => $now,
                'paused_seconds' => self::resolvePausedSeconds($session),
                'paused_at' => null,
            ]);

            $recipe = $session->recipe;
            $recipe->update([
                'cooked_count' => $recipe->cooked_count + 1,
                'last_cooked_at' => $now,
            ]);
        });

        return $session->refresh();
    }

    private static function resolvePausedSeconds(CookSession $session): int
    {
        $accumulated = (int) $session->paused_seconds;
        if ($session->paused_at === null) {
            return $accumulated;
        }

        return $accumulated + max(0, now()->diffInSeconds($session->paused_at, false) * -1);
    }
}
