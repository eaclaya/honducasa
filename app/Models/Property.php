<?php

namespace App\Models;

use App\Data\GeoPoint;
use App\Enums\Furnishing;
use App\Enums\LocationPrecision;
use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $team_id
 * @property int $location_id
 * @property int $created_by
 * @property PropertyType $type
 * @property string|null $name
 * @property string $slug
 * @property string|null $address_line
 * @property string|null $address_landmark
 * @property string $coordinates
 * @property LocationPrecision $public_location_precision
 * @property int $bedrooms
 * @property string $bathrooms
 * @property int $parking_spaces
 * @property int|null $interior_area_m2
 * @property int|null $lot_area_m2
 * @property int|null $year_built
 * @property Furnishing $furnishing
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Location $location
 * @property-read User $creator
 */
#[Fillable([
    'team_id',
    'location_id',
    'created_by',
    'type',
    'name',
    'slug',
    'address_line',
    'address_landmark',
    'public_location_precision',
    'bedrooms',
    'bathrooms',
    'parking_spaces',
    'interior_area_m2',
    'lot_area_m2',
    'year_built',
    'furnishing',
    'description',
])]
#[Hidden(['address_line', 'coordinates'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'public_location_precision' => LocationPrecision::class,
            'bedrooms' => 'integer',
            'bathrooms' => 'decimal:1',
            'parking_spaces' => 'integer',
            'interior_area_m2' => 'integer',
            'lot_area_m2' => 'integer',
            'year_built' => 'integer',
            'furnishing' => Furnishing::class,
        ];
    }
}
