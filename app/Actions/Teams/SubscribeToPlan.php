<?php

namespace App\Actions\Teams;

use App\Actions\Notifications\NotifyAdministrators;
use App\Actions\Notifications\NotifySubscriber;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Self-service plan selection. No payment provider is wired up yet, so this
 * activates immediately against whatever provider the plan references
 * (today always `manual`) — the same shape as an admin comping a plan via
 * CompTeamSubscription, just team-initiated instead of a support gesture.
 *
 * Swaps out any existing live subscription rather than stacking rows, since
 * at most one lives per team (see the partial unique index on
 * `team_subscriptions`).
 */
class SubscribeToPlan
{
    public function handle(Team $team, SubscriptionPlan $plan): TeamSubscription
    {
        $subscription = DB::transaction(function () use ($team, $plan) {
            $team->activeSubscription()?->update([
                'status' => SubscriptionStatus::Canceled,
                'canceled_at' => now(),
            ]);

            return $team->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
            ]);
        });

        app(NotifyAdministrators::class)->ofPlanSubscription($subscription);
        app(NotifySubscriber::class)->ofPlanChange($subscription);

        return $subscription;
    }
}
