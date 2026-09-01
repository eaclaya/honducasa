<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamCanCreateListing
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeTeam = $request->route('current_team');
        $team = $routeTeam instanceof Team ? $routeTeam : null;

        if ($team !== null && ! $team->canPublishAnotherListing()) {
            return to_route('teams.billing.edit', $team)
                ->with('toast', [
                    'type' => 'warning',
                    'message' => __('You reached your active listing limit. Choose a plan to add another property.'),
                ]);
        }

        if ($team === null
            && $request->user()->individual_trial_ends_at !== null
            && ! $request->user()->canPublishAnotherIndividualListing()) {
            return to_route('billing.edit')
                ->with('toast', [
                    'type' => 'warning',
                    'message' => __('You reached your active listing limit. Choose a plan to add another property.'),
                ]);
        }

        return $next($request);
    }
}
