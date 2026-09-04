<?php

namespace App\Console\Commands;

use App\Data\GeoPoint;
use App\Data\GeoPolygon;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatchesFound;
use App\Support\CurrencyConverter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('app:send-saved-search-alerts')]
#[Description('Create private in-app notifications for new properties matching saved searches')]
class SendSavedSearchAlerts extends Command
{
    public function handle(CurrencyConverter $currencyConverter): int
    {
        SavedSearch::query()->with('user')->where('alerts_enabled', true)->chunkById(100, function ($searches) use ($currencyConverter): void {
            foreach ($searches as $search) {
                $matches = $this->matchingProperties($search, $currencyConverter)->count();

                if ($matches > 0) {
                    $search->user->notify((new SavedSearchMatchesFound($search, $matches))->afterCommit());
                }

                $search->update(['last_notified_at' => now()]);
            }
        });

        return self::SUCCESS;
    }

    private function matchingProperties(SavedSearch $search, CurrencyConverter $currencyConverter): Builder
    {
        $filters = $search->filters;
        $since = $search->last_notified_at ?? $search->created_at;
        $displayCurrency = $filters['currency'] ?? $currencyConverter->baseCurrency();
        $minimumNormalizedPrice = isset($filters['min_price'])
            ? $currencyConverter->toBase($filters['min_price'], $displayCurrency)
            : null;
        $maximumNormalizedPrice = isset($filters['max_price'])
            ? $currencyConverter->toBase($filters['max_price'], $displayCurrency)
            : null;

        return Property::query()
            ->visibleToPublic()
            ->where('published_at', '>', $since)
            ->when($filters['location'] ?? null, fn (Builder $query, string $location) => $query->whereHas('location', fn (Builder $locationQuery) => $locationQuery->matchingHierarchy($location)))
            ->when($filters['property_type'] ?? null, fn (Builder $query, string $value) => $query->where('type', $value))
            ->when($filters['listing_type'] ?? null, fn (Builder $query, string $value) => $query->where('listing_type', $value))
            ->when($minimumNormalizedPrice !== null, fn (Builder $query) => $query->where('normalized_price_amount', '>=', $minimumNormalizedPrice))
            ->when($maximumNormalizedPrice !== null, fn (Builder $query) => $query->where('normalized_price_amount', '<=', $maximumNormalizedPrice))
            ->when(isset($filters['bedrooms']), fn (Builder $query) => $query->where('bedrooms', '>=', $filters['bedrooms']))
            ->when(isset($filters['bathrooms']), fn (Builder $query) => $query->where('bathrooms', '>=', $filters['bathrooms']))
            ->when(isset($filters['parking_spaces']), fn (Builder $query) => $query->where('parking_spaces', '>=', $filters['parking_spaces']))
            ->when(isset($filters['min_area']) || isset($filters['max_area']), fn (Builder $query) => $query->withinAreaRange(
                isset($filters['min_area']) ? (int) $filters['min_area'] : null,
                isset($filters['max_area']) ? (int) $filters['max_area'] : null,
                $filters['property_type'] ?? null,
            ))
            ->when($filters['furnishing'] ?? null, fn (Builder $query, string $value) => $query->where('furnishing', $value))
            ->when(isset($filters['utilities_included']), fn (Builder $query) => $query->where('utilities_included', $filters['utilities_included']))
            ->when(isset($filters['polygon']), fn (Builder $query) => $query->withinPolygon(
                new GeoPolygon($filters['polygon']),
            ))
            ->when(! isset($filters['polygon']) && isset($filters['latitude'], $filters['longitude']), fn (Builder $query) => $query->withinRadius(
                new GeoPoint((float) $filters['latitude'], (float) $filters['longitude']),
                Property::NEARBY_SEARCH_RADIUS_METERS,
            ));
    }
}
