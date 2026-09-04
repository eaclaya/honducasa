<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // `current_team` is only implicitly bound to a Team when the target
        // controller action itself type-hints one (e.g. ListingController's
        // `create`); routes like the team dashboard don't, so it arrives as
        // the raw slug string and must be resolved here. `EnsureTeamMembership`
        // runs first and already 403s on an unresolvable/foreign slug.
        $routeTeam = $request->route('current_team');
        $team = $routeTeam instanceof Team
            ? $routeTeam
            : (is_string($routeTeam) ? Team::where('slug', $routeTeam)->first() : null);

        if ($team !== null && ! $team->hasActiveAccess()) {
            return to_route('teams.billing.edit', $team)
                ->with('toast', [
                    'type' => 'warning',
                    'message' => __('Your trial has ended. Choose a plan to continue.'),
                ]);
        }

        if ($team === null
            && $request->user()->individual_trial_ends_at !== null
            && ! $request->user()->hasActiveAccess()) {
            return to_route('billing.edit')
                ->with('toast', [
                    'type' => 'warning',
                    'message' => __('Your trial has ended. Choose a plan to continue.'),
                ]);
        }

        return $next($request);
    }
}
