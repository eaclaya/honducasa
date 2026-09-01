<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class GeoPoint
{
    private const float EARTH_RADIUS_KILOMETERS = 6371.0088;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if (! is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90 degrees.');
        }

        if (! is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180 degrees.');
        }
    }

    public function toPostgisPoint(): string
    {
        return sprintf('SRID=4326;POINT(%.8F %.8F)', $this->longitude, $this->latitude);
    }

    /**
     * Great-circle distance to another point, in kilometers.
     */
    public function distanceInKilometersTo(self $other): float
    {
        $latitudeDelta = deg2rad($other->latitude - $this->latitude);
        $longitudeDelta = deg2rad($other->longitude - $this->longitude);

        $chord = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KILOMETERS * asin(min(1.0, sqrt($chord)));
    }
}
