<?php

namespace App\Models;

use App\Data\GeoPoint;
use App\Enums\ApproximateLocationShape;
use App\Enums\Furnishing;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $team_id
 * @property int $location_id
 * @property int $created_by
 * @property PropertyType $type
 * @property ListingType $listing_type
 * @property ListingStatus $status
 * @property Carbon|null $published_at
 * @property string|null $name
 * @property string $slug
 * @property string|null $address_line
 * @property string|null $address_landmark
 * @property string $coordinates
 * @property LocationPrecision $public_location_precision
 * @property ApproximateLocationShape|null $approximate_shape
 * @property int|null $approximate_radius_meters
 * @property array<string, mixed>|null $approximate_polygon
 * @property int $bedrooms
 * @property string $bathrooms
 * @property int $parking_spaces
 * @property int|null $interior_area_m2
 * @property int|null $lot_area_m2
 * @property int|null $year_built
 * @property Furnishing $furnishing
 * @property int $price_amount
 * @property string $currency
 * @property int|null $deposit_amount
 * @property bool $utilities_included
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Location $location
 * @property-read User $creator
 * @property-read Collection<int, Conversation> $conversations
 */
#[Fillable([
    'team_id',
    'location_id',
    'created_by',
    'type',
    'listing_type',
    'status',
    'published_at',
    'name',
    'slug',
    'address_line',
    'address_landmark',
    'public_location_precision',
    'approximate_shape',
    'approximate_radius_meters',
    'approximate_polygon',
    'bedrooms',
    'bathrooms',
    'parking_spaces',
    'interior_area_m2',
    'lot_area_m2',
    'year_built',
    'furnishing',
    'price_amount',
    'currency',
    'deposit_amount',
    'utilities_included',
    'description',
])]
#[Hidden(['address_line', 'coordinates'])]
class Property extends Model implements HasMedia
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    private const string DISTANCE_SELECT = 'ST_Distance(coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distance_meters';

    private const string WITHIN_RADIUS_CONDITION = 'ST_DWithin(coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)';

    /**
     * Get the team that owns the property.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the property's canonical location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who created the property.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(480)
            ->height(360)
            ->sharpen(10)
            ->nonQueued();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(PropertyFavorite::class);
    }

    /**
     * Limit properties to a radius and order them nearest first.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    #[Scope]
    protected function withinRadius(Builder $query, GeoPoint $origin, int $radiusMeters): Builder
    {
        if ($radiusMeters < 1 || $radiusMeters > 50_000) {
            throw new InvalidArgumentException('Radius must be between 1 and 50000 meters.');
        }

        return $query
            ->addSelect($query->getModel()->qualifyColumn('*'))
            ->selectRaw(self::DISTANCE_SELECT, [
                $origin->longitude,
                $origin->latitude,
            ])
            ->whereRaw(self::WITHIN_RADIUS_CONDITION, [
                $origin->longitude,
                $origin->latitude,
                $radiusMeters,
            ])
            ->orderBy('distance_meters')
            ->orderByDesc($query->getModel()->qualifyColumn('id'));
    }

    /**
     * Limit properties to those a visitor may see: published, and owned by a
     * team that isn't suspended. This is the single home of "public" — every
     * search, show page, and notification query filters through here so a
     * suspended team's listings can't surface anywhere outside its own console.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    #[Scope]
    protected function visibleToPublic(Builder $query): Builder
    {
        return $query
            ->where('status', ListingStatus::Published)
            ->whereHas('team', fn (Builder $team) => $team->whereNull('suspended_at'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'listing_type' => ListingType::class,
            'status' => ListingStatus::class,
            'published_at' => 'datetime',
            'public_location_precision' => LocationPrecision::class,
            'approximate_shape' => ApproximateLocationShape::class,
            'approximate_radius_meters' => 'integer',
            'approximate_polygon' => 'array',
            'bedrooms' => 'integer',
            'bathrooms' => 'decimal:1',
            'parking_spaces' => 'integer',
            'interior_area_m2' => 'integer',
            'lot_area_m2' => 'integer',
            'year_built' => 'integer',
            'furnishing' => Furnishing::class,
            'price_amount' => 'integer',
            'deposit_amount' => 'integer',
            'utilities_included' => 'boolean',
        ];
    }
}
