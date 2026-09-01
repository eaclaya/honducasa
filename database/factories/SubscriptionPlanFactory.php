<?php

namespace Database\Factories;

use App\Enums\AnalyticsTier;
use App\Enums\SubscriptionLadder;
use App\Enums\SubscriptionProvider;
use App\Enums\SupportTier;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name),
            'ladder' => fake()->randomElement(SubscriptionLadder::cases()),
            'name' => $name,
            'active_listings_limit' => fake()->numberBetween(1, 25),
            'seats_limit' => fake()->numberBetween(1, 5),
            'featured_listing_slots' => 0,
            'analytics_tier' => AnalyticsTier::Basic,
            'support_tier' => SupportTier::Standard,
            'price_amount' => fake()->numberBetween(500, 5_000),
            'currency' => 'HNL',
            'provider' => SubscriptionProvider::Manual,
            'is_entry_tier' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the plan is the free entry point for its ladder.
     */
    public function entryTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_entry_tier' => true,
        ]);
    }

    /**
     * Indicate that the plan has no listing cap.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'active_listings_limit' => null,
            'seats_limit' => null,
        ]);
    }
}
