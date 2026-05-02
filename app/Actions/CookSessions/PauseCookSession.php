<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;

final class PauseCookSession
{
    public function handle(CookSession $session): CookSession
    {
        if ($session->completed_at !== null || $session->paused_at !== null) {
            return $session;
        }

        $session->update(['paused_at' => now()]);

        return $session;
    }
}
