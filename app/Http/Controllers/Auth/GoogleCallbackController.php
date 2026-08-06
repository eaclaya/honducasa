<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveGoogleUser;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleCallbackController extends Controller
{
    public function __invoke(Request $request, ResolveGoogleUser $resolveGoogleUser): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            if (! $googleUser instanceof AbstractUser) {
                throw new DomainException(__('Google returned an unsupported identity response.'));
            }

            $user = $resolveGoogleUser->handle($googleUser);
        } catch (DomainException $exception) {
            return $this->failedRedirect($request, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failedRedirect($request, __('Google sign-in could not be completed. Please try again.'));
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('auth.google.invitation');

        $team = $user->currentTeam()->firstOrFail();

        return redirect()->intended(route('dashboard', ['current_team' => $team->slug]));
    }

    private function failedRedirect(Request $request, string $message): RedirectResponse
    {
        $invitation = $request->session()->pull('auth.google.invitation');

        return to_route('login', array_filter(['invitation' => $invitation]))
            ->withErrors(['google' => $message]);
    }
}
