<?php

namespace App\Actions\CookSessions;

use App\Models\CookSession;

final class UpdateCookSession
{
    /**
     * @param  array{servings_multiplier?: float, notes?: ?string}  $data
     */
    public function handle(CookSession $session, array $data): CookSession
    {
        $session->update($data);

        return $session;
    }
}
