<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title', 'source_url', 'image_path', 'servings', 'cook_time_minutes', 'notes', 'starred_at', 'last_cooked_at', 'cooked_count'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'cook_time_minutes' => 'integer',
            'starred_at' => 'datetime',
            'last_cooked_at' => 'datetime',
            'cooked_count' => 'integer',
        ];
    }

    public function isStarred(): bool
    {
        return $this->starred_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    public function cookSessions(): HasMany
    {
        return $this->hasMany(CookSession::class);
    }

    public function grocerySessions(): HasMany
    {
        return $this->hasMany(GrocerySession::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('group')->orderBy('sort');
    }

    public function shortlists(): BelongsToMany
    {
        return $this->belongsToMany(Shortlist::class, 'recipe_shortlist')
            ->withPivot(['position', 'note'])
            ->withTimestamps();
    }
}
