<?php

namespace App\Actions\Moderation;

use App\Actions\Admin\SetUserSuspension;
use App\Models\ModerationStrike;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordModerationStrike
{
    public const int STRIKE_LIMIT = 3;

    public function __construct(private SetUserSuspension $setUserSuspension) {}

    /** @param array<string, mixed> $metadata */
    public function handle(User $user, string $source, string $reason, array $metadata = []): ModerationStrike
    {
        return DB::transaction(function () use ($user, $source, $reason, $metadata): ModerationStrike {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $strike = $lockedUser->moderationStrikes()->create([
                'source' => $source,
                'reason' => $reason,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            $activeStrikeCount = $lockedUser->moderationStrikes()->active()->count();

            if ($activeStrikeCount >= self::STRIKE_LIMIT && ! $lockedUser->isSuspended()) {
                $this->setUserSuspension->handle(
                    $lockedUser,
                    true,
                    'Automatically blocked after three content moderation violations.',
                );
            }

            return $strike;
        });
    }
}
