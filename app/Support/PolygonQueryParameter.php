<?php

namespace App\Support;

class PolygonQueryParameter
{
    /**
     * Expand a `lng,lat;lng,lat;...` query string into `[[lng, lat], ...]`
     * pairs. A drawn search area travels this way rather than as a nested
     * array because Inertia's GET query serialization flattens an array of
     * coordinate pairs into one indistinguishable flat list — see
     * RentalSearchRequest. Malformed segments are left as whatever
     * `explode()` produced rather than dropped, so the normal validation
     * rules reject them with a real error instead of silently discarding
     * bad input.
     *
     * @return list<array<int, string>>|null
     */
    public static function expand(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return array_map(
            fn (string $point): array => explode(',', $point),
            explode(';', $value),
        );
    }
}
