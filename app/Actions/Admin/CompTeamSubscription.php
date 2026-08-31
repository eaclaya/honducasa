<?php

namespace App\Actions\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamSubscription;

/**
 * Grant a team free access to a plan without any real billing-provider
 * subscription behind it — recognizable by null provider IDs. For a support
 * gesture (promo, VIP landlord, resolving a billing dispute), not for
 * changing an already-paying team's plan.
 */
class CompTeamSubscription
{
    public function handle(Team $team, SubscriptionPlan $plan): TeamSubscription
    {
        return $team->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);
    }
}
