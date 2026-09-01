<?php

namespace App\Actions\Notifications;

use App\Models\TeamSubscription;
use App\Models\UserSubscription;
use App\Notifications\PlanSubscriptionUpdated;

class NotifySubscriber
{
    /**
     * The team side goes to the team's owner, not whoever clicked the
     * button — billing is account-of-record correspondence, and the actor
     * changing the plan (any admin with UpdateTeam) isn't always the owner.
     */
    public function ofPlanChange(TeamSubscription|UserSubscription $subscription): void
    {
        if ($subscription instanceof TeamSubscription) {
            $subscription->loadMissing(['team', 'plan']);
            $recipient = $subscription->team->owner();
            $targetUrl = route('teams.billing.edit', $subscription->team);
        } else {
            $subscription->loadMissing(['user', 'plan']);
            $recipient = $subscription->user;
            $targetUrl = route('billing.edit');
        }

        $recipient?->notify((new PlanSubscriptionUpdated($subscription->plan->name, $targetUrl))->afterCommit());
    }
}
