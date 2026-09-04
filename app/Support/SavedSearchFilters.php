<?php

namespace App\Support;

use Illuminate\Support\Arr;

class SavedSearchFilters
{
    /**
     * @var list<string>
     */
    private const FILTER_KEYS = [
        'location',
        'property_type',
        'listing_type',
        'currency',
        'min_price',
        'max_price',
        'bedrooms',
        'bathrooms',
        'parking_spaces',
        'min_area',
        'max_area',
        'furnishing',
        'utilities_included',
        'sort',
        'latitude',
        'longitude',
        'polygon',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        $normalized = Arr::where(
            Arr::only($filters, self::FILTER_KEYS),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        if (($normalized['sort'] ?? null) === 'newest') {
            unset($normalized['sort']);
        }

        // Validated but not yet cast: request classes deliberately preserve
        // whatever `explode()` produced so validation can reject a malformed
        // segment (see PolygonQueryParameter). Cast to float only now that
        // it's passed, so a saved search's fingerprint matches the float
        // points RentalSearchController builds for the live equivalent
        // search — a string/float mismatch here would make "already saved"
        // detection silently never match a polygon search.
        if (isset($normalized['polygon']) && is_array($normalized['polygon'])) {
            $normalized['polygon'] = array_map(
                fn (array $point): array => [(float) $point[0], (float) $point[1]],
                $normalized['polygon'],
            );
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fingerprint(array $filters): string
    {
        return hash('sha256', json_encode(self::normalize($filters), JSON_THROW_ON_ERROR));
    }
}
