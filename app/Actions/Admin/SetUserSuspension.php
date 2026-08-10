<?php

namespace App\Actions\Admin;

use App\Models\User;

/**
 * The single place a user's suspension changes.
 *
 * Suspension is reversible: the account and all of its data stay intact, it
 * just can't sign in or publish while `suspended_at` is set.
 */
class SetUserSuspension
{
    public function handle(User $user, bool $suspended, ?string $reason = null): User
    {
        $user->update([
            'suspended_at' => $suspended ? now() : null,
            'suspension_reason' => $suspended ? $reason : null,
        ]);

        return $user;
    }

    /**
     * An admin can never suspend their own account — that would lock them out
     * with no one left to reinstate them.
     */
    public static function allowedFor(User $actor, User $target, bool $requested): bool
    {
        return ! $requested || ! $actor->is($target);
    }
}
