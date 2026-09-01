<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use App\Support\SafeRedirectPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    /**
     * The page a client explicitly asked to return to after authenticating
     * (e.g. a guest-triggered login modal on a property page), if any and
     * if it's safe. Passed as `intended()`'s default so a real intended URL
     * set by the auth middleware — the guest-tried-a-protected-route case —
     * still takes precedence.
     */
    protected function requestedRedirect(Request $request): ?string
    {
        return SafeRedirectPath::resolve($request->input('redirect'));
    }

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
