<?php

use App\Http\Controllers\CookSessionController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeImportController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [RecipeController::class, 'index'])->name('dashboard');

    Route::resource('recipes', RecipeController::class);
    Route::post('recipes/import', [RecipeImportController::class, 'store'])->name('recipes.import');

    Route::post('recipes/{recipe}/cook', [CookSessionController::class, 'store'])->name('cook.start');
    Route::get('cook/{session}', [CookSessionController::class, 'show'])->name('cook.show');
    Route::patch('cook/{session}', [CookSessionController::class, 'update'])->name('cook.update');
    Route::post('cook/{session}/complete', [CookSessionController::class, 'complete'])->name('cook.complete');
    Route::delete('cook/{session}', [CookSessionController::class, 'destroy'])->name('cook.destroy');
    Route::post('cook/{session}/ingredients/{ingredient}', [CookSessionController::class, 'toggleIngredient'])->name('cook.ingredient.toggle');
    Route::post('cook/{session}/steps/{step}', [CookSessionController::class, 'toggleStep'])->name('cook.step.toggle');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
});

require __DIR__.'/settings.php';
