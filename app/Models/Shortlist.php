<?php

namespace App\Models;

use Database\Factories\ShortlistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'color'])]
class Shortlist extends Model
{
    /** @use HasFactory<ShortlistFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_shortlist')
            ->withPivot(['position', 'note'])
            ->withTimestamps()
            ->orderBy('recipe_shortlist.position');
    }

    public function grocerySessions(): HasMany
    {
        return $this->hasMany(GrocerySession::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ShortlistShare::class);
    }
}
