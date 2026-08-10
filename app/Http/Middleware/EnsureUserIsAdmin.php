<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the platform administration console.
 *
 * Admin power is granted here and nowhere else. In particular there is no
 * `Gate::before` shortcut for administrators: that would silently bypass the
 * team-ownership checks in the property and conversation policies on every
 * non-admin route as well.
 */
class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin === true, 403);

        return $next($request);
    }
}
