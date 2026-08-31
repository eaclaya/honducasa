<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamSubscription>
 */
class TeamSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'provider_customer_id' => 'cus_'.fake()->unique()->bothify('##########'),
            'provider_subscription_id' => 'sub_'.fake()->unique()->bothify('##########'),
            'current_period_ends_at' => now()->addMonth(),
        ];
    }

    /**
     * Indicate that the subscription is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }

    /**
     * Indicate that a payment has failed and the team is in its grace period.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PastDue,
            'grace_period_ends_at' => now()->addDays(7),
        ]);
    }
}
