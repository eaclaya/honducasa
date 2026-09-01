<?php

namespace Database\Seeders;

use App\Enums\AnalyticsTier;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionProvider;
use App\Enums\SupportTier;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Placeholder catalog — prices and limits here are strawman numbers for
 * scaffolding, not settled pricing. Update via new rows (see
 * SubscriptionPlanController's docblock), never by editing amounts in place.
 */
class SubscriptionPlanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $plans = [
            [
                'key' => 'individual-basic',
                'ladder' => SubscriptionLadder::Individual,
                'name' => 'Individual — Basic',
                'active_listings_limit' => 3,
                'seats_limit' => 1,
                'featured_listing_slots' => 0,
                'analytics_tier' => AnalyticsTier::Basic,
                'support_tier' => SupportTier::Standard,
                'price_amount' => 350,
                'is_entry_tier' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'individual-plus',
                'ladder' => SubscriptionLadder::Individual,
                'name' => 'Individual — Plus',
                'active_listings_limit' => 10,
                'seats_limit' => 1,
                'featured_listing_slots' => 1,
                'analytics_tier' => AnalyticsTier::Full,
                'support_tier' => SupportTier::Standard,
                'price_amount' => 750,
                'is_entry_tier' => false,
                'sort_order' => 2,
            ],
            [
                'key' => 'agency-starter',
                'ladder' => SubscriptionLadder::Agency,
                'name' => 'Agency — Starter',
                'active_listings_limit' => 15,
                'seats_limit' => 3,
                'featured_listing_slots' => 0,
                'analytics_tier' => AnalyticsTier::Basic,
                'support_tier' => SupportTier::Standard,
                'price_amount' => 1_500,
                'is_entry_tier' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'agency-growth',
                'ladder' => SubscriptionLadder::Agency,
                'name' => 'Agency — Growth',
                'active_listings_limit' => 50,
                'seats_limit' => 10,
                'featured_listing_slots' => 3,
                'analytics_tier' => AnalyticsTier::Full,
                'support_tier' => SupportTier::Priority,
                'price_amount' => 3_500,
                'is_entry_tier' => false,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['key' => $plan['key']],
                [
                    ...$plan,
                    'currency' => 'HNL',
                    'provider' => SubscriptionProvider::Manual,
                    'is_active' => true,
                ],
            );
        }
    }
}
