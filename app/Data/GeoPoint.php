<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class GeoPoint
{
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
}
