<?php

namespace App\Models;

use Database\Factories\GrocerySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['recipe_id', 'user_id', 'phase', 'started_at', 'completed_at'])]
class GrocerySession extends Model
{
    /** @use HasFactory<GrocerySessionFactory> */
    use HasFactory;

    public const PHASE_HOME = 'home';

    public const PHASE_SHOPPING = 'shopping';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkedIngredients(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipeIngredient::class,
            'grocery_session_ingredient_checks',
            'grocery_session_id',
            'recipe_ingredient_id',
        )->withPivot('checked_at', 'checked_in_phase');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isShopping(): bool
    {
        return $this->phase === self::PHASE_SHOPPING;
    }
}
