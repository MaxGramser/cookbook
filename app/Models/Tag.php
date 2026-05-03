<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['group', 'slug', 'name', 'color', 'sort', 'is_system', 'user_id'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    public const GROUP_MEAL_TYPE = 'meal_type';

    public const GROUP_CUISINE = 'cuisine';

    public const GROUP_ATTRIBUTE = 'attribute';

    public const GROUPS = [
        self::GROUP_MEAL_TYPE,
        self::GROUP_CUISINE,
        self::GROUP_ATTRIBUTE,
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }

    public function scopeAvailableTo(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('is_system', true);
            if ($user !== null) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }
}
