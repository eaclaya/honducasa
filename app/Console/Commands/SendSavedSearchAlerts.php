<?php

namespace App\Console\Commands;

use App\Data\GeoPoint;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatchesFound;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('app:send-saved-search-alerts')]
#[Description('Create private in-app notifications for new properties matching saved searches')]
class SendSavedSearchAlerts extends Command
{
    public function handle(): int
    {
        SavedSearch::query()->with('user')->where('alerts_enabled', true)->chunkById(100, function ($searches): void {
            foreach ($searches as $search) {
                $matches = $this->matchingProperties($search)->count();

                if ($matches > 0) {
                    $search->user->notify((new SavedSearchMatchesFound($search, $matches))->afterCommit());
                }

                $search->update(['last_notified_at' => now()]);
            }
        });

        return self::SUCCESS;
    }

    private function matchingProperties(SavedSearch $search): Builder
    {
        $filters = $search->filters;
        $since = $search->last_notified_at ?? $search->created_at;

        return Property::query()
            ->visibleToPublic()
            ->where('published_at', '>', $since)
            ->when($filters['location'] ?? null, fn (Builder $query, string $location) => $query->whereHas('location', fn (Builder $locationQuery) => $locationQuery->matchingHierarchy($location)))
            ->when($filters['property_type'] ?? null, fn (Builder $query, string $value) => $query->where('type', $value))
            ->when($filters['listing_type'] ?? null, fn (Builder $query, string $value) => $query->where('listing_type', $value))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $value) => $query->where('currency', $value))
            ->when(isset($filters['min_price']), fn (Builder $query) => $query->where('price_amount', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn (Builder $query) => $query->where('price_amount', '<=', $filters['max_price']))
            ->when(isset($filters['bedrooms']), fn (Builder $query) => $query->where('bedrooms', '>=', $filters['bedrooms']))
            ->when(isset($filters['bathrooms']), fn (Builder $query) => $query->where('bathrooms', '>=', $filters['bathrooms']))
            ->when(isset($filters['parking_spaces']), fn (Builder $query) => $query->where('parking_spaces', '>=', $filters['parking_spaces']))
            ->when(isset($filters['min_area']), fn (Builder $query) => $query->where('interior_area_m2', '>=', $filters['min_area']))
            ->when(isset($filters['max_area']), fn (Builder $query) => $query->where('interior_area_m2', '<=', $filters['max_area']))
            ->when($filters['furnishing'] ?? null, fn (Builder $query, string $value) => $query->where('furnishing', $value))
            ->when(isset($filters['utilities_included']), fn (Builder $query) => $query->where('utilities_included', $filters['utilities_included']))
            ->when(isset($filters['latitude'], $filters['longitude']), fn (Builder $query) => $query->withinRadius(
                new GeoPoint((float) $filters['latitude'], (float) $filters['longitude']),
                2_000,
            ));
    }
}
