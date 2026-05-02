<?php

namespace App\Models;

use Database\Factories\CookSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['recipe_id', 'user_id', 'servings_multiplier', 'notes', 'started_at', 'completed_at', 'paused_at', 'paused_seconds'])]
class CookSession extends Model
{
    /** @use HasFactory<CookSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'servings_multiplier' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'paused_seconds' => 'integer',
        ];
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
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
            'cook_session_ingredient_checks',
            'cook_session_id',
            'recipe_ingredient_id',
        )->withPivot('checked_at');
    }

    public function checkedSteps(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipeStep::class,
            'cook_session_step_checks',
            'cook_session_id',
            'recipe_step_id',
        )->withPivot('checked_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
