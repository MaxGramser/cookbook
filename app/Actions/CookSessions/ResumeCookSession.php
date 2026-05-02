<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;

final class ResumeCookSession
{
    public function handle(CookSession $session): CookSession
    {
        if ($session->completed_at !== null || $session->paused_at === null) {
            return $session;
        }

        $accumulated = (int) $session->paused_seconds;
        $delta = max(0, now()->diffInSeconds($session->paused_at, false) * -1);

        $session->update([
            'paused_seconds' => $accumulated + $delta,
            'paused_at' => null,
        ]);

        return $session;
    }
}
