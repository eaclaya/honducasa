<?php

namespace App\Actions\Notifications;

use App\Models\TeamSubscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\Admin\NewAccountRegistered;
use App\Notifications\Admin\PlanSubscribed;
use Illuminate\Database\Eloquent\Collection;

class NotifyAdministrators
{
    public function ofNewAccount(User $user, string $registrationMethod): void
    {
        $this->administrators()->each(
            fn (User $administrator) => $administrator->notify(
                (new NewAccountRegistered($user, $registrationMethod))->afterCommit(),
            ),
        );
    }

    public function ofPlanSubscription(TeamSubscription|UserSubscription $subscription): void
    {
        $subscription->loadMissing($subscription instanceof TeamSubscription ? ['team', 'plan'] : ['user', 'plan']);

        $this->administrators()->each(
            fn (User $administrator) => $administrator->notify(
                (new PlanSubscribed($subscription))->afterCommit(),
            ),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        return User::query()
            ->where('is_admin', true)
            ->whereNull('suspended_at')
            ->get();
    }
}
