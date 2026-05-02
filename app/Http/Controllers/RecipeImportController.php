<?php

namespace App\Http\Controllers;

use App\Actions\Recipes\ImportRecipeFromUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RecipeImportController extends Controller
{
    public function store(Request $request, ImportRecipeFromUrl $action): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
        ]);

        try {
            $recipe = $action->handle($request->user(), $data['url']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['url' => $e->getMessage()]);
        }

        return redirect()->route('recipes.show', $recipe);
    }
}
