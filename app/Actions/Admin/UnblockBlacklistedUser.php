<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnblockBlacklistedUser
{
    public function __construct(
        private SetUserSuspension $setUserSuspension,
        private RecordAdminActivity $recordAdminActivity,
    ) {}

    public function handle(User $admin, User $user, string $reason): User
    {
        return DB::transaction(function () use ($admin, $user, $reason): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $activeStrikeCount = $lockedUser->moderationStrikes()->active()->count();

            $lockedUser->moderationStrikes()->active()->update([
                'cleared_at' => now(),
                'cleared_by' => $admin->getKey(),
            ]);
            $this->setUserSuspension->handle($lockedUser, false);
            $this->recordAdminActivity->handle(
                $admin,
                'user.blacklist_unblocked',
                $lockedUser,
                $reason,
                [
                    'suspended' => ['from' => true, 'to' => false],
                    'active_strikes' => ['from' => $activeStrikeCount, 'to' => 0],
                ],
            );

            return $lockedUser;
        });
    }
}
