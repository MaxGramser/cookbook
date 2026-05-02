<?php

namespace App\Http\Controllers;

use App\Actions\Recipes\ImportRecipeFromText;
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

    public function storeFromText(Request $request, ImportRecipeFromText $action): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:20', 'max:20000'],
            'image' => ['nullable', 'image', 'max:8192'],
        ]);

        try {
            $recipe = $action->handle(
                $request->user(),
                $data['text'],
                $request->file('image'),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['text' => $e->getMessage()]);
        }

        return redirect()->route('recipes.show', $recipe);
    }
}
