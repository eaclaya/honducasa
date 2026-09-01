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
