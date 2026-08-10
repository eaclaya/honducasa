<?php

namespace App\Actions\Admin;

use App\Models\User;

/**
 * The single place a user's admin flag changes.
 */
class SetUserAdminStatus
{
    public function handle(User $user, bool $isAdmin): User
    {
        $user->update(['is_admin' => $isAdmin]);

        return $user;
    }

    /**
     * An admin can never remove their own access — that could leave the
     * console with nobody left to open it back up.
     */
    public static function allowedFor(User $actor, User $target, bool $requested): bool
    {
        return $requested || ! $actor->is($target);
    }
}
