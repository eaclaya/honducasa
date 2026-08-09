<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $country_code
 * @property LocationType $type
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $parent
 * @property-read Collection<int, Location> $children
 * @property-read Collection<int, Property> $properties
 */
#[Fillable(['parent_id', 'country_code', 'type', 'name', 'slug', 'is_active'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * Match a location name without requiring users to enter accents.
     */
    #[Scope]
    protected function matchingSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('name', 'ilike', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.Str::slug($search).'%');
        });
    }

    /**
     * Match this location or any of its administrative ancestors.
     */
    #[Scope]
    protected function matchingHierarchy(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query
                ->matchingSearch($search)
                ->orWhereHas('parent', fn (Builder $parent) => $parent->matchingSearch($search))
                ->orWhereHas('parent.parent', fn (Builder $parent) => $parent->matchingSearch($search))
                ->orWhereHas('parent.parent.parent', fn (Builder $parent) => $parent->matchingSearch($search));
        });
    }

    /**
     * Get the parent administrative location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child administrative locations.
     *
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the properties assigned to this location.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }
}
