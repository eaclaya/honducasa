<?php

namespace App\Actions\Admin;

use App\Models\Team;

/**
 * A support gesture: push a team's trial window out further. Extends from
 * whichever is later — "now" or the current trial end — so it can't be used
 * to shorten an already-longer trial.
 */
class ExtendTeamTrial
{
    public function handle(Team $team, int $days): Team
    {
        $base = $team->trial_ends_at?->isFuture() ? $team->trial_ends_at : now();

        $team->update(['trial_ends_at' => $base->addDays($days)]);

        return $team;
    }
}
