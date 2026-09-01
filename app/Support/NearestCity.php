<?php

namespace App\Support;

use App\Data\GeoPoint;
use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves which city a listing belongs to from the pin dropped on the map.
 *
 * Publishers no longer choose a city by hand, so a listing's `location_id` is
 * derived from its coordinates: the closest city we both store a `Location` row
 * for and know the center of (see `HondurasCityCoordinates`).
 */
class NearestCity
{
    /**
     * Cities a listing can be filed under, each hydrated with `latitude` and
     * `longitude` attributes taken from the known city centers.
     *
     * @return Collection<int, Location>
     */
    public static function candidates(): Collection
    {
        return Location::query()
            ->where('country_code', 'HN')
            ->where('type', LocationType::City)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (Location $location): void {
                $center = HondurasCityCoordinates::for($location->name);
                $location->setAttribute('latitude', $center?->latitude);
                $location->setAttribute('longitude', $center?->longitude);
            })
            ->filter(fn (Location $location): bool => $location->getAttribute('latitude') !== null)
            ->values();
    }

    public static function for(GeoPoint $point): ?Location
    {
        return self::candidates()
            ->sortBy(fn (Location $location): float => $point->distanceInKilometersTo(new GeoPoint(
                (float) $location->getAttribute('latitude'),
                (float) $location->getAttribute('longitude'),
            )))
            ->first();
    }
}
