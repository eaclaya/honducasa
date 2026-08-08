<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    /**
     * Prefix the redirect with the user's team slug, or leave it user-scoped when they have no team.
     */
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        if ($team === null) {
            return $redirect;
        }

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam(Request $request): ?Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        return $user->currentTeam ?? $user->fallbackTeam();
    }
}
