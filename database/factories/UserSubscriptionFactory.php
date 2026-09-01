<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSubscription>
 */
class UserSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'provider_customer_id' => null,
            'provider_subscription_id' => null,
            'current_period_ends_at' => now()->addMonth(),
            'grace_period_ends_at' => null,
            'canceled_at' => null,
        ];
    }
}
