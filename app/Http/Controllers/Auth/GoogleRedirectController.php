<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SafeRedirectPath;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class GoogleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $invitation = $request->query('invitation');

        if (is_string($invitation) && $invitation !== '') {
            $request->session()->put('auth.google.invitation', $invitation);
        }

        if ($redirect = SafeRedirectPath::resolve($request->query('redirect'))) {
            $request->session()->put('auth.google.redirect', $redirect);
        }

        return Socialite::driver('google')->redirect();
    }
}
