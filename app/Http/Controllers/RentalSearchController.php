<?php

namespace App\Http\Controllers;

use App\Data\GeoPoint;
use App\Http\Requests\RentalSearchRequest;
use App\Models\Property;
use App\Support\CurrencyConverter;
use App\Support\SavedSearchFilters;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class RentalSearchController extends Controller
{
    public function __construct(private CurrencyConverter $currencyConverter) {}

    /**
     * Display searchable rental inventory.
     */
    public function __invoke(RentalSearchRequest $request): Response
    {
        $filters = $request->validated();
        $location = $filters['location'] ?? null;
        $propertyType = $filters['property_type'] ?? null;
        $listingType = $filters['listing_type'] ?? null;
        $sort = $filters['sort'] ?? 'newest';
        $displayCurrency = $filters['currency'] ?? $this->currencyConverter->baseCurrency();
        $minimumNormalizedPrice = isset($filters['min_price'])
            ? $this->currencyConverter->toBase($filters['min_price'], $displayCurrency)
            : null;
        $maximumNormalizedPrice = isset($filters['max_price'])
            ? $this->currencyConverter->toBase($filters['max_price'], $displayCurrency)
            : null;
        $hasBounds = isset($filters['west'], $filters['south'], $filters['east'], $filters['north']);
        $hasNearbyOrigin = isset($filters['latitude'], $filters['longitude']);
        $favoritePropertyIds = $request->user()?->propertyFavorites()->pluck('property_id')->all() ?? [];
        $currentFingerprint = SavedSearchFilters::fingerprint($filters);
        $matchingSavedSearch = $request->user()?->savedSearches()
            ->where('fingerprint', $currentFingerprint)
            ->first(['id', 'name', 'fingerprint']);
        $sourceSavedSearch = isset($filters['saved_search'])
            ? $request->user()?->savedSearches()
                ->whereKey($filters['saved_search'])
                ->first(['id', 'name', 'fingerprint'])
            : null;
        $activeSavedSearch = $matchingSavedSearch ?? $sourceSavedSearch;

        $query = Property::query()
            ->visibleToPublic()
            ->selectRaw("properties.*, CASE WHEN public_location_precision = 'exact' THEN ST_Y(coordinates::geometry) ELSE ROUND(ST_Y(coordinates::geometry)::numeric, 2) END AS map_latitude")
            ->selectRaw("CASE WHEN public_location_precision = 'exact' THEN ST_X(coordinates::geometry) ELSE ROUND(ST_X(coordinates::geometry)::numeric, 2) END AS map_longitude")
            ->with(['location:id,name', 'media'])
            ->when($location, fn (Builder $query, string $search) => $query
                ->whereHas('location', fn (Builder $locationQuery) => $locationQuery
                    ->matchingHierarchy($search)))
            ->when($propertyType, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($listingType, fn (Builder $query, string $type) => $query->where('listing_type', $type))
            ->when($minimumNormalizedPrice !== null, fn (Builder $query) => $query->where('normalized_price_amount', '>=', $minimumNormalizedPrice))
            ->when($maximumNormalizedPrice !== null, fn (Builder $query) => $query->where('normalized_price_amount', '<=', $maximumNormalizedPrice))
            ->when(isset($filters['bedrooms']), fn (Builder $query) => $query->where('bedrooms', '>=', $filters['bedrooms']))
            ->when(isset($filters['bathrooms']), fn (Builder $query) => $query->where('bathrooms', '>=', $filters['bathrooms']))
            ->when(isset($filters['parking_spaces']), fn (Builder $query) => $query->where('parking_spaces', '>=', $filters['parking_spaces']))
            ->when(isset($filters['min_area']), fn (Builder $query) => $query->where('interior_area_m2', '>=', $filters['min_area']))
            ->when(isset($filters['max_area']), fn (Builder $query) => $query->where('interior_area_m2', '<=', $filters['max_area']))
            ->when($filters['furnishing'] ?? null, fn (Builder $query, string $furnishing) => $query->where('furnishing', $furnishing))
            ->when(isset($filters['utilities_included']), fn (Builder $query) => $query->where('utilities_included', $filters['utilities_included']))
            ->when($hasNearbyOrigin, fn (Builder $query) => $query->withinRadius(
                new GeoPoint((float) $filters['latitude'], (float) $filters['longitude']),
                2_000,
            ))
            ->when($hasBounds, fn (Builder $query) => $query->whereRaw(
                'ST_Intersects(coordinates, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)',
                [$filters['west'], $filters['south'], $filters['east'], $filters['north']],
            ));

        match ($sort) {
            'price_asc' => $query->orderBy('normalized_price_amount')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('normalized_price_amount')->orderByDesc('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $properties = $query
            ->paginate(18)
            ->withQueryString()
            ->through(function (Property $property) use ($favoritePropertyIds, $displayCurrency) {
                $images = $property->getMedia('photos')
                    ->take(5)
                    ->map(fn ($media) => [
                        'url' => $media->getUrl('thumb'),
                        'altText' => $media->getCustomProperty('alt_text'),
                    ])
                    ->values();
                $primaryImage = $images->first();
                $normalizedPrice = $property->normalized_price_amount
                    ?? $this->currencyConverter->toBase($property->price_amount, $property->currency);

                return [
                    'id' => $property->id,
                    'slug' => $property->slug,
                    'name' => $property->name,
                    'type' => $property->type->value,
                    'listingType' => $property->listing_type->value,
                    'location' => $property->location->name,
                    'bedrooms' => $property->bedrooms,
                    'bathrooms' => $property->bathrooms,
                    'parkingSpaces' => $property->parking_spaces,
                    'interiorAreaM2' => $property->interior_area_m2,
                    'furnishing' => $property->furnishing->value,
                    'priceAmount' => $this->currencyConverter->fromBase($normalizedPrice, $displayCurrency),
                    'currency' => $displayCurrency,
                    'originalPriceAmount' => $property->price_amount,
                    'originalCurrency' => $property->currency,
                    'priceIsConverted' => $property->currency !== $displayCurrency,
                    'depositAmount' => $property->deposit_amount === null
                        ? null
                        : $this->currencyConverter->convert($property->deposit_amount, $property->currency, $displayCurrency),
                    'utilitiesIncluded' => $property->utilities_included,
                    'mapLatitude' => (float) $property->getAttribute('map_latitude'),
                    'mapLongitude' => (float) $property->getAttribute('map_longitude'),
                    'primaryImage' => $primaryImage,
                    'images' => $images,
                    'isFavorited' => in_array($property->id, $favoritePropertyIds, true),
                ];
            });

        return Inertia::render('rentals/Index', [
            'filters' => [
                'location' => $location ?? '',
                'propertyType' => $propertyType ?? '',
                'listingType' => $listingType ?? '',
                'currency' => $displayCurrency,
                'minPrice' => isset($filters['min_price']) ? (string) $filters['min_price'] : '',
                'maxPrice' => isset($filters['max_price']) ? (string) $filters['max_price'] : '',
                'bedrooms' => isset($filters['bedrooms']) ? (string) $filters['bedrooms'] : '',
                'bathrooms' => isset($filters['bathrooms']) ? (string) $filters['bathrooms'] : '',
                'parkingSpaces' => isset($filters['parking_spaces']) ? (string) $filters['parking_spaces'] : '',
                'minArea' => isset($filters['min_area']) ? (string) $filters['min_area'] : '',
                'maxArea' => isset($filters['max_area']) ? (string) $filters['max_area'] : '',
                'furnishing' => $filters['furnishing'] ?? '',
                'utilitiesIncluded' => isset($filters['utilities_included']) ? (bool) $filters['utilities_included'] : null,
                'sort' => $sort,
                'west' => $hasBounds ? (float) $filters['west'] : null,
                'south' => $hasBounds ? (float) $filters['south'] : null,
                'east' => $hasBounds ? (float) $filters['east'] : null,
                'north' => $hasBounds ? (float) $filters['north'] : null,
                'latitude' => $hasNearbyOrigin ? (float) $filters['latitude'] : null,
                'longitude' => $hasNearbyOrigin ? (float) $filters['longitude'] : null,
                'radiusMeters' => $hasNearbyOrigin ? 2_000 : null,
            ],
            'properties' => $properties,
            'currencies' => $this->currencyConverter->supportedCurrencies(),
            'baseCurrency' => $this->currencyConverter->baseCurrency(),
            'isSearchSaved' => $matchingSavedSearch !== null,
            'savedSearch' => $activeSavedSearch === null ? null : [
                'id' => $activeSavedSearch->id,
                'name' => $activeSavedSearch->name,
                'hasChanges' => $activeSavedSearch->fingerprint !== $currentFingerprint,
            ],
        ]);
    }
}
