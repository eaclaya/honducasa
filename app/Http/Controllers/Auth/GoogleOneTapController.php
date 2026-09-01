<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ExecutePendingAuthAction;
use App\Actions\Auth\ResolveGoogleUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleOneTapRequest;
use App\Services\GoogleIdTokenVerifier;
use App\Support\SafeRedirectPath;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class GoogleOneTapController extends Controller
{
    public function __invoke(
        GoogleOneTapRequest $request,
        GoogleIdTokenVerifier $verifyGoogleIdToken,
        ResolveGoogleUser $resolveGoogleUser,
        ExecutePendingAuthAction $executePendingAuthAction,
    ): RedirectResponse {
        try {
            $claims = $verifyGoogleIdToken->verify($request->string('credential')->toString());
            $googleUser = (new SocialiteUser)->setRaw($claims)->map([
                'id' => $claims['sub'],
                'name' => $claims['name'] ?? null,
                'email' => $claims['email'],
                'avatar' => $claims['picture'] ?? null,
            ]);
            $user = $resolveGoogleUser->handle($googleUser);

            if ($user->isSuspended()) {
                throw new DomainException(__('This account has been suspended.'));
            }
        } catch (DomainException $exception) {
            return back()->withErrors(['google' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['google' => __('Google sign-in could not be completed. Please try again.')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $pendingActionRedirect = $executePendingAuthAction->handle($request, $user);
        $redirect = SafeRedirectPath::resolve($request->validated('redirect'));

        return $pendingActionRedirect
            ? redirect($pendingActionRedirect)
            : redirect()->intended($redirect ?? route('user.dashboard'));
    }
}
