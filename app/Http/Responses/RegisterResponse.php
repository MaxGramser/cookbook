<?php

namespace App\Http\Responses;

use App\Http\Controllers\PublicRecipeController;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->session()->has(PublicRecipeController::PENDING_SESSION_KEY)) {
            return redirect()->route('share.recipe.claim');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
