<?php

use App\Http\Controllers\CookSessionController;
use App\Http\Controllers\GrocerySessionController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeImportController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [RecipeController::class, 'index'])->name('dashboard');

    Route::resource('recipes', RecipeController::class);
    Route::post('recipes/{recipe}/star', [RecipeController::class, 'toggleStar'])->name('recipes.star');
    Route::post('recipes/import', [RecipeImportController::class, 'store'])->name('recipes.import');
    Route::post('recipes/import/text', [RecipeImportController::class, 'storeFromText'])->name('recipes.import.text');

    Route::post('recipes/{recipe}/cook', [CookSessionController::class, 'store'])->name('cook.start');
    Route::get('cook/{session}', [CookSessionController::class, 'show'])->name('cook.show');
    Route::patch('cook/{session}', [CookSessionController::class, 'update'])->name('cook.update');
    Route::post('cook/{session}/complete', [CookSessionController::class, 'complete'])->name('cook.complete');
    Route::post('cook/{session}/pause', [CookSessionController::class, 'pause'])->name('cook.pause');
    Route::post('cook/{session}/resume', [CookSessionController::class, 'resume'])->name('cook.resume');
    Route::delete('cook/{session}', [CookSessionController::class, 'destroy'])->name('cook.destroy');
    Route::post('cook/{session}/ingredients/{ingredient}', [CookSessionController::class, 'toggleIngredient'])->name('cook.ingredient.toggle');
    Route::post('cook/{session}/steps/{step}', [CookSessionController::class, 'toggleStep'])->name('cook.step.toggle');

    Route::post('recipes/{recipe}/grocery', [GrocerySessionController::class, 'store'])->name('grocery.start');
    Route::get('grocery/{session}', [GrocerySessionController::class, 'show'])->name('grocery.show');
    Route::post('grocery/{session}/phase', [GrocerySessionController::class, 'phase'])->name('grocery.phase');
    Route::post('grocery/{session}/complete', [GrocerySessionController::class, 'complete'])->name('grocery.complete');
    Route::delete('grocery/{session}', [GrocerySessionController::class, 'destroy'])->name('grocery.destroy');
    Route::post('grocery/{session}/ingredients/{ingredient}', [GrocerySessionController::class, 'toggleIngredient'])->name('grocery.ingredient.toggle');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
});

require __DIR__.'/settings.php';
