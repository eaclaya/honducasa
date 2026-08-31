<?php

namespace App\Actions\Users;

use App\Actions\Notifications\NotifyAdministrators;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

class SubscribeToPlan
{
    public function handle(User $user, SubscriptionPlan $plan): UserSubscription
    {
        $subscription = DB::transaction(function () use ($user, $plan) {
            $user->activeSubscription()?->update([
                'status' => SubscriptionStatus::Canceled,
                'canceled_at' => now(),
            ]);

            return $user->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
            ]);
        });

        app(NotifyAdministrators::class)->ofPlanSubscription($subscription);

        return $subscription;
    }
}
