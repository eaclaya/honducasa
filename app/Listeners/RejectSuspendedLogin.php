<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The single choke point for every session-based login path (password,
 * two-factor challenge, passkey). `Fortify::authenticateUsing()` already
 * rejects a suspended account before the two-factor screen, but passkey
 * login calls the guard directly and skips that callback entirely — so this
 * listener is what actually stops it, and doubles as a backstop for any
 * future login path that does the same.
 */
class RejectSuspendedLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! $event->user->isSuspended()) {
            return;
        }

        Auth::guard($event->guard)->logout();

        if ($this->request->hasSession()) {
            $this->request->session()->invalidate();
            $this->request->session()->regenerateToken();
        }

        throw ValidationException::withMessages([
            'email' => __('This account has been suspended.'),
        ]);
    }
}
