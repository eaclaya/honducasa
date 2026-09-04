<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class GeoPolygon
{
    /**
     * @param  list<array{0: float, 1: float}>  $points  closed ring of [lng, lat] pairs
     */
    public function __construct(public array $points)
    {
        if (count($points) < 4) {
            throw new InvalidArgumentException('A polygon must have at least 4 points (a closed ring of 3 vertices).');
        }

        if ($points[0] !== $points[array_key_last($points)]) {
            throw new InvalidArgumentException('A polygon ring must be closed (first and last point equal).');
        }
    }

    public function toPostgisPolygon(): string
    {
        $ring = implode(', ', array_map(
            fn (array $point): string => sprintf('%.8F %.8F', $point[0], $point[1]),
            $this->points,
        ));

        return sprintf('SRID=4326;POLYGON((%s))', $ring);
    }
}
