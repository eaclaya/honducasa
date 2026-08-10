<?php

namespace App\Actions\Admin;

use App\Models\Team;

/**
 * The single place a team's suspension changes.
 *
 * A suspended team keeps its listings but none of them stay public, so
 * suspension is a reversible alternative to deleting the team outright.
 */
class SetTeamSuspension
{
    public function handle(Team $team, bool $suspended, ?string $reason = null): Team
    {
        $team->update([
            'suspended_at' => $suspended ? now() : null,
            'suspension_reason' => $suspended ? $reason : null,
        ]);

        return $team;
    }
}
