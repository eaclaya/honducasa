<?php

namespace App\Actions\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Team;
use App\Models\TeamSubscription;

class CancelTeamSubscription
{
    public function handle(Team $team): ?TeamSubscription
    {
        $subscription = $team->activeSubscription();

        $subscription?->update([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);

        return $subscription;
    }
}
