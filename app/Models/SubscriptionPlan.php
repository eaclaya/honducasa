<?php

namespace App\Models;

use App\Enums\AnalyticsTier;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionProvider;
use App\Enums\SupportTier;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A sellable tier. One row maps to one Product+Price on whichever payment
 * provider is in use — `provider_price_id` is a reference to that external
 * object, never computed locally. Price and provider fields are reference
 * only: a price change means a new plan row, not an edit to this one.
 *
 * @property int $id
 * @property string $key
 * @property SubscriptionLadder $ladder
 * @property string $name
 * @property int|null $active_listings_limit
 * @property int|null $seats_limit
 * @property int $featured_listing_slots
 * @property AnalyticsTier $analytics_tier
 * @property SupportTier $support_tier
 * @property int $price_amount
 * @property string $currency
 * @property SubscriptionProvider $provider
 * @property string|null $provider_price_id
 * @property bool $is_entry_tier
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TeamSubscription> $subscriptions
 */
#[Fillable([
    'key',
    'ladder',
    'name',
    'active_listings_limit',
    'seats_limit',
    'featured_listing_slots',
    'analytics_tier',
    'support_tier',
    'price_amount',
    'currency',
    'provider',
    'provider_price_id',
    'is_entry_tier',
    'is_active',
    'sort_order',
])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * @return HasMany<TeamSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TeamSubscription::class);
    }

    /**
     * The plan a trialing team gets access to, one per ladder.
     *
     * @param  Builder<SubscriptionPlan>  $query
     * @return Builder<SubscriptionPlan>
     */
    #[Scope]
    protected function entryTierFor(Builder $query, SubscriptionLadder $ladder): Builder
    {
        return $query->where('ladder', $ladder)->where('is_entry_tier', true);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ladder' => SubscriptionLadder::class,
            'active_listings_limit' => 'integer',
            'seats_limit' => 'integer',
            'featured_listing_slots' => 'integer',
            'analytics_tier' => AnalyticsTier::class,
            'support_tier' => SupportTier::class,
            'price_amount' => 'integer',
            'provider' => SubscriptionProvider::class,
            'is_entry_tier' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
