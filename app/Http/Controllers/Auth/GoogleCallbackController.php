<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveGoogleUser;
use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
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

            if ($user->isSuspended()) {
                throw new DomainException(__('This account has been suspended.'));
            }
        } catch (DomainException $exception) {
            return $this->failedRedirect($request, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failedRedirect($request, __('Google sign-in could not be completed. Please try again.'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        $this->acceptPendingInvitation($request, $user);

        $team = $user->currentTeam;

        return redirect()->intended($team
            ? route('dashboard', ['current_team' => $team->slug])
            : route('user.dashboard'));
    }

    /**
     * Accept the team invitation stashed before the OAuth redirect, if it is valid for this user.
     */
    private function acceptPendingInvitation(Request $request, User $user): void
    {
        $code = $request->session()->pull('auth.google.invitation');

        if (! is_string($code) || $code === '') {
            return;
        }

        $invitation = TeamInvitation::query()->where('code', $code)->first();

        if ($invitation === null
            || $invitation->isAccepted()
            || $invitation->isExpired()
            || strtolower($invitation->email) !== strtolower($user->email)) {
            return;
        }

        app(AcceptTeamInvitation::class)->handle($user, $invitation);
    }

    private function failedRedirect(Request $request, string $message): RedirectResponse
    {
        $invitation = $request->session()->pull('auth.google.invitation');

        return to_route('login', array_filter(['invitation' => $invitation]))
            ->withErrors(['google' => $message]);
    }
}
