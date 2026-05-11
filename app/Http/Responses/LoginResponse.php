<?php

namespace App\Http\Responses;

use App\Http\Controllers\PublicRecipeController;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->session()->has(PublicRecipeController::PENDING_SESSION_KEY)) {
            return redirect()->route('share.recipe.claim');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
